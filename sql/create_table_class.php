<?php
include __DIR__ . '/../vendor/autoload.php';

class ClassCreator
{

	private $config;
	private $arg;

	public function __construct($c, $a){
		$this->config = $c;
		$this->arg = $a;
	}

	public function run()
	{
		//avoid notice message
		$table 		= isset($this->arg[1]) ? $this->arg[1] : '';
		$db 		= isset($this->arg[2]) ? $this->arg[2] : '';
		$module 	= isset($this->arg[3]) ? $this->arg[3] : 'Application';

		$module_root = null;
		if(file_exists(__DIR__.'/../module/'.$module))
		{
		    $module_root = __DIR__.'/../module/';
		}

		if(!$table || !$db || !$module_root)
		{
			$help_string = "Usage: php create_table_class.php [table_name] [main|workgroup|%workgroup_key_name%] [module](OPTIONAL) \n";
			echo $help_string;
		}
		else
			$this->create_class($table, $db, $module, $module_root);
	}

	private function create_class($table, $db, $module, $module_root)
	{
		$prefix = $this->config['db_params']['prefix'];
		$adapter = $this->get_adapter();

		if($db == 'workgroup')
		{
			$workgroups = $adapter->query("SELECT * FROM workgroup ORDER BY id");
			$workgroup = $workgroups->fetch_array();
			$db_name = $prefix.'_'.$workgroup['id'];
		}
		elseif($db == 'main')
		{
			$db_name = $prefix.'_main';
		}

		$columns = $adapter->query("SHOW COLUMNS FROM $db_name.$table");

		if(!$columns || $columns->num_rows == 0){
			echo "Unable to find Table $table on database $db_name \n";
			return;
		}

		$camel_table = $this->to_camel_case($table, true);
		//pre declare keys to avoid notice...
		$keys = array(
	        'db_type' => $db,
			'db_table' =>$table,
	        'module' => $module,
			'table' => $camel_table,
			'fields' => '',
			'exchange' => '',
			'keys_var' => '',
			'keys_var_array' => '',
			'primary' => ''
		);

		while($column = $columns->fetch_array()){
			$field 		= $column['Field'];
			$type 		= $column['Type'];
			$default 	= !is_null($column['Default']) && $column['Default'] != 'CURRENT_TIMESTAMP' ? "'".$column['Default']."'" : 'null';
			$is_primary = $column['Key'] == 'PRI';

			$keys['fields'] .= $keys['fields'] == '' ? "public $".$field.";\n" : "\tpublic $".$field.";\n";

			$str_exchange = '$this->'.$field.' = isset($data[\''.$field.'\']) ? $data[\''.$field.'\'] : '.$default.';'."\n";
			$keys['exchange'] .= $keys['exchange'] == '' ? $str_exchange : "\t\t$str_exchange";

			if($is_primary){
				$keys['keys_var'] .= $keys['keys_var'] == '' ? "$".$field : ", $".$field;
				$keys['keys_var_array'] .= $keys['keys_var_array'] == '' ? "'$field' => $".$field : ", '$field' => $".$field;
				$keys['primary'] .= $keys['primary'] == '' ? "'$field'" : ", '$field'";
			}
		}

		$entity 	= file_get_contents('templates/entity.txt');
		$repository = file_get_contents('templates/repository.txt');

		foreach ($keys as $key => $value){
			$entity 	= str_replace("%$key%", $value, $entity);
			$repository = str_replace("%$key%", $value, $repository);
		}


		if(!file_exists($module_root.$module.'/src/Model/Entity/')){
			mkdir($module_root.$module.'/src/Model/Entity/', 0777, true);
			mkdir($module_root.$module.'/src/Model/Repository/', 0777, true);
		}

        $entityFile = $module_root.$module.'/src//Model/Entity/'.$camel_table.'.php';
        $repoFile = $module_root.$module.'/src/Model/Repository/'.$camel_table.'Table.php';

		file_put_contents($entityFile, $entity);
		file_put_contents($repoFile, $repository);

		//update entities config
		$config_path = $module_root.$module.'/config/entity.config.php';
		$entities = file_exists($config_path) ? include $config_path : array();

		$entities[$camel_table] = array('table' => $table, 'type' => $db, 'namespace' => "\\{$module}" );

		$content = "<?php return [\n";
		foreach ($entities as $entity => $data){
		  if(file_exists($module_root.$module.'/src/Model/Entity/'.$entity.'.php'))
		      $content .= "\t'".$entity."' => array('table' => '{$data['table']}', 'type' =>'{$data['type']}', 'namespace' => '{$data['namespace']}' ), \n";
		}
		$content .= "];";

		file_put_contents($config_path, $content);

        echo "Files created: \n $entityFile \n $repoFile \n";

	}

	private function to_camel_case($str, $capitalise_first_char = false) {
    	if($capitalise_first_char) {
      		$str[0] = strtoupper($str[0]);
    	}
    	$func = create_function('$c', 'return strtoupper($c[1]);');
    	return preg_replace_callback('/_([a-z])/', $func, $str);
  	}


	private function get_adapter(){

		$adapter = new mysqli(
				$this->config['db_params']['hostname'],
				$this->config['db_params']['username'],
				$this->config['db_params']['password'],
				$this->config['db_params']['prefix'].'_main'
				);
		return $adapter;
	}

}

try{
	$db_config = new Zend\Config\Config(include '../config/autoload/db.local.php');
	$creator = new ClassCreator($db_config, $argv);
    $creator->run();

}catch (Exception $e){
	echo $e->getMessage();
}

