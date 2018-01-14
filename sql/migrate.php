<?php
use Zend\Mvc\Application;
use Zend\Stdlib\ArrayUtils;
use Zend\ServiceManager\ServiceManager;

chdir(dirname(__DIR__));

// Setup autoloading
include __DIR__ . '/../vendor/autoload.php';
$appConfig = require __DIR__ . '/../config/application.config.php';

if (file_exists(__DIR__ . '/../config/development.config.php')) {
    $appConfig = ArrayUtils::merge($appConfig, require __DIR__ . '/../config/development.config.php');
}
$app = Application::init($appConfig);
$sm         = $app->getServiceManager();

$db_config = new Zend\Config\Config(include 'config/autoload/db.local.php');


class Migrator extends \Application\Services\BaseService
{
    private $config;
    private $arg;
    private $migrationPath = 'migrations';

    public function __construct($c, $a, ServiceManager $sm)
    {
        $this->config = $c;
        $this->arg    = $a;
        $this->sm     = $sm;
        if (!file_exists($this->migrationPath)) {
            mkdir($this->migrationPath, 0777, true);
        }
    }

    public function run() {
        //avoid notice message
        $command = isset($this->arg[1]) ? $this->arg[1] : 'none';
        $arg     = isset($this->arg[2]) ? $this->arg[2] : '';
        $work    = isset($this->arg[3]) ? $this->arg[3] : '';

        switch ($command) {
            case 'create':
                $this->createMigrationFile($arg);
                break;
            case 'update':
                $this->applyMigrations();
                break;
            case 'install':
                $this->createDb();
                break;
            default :
                $help_string = "Usage:\n\tcreate -> create a new migration file using update.sql \n";
                $help_string .= "\tupdate -> apply migration(s) to DB\n";
                $help_string .= "\tinstall -> Create main db, only if not exists, with the given prefix configured in ['db_params']['prefix']. Inserts default user and group. Also inserts all migrations as done\n";
                $help_string .= "NOTE: For creating the main DB first disable DB session handler: in config/autoload/global.php comment this line 'save_handler' => 'db'\n";
                echo $help_string;
        }
    }

    /**
     * Analys the update.sql generated from WorkBench and create a migration file in php
     *
     * @param unknown $arg
     */
    private function createMigrationFile($arg) {
        $queries                   = $arg != 'empty' ? $this->splitQueries('update.sql') : '';
        $main_queries              = $arg != 'empty' ? $queries['main'] : '';
        $workgroup_queries         = $arg != 'empty' ? $queries['workgroup'] : '';
        $permq                     = $arg != 'empty' ? $queries['permission'] : '';
        $version                   = date('YmdHis');

        $content = "<?php return array(
	                   'version' => $version,
	                   'main' => \"\t$main_queries\",
	                   'workgroup' => \"\t$workgroup_queries\",
                        'pre_function' => function(\$this){
    	                   try{
    	                       /*\$workgroups = \$this->getTable('Workgroup')->fetchAll();
    	                       \$workgroup_service =  \$this->sm->get('WorkgroupService');
    	
    	                       foreach (\$workgroups as \$k =>\$workgroup)
    	                       {
    	                           \$workgroup_service->changeWorkgroup(\$workgroup->id, false);
    	                       }*/
    	                       return 1;
    	                   }catch(\\Exception \$e){
    	                       echo \$e->getMessage();
    	                       return 0;
    	                   }
                        },
                        'post_function' => function(\$this){
                        	try{
                            	/*\$workgroups = \$this->getTable('Workgroup')->fetchAll();
                            	\$workgroup_service =  \$this->sm->get('WorkgroupService');
                            	
                            	foreach (\$workgroups as \$k =>\$workgroup)
                            	{
                            	\$workgroup_service->changeWorkgroup(\$workgroup->id, false);
                            	}*/
                            	return 1;
                        	}catch(\\Exception \$e){
                        	   echo \$e->getMessage();
                        	   return 0;
                        	}
                    	}
	               );";

        file_put_contents($this->migrationPath . "/migration_$version.php", $content);
        $this->slog("Migration created: " . $this->migrationPath . "/migration_$version.php");
    }




    private function applyMigrations()
    {
        $prefix 	= $this->config['db_params']['prefix'];
        $adapter 	= $this->getMysqli();
        $migrations = glob("migrations/migration_*.php");
        $dbw        = $prefix.'_main';

        $works      = $this->pureQuery("SELECT * FROM $dbw.workgroup ORDER BY id");

        //put file in alphabethical order
        sort($migrations);
        foreach ($migrations as $migration) {
            $migration_data = include $migration;
            $version        = $migration_data['version'];
            if (!is_numeric($version)) continue;

            $pre_fun           = isset($migration_data['pre_function']) ? $migration_data['pre_function'] : null;
            $main_queries      = str_replace('`main`.', '`'.$prefix.'_main`.', $migration_data['main']);
            $w_queries_s         = str_replace('`main`.', '`'.$prefix.'_main`.', $migration_data['workgroup']);
            $wc_queries_s        = str_replace('`main`.', '`'.$prefix.'_main`.', $migration_data['workgroup_custom']);

            $already_migrated          = $adapter->query("SELECT * FROM $dbw.migration WHERE version=$version");
            $proceed                   = true;
            $pre_function_already_done = false;
            $done_db                   = array();

            if($already_migrated->num_rows)
            {
                $r         = $already_migrated->fetch_assoc();
                $proceed   = $r['applied'] == 0;
                if($proceed)
                {
                    $pre_function_already_done = $r['pre_function'] == 1;
                    if($r['schema']) $done_db = unserialize($r['schema']);
                }
            }

            //if migration not applied yet
            if($proceed)
            {
                $this->slog("******************* MIGRATION $version ********************");
                $failed_db = array();

                //Pre function

                $pre_success = 0;
                if(!$pre_function_already_done && is_callable($pre_fun))
                {
                    $this->slog("=====> PRE FUNCTION ");
                    $pre_success = $pre_fun($this);
                    if($pre_success != 1)
                    {
                        $this->slog("=====> PRE FUNCTION ERROR, MIGRATION STOP");
                        continue;
                    }
                }
                else
                {
                    $pre_success = 1;
                }


                //===============> MAIN Because main is done only once
                if(!in_array($prefix.'_main', $done_db))
                {

                    if($main_queries)
                    {
                        $this->slog( "=====>UPDATING ".$prefix."_main" );
                        $ret = $this->runQueries($main_queries);
                        $this->slog( $ret ? "--> SUCCESS!" : "-->  FAILED : $ret" );
                        $ret ? $done_db[] = $prefix.'_main' : $failed_db[] = $prefix.'_main';
                    }
                }

                //==============> WORKGROUP for external keys pointing to main db
                if($wc_queries_s || $w_queries_s)
                {
                    //for each workgroup apply
                    foreach ($works as $wk)
                    {
                        $id_work = $wk['id'];
                        $db_name = $prefix.'_'.$id_work;

                        $this->slog("=======> APLLYING MIGRATION TO {$wk['id']} - {$wk['label']}");
                        if( !in_array($prefix.'_'.$id_work, $done_db) )
                        {
                            //==============> STANDARD TABLES UPDATE
                            if($w_queries_s)
                            {
                                $w_queries = str_replace('`workgroup`.', "`$db_name`.", $w_queries_s);

                                $this->slog("=====> UPDATING $db_name");
                                $ret = $this->runQueries($w_queries);
                                $this->slog($ret ? "=====> SUCCESS!" : "=====> FAILED : $ret");

                                $ret ? $done_db[] = $db_name : $failed_db[] = $db_name;
                            }
                        }
                        $this->slog("=======> MIGRATION TO {$wk['id']} - {$wk['label']} APPLIED\n\n");
                    }
                }


                //psot function
                $post_fun       = isset($migration_data['post_function']) ? $migration_data['post_function'] : null;
                $post_success   = $post_fun === null ? 1 : 0;
                if(is_callable($post_fun) && count($failed_db) == 0)
                {
                    $this->slog("--> POST FUNCTION");
                    $post_success = $post_fun($this);
                    if($post_success != 1)
                    {
                        $this->slog("--> POST FUNCTION ERROR");
                    }
                }

                $applied 	= count($failed_db) == 0 && $post_success ? 1 : 0;
                $stmt 		= $adapter->prepare("REPLACE INTO $dbw.migration(`version`,`applied`,`schema`, pre_function) VALUES (?, ?, ?, ?)");
                $ser        = serialize($done_db);
                $stmt->bind_param("iisi", $version, $applied, $ser, $pre_success);
                $stmt->execute();
                $this->slog("");//solo per uno spazio

                $this->slog("**************** MIGRATION $version DONE *******************");
                //se non andata a buon fine non faccio le altre!
                if(!$applied) break;
            }
        }
    }


    /**
     * Function that creates the main schema db. Disable the db session handle first in config/autoload/global.php
     */
    private function createDb()
    {
        $prefix    = $this->config['db_params']['prefix'];
        $main_dbw  = $prefix.'_main';
        $ext_db    = $this->pureQuery("SHOW DATABASES LIKE '$main_dbw'");

        if(!count($ext_db))
        {
            $this->slog('========> Creating DB '.$prefix.'_main ...');
            $queries 			= $this->splitQueries('schema.sql');//CREATE SCHEMA IF NOT EXISTS `main`
            $main_queries 		= str_replace('`main`.', '`'.$main_dbw.'`.', $queries['main']);
            $main_queries 		= str_replace("CREATE SCHEMA IF NOT EXISTS `main`", "", $main_queries);
            $main_queries       = "CREATE SCHEMA IF NOT EXISTS `$main_dbw` DEFAULT CHARACTER SET utf8 ;".$main_queries;

            try {
                $this->runQueries($main_queries);
                $this->slog('========> Creating DB completed successfully');

                $this->startData();
            } catch (Exception $e) {
                echo $e->getMessage();
                $this->slog('========> Error creating db ');
            }

            $this->slog('========> Inserting migration already in schema');
            $migrations = glob("migrations/migration_*.php");
            sort($migrations);
            foreach ($migrations as $migration)
            {
                $migration_data    = include $migration;
                $version           = $migration_data['version'];
                $ser           = serialize(array());
                $applied       = 1;
                $pre_function  = 1;
                if(!is_numeric($version)) continue;
                $this->pureQuery("REPLACE INTO $main_dbw.migration(`version`,`applied`,`schema`, pre_function) VALUES (?, ?, ?, ?)", array( $version, $applied, $ser, $pre_function));
            }
            $this->slog('========> Migrations inserted ');

        }
        else
        {
            $this->slog('========> Database "'.$main_dbw.'" already exists nothing changed. ');
        }
    }


    //inserts base start data in DB
    private function startData()
    {
        try{
        }catch (Exception $e){
        }
    }
}



//START THE BIG WORK
try{
    chdir(__DIR__);
    $migrator = new Migrator($db_config, $argv, $sm);
    $migrator->run();

}catch (Exception $e){
    echo $e->getMessage();
}