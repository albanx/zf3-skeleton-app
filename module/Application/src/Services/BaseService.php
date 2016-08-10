<?php
namespace Application\Services;
use Application\Model\BaseTable;
use Zend\ServiceManager\ServiceLocatorInterface;
use Zend\Db\TableGateway\TableGateway;
use Application\Model\Entity;
use Zend\Db\ResultSet\ResultSet;

class BaseService {

	protected $entities;
	public $sm;
	protected $authservice;
	protected $translator_domain = 'application';
    protected $conn = null;

	const QUERY_ERROR = 1;

	public function setServiceLocator(ServiceLocatorInterface $serviceLocator) {
		$this->sm = $serviceLocator;
	}

	public function getServiceLocator() {
		return $this->sm;
	}

    public function getConfig($config) {
        return $this->sm->get('Config')[$config];
    }

	private function getGateway($table, $entity)
	{
		$dbAdapter = $this->sm->get('Adapter_' . $entity::DB_TYPE);
		$resultSetPrototype = new ResultSet();
		$resultSetPrototype->setArrayObjectPrototype($entity);
		return new TableGateway($table, $dbAdapter, null, $resultSetPrototype);
	}

	/**
	 * Return the tablegateway of a table
	 * @param unknown $entity
	 * @return Ambigous <\Application\Extension\MmsTablegateway, \Zend\Db\TableGateway\TableGateway>
	 */
	public function getTableGateway($entity){
		$class 		= get_class($entity);
		$table 		= basename($class);
		// 		$nameSpace 	= str_replace('\\'.$table, '', $class);

		$dbAdapter 			= $this->sm->get('Adapter_' . $entity::DB_TYPE);
		$resultSetPrototype = new ResultSet();
		$resultSetPrototype->setArrayObjectPrototype($entity);
		return new TableGateway($entity::DB_TABLE, $dbAdapter, null, $resultSetPrototype);
	}


	public function getRepo($entity){
		$class 		= get_class($entity);
		$repoClass	= str_replace('Entity', 'Repository', $class).'Table';
		$tableGateway = $this->getTableGateway($entity);
		return new $repoClass($tableGateway);
	}


	/**
	 * Return the entity object with data (row of table)
	 * @param Entity $entity
	 * @return Ambigous <multitype:, ArrayObject, NULL, \ArrayObject, \Zend\Db\ResultSet\mixed, unknown>
	 */
	public function getEntity(Entity $entity){
		$tableGateway 	= $this->getTableGateway($entity);
		$data 			= $entity->getArrayCopy(true);
		$rowset 		= $tableGateway->select($data);
		$row 			= $rowset->current();
		return $row;
	}

	public function saveEntity(Entity $entity)
	{
		$data 			= $entity->getArrayCopy();
		$tableGateway 	= $this->getTableGateway($entity);
		if(isset($entity->id) && $entity->id)//FIXME find a better solution than puttin the id hard coded
		{
			$tableGateway->update($data, array('id' => $entity->id));
			return $entity->id;
		}
		else
		{
			$tableGateway->insert($data);
			$id = $tableGateway->getLastInsertValue();
			$entity->setValue(array('id' => $id));
		}
		return $entity->id;
	}

	public function deleteEntity(Entity $entity){
		$tableGateway 	= $this->getTableGateway($entity);
		$data 			= $entity->getArrayCopy(true);
		$ret 			= $tableGateway->delete( $data );
	}

	/**
	 * get a table for queries
	 * @param unknown $entity
	 * @return BaseTable
	 */
	protected function getTable($entity) {
// 		return $this->getRepo($entity);
		if (!$this->entities) {
			$this->entities = $this->sm->get('Config')['model_entity'];
		}

		if( isset($this->entities[$entity]) ) {
            $entity_data    = $this->entities[$entity];
            $table          = $entity_data['table'];
            $namespace      = $entity_data['namespace'];
            $entityClass    = $namespace . '\Model\Entity\\' . $entity;
            $repositoryClass = $namespace . '\Model\Repository\\' . $entity . 'Table';
            $entityObj      = new $entityClass();
            $tableGateway   = $this->getTableGateway($entityObj);
            return new $repositoryClass($tableGateway);
        }
	}

	/**
	 * Retrieve adapter information
	 * @param string $type
	 */
	public function getAdapterData($type = 'main'){

		$config = $this->sm->get('config');
		$dbParams = $config['db_params'];

		if($type == 'main'){
			$database = $dbParams['prefix'].'_main';
		}else{
			$workgrop_session = new \Zend\Session\Container('Workgroup');
			$id = $workgrop_session->workgroup_id;
			$database = $dbParams['prefix']."_$id";
		}

		return array(
				'driver'    => 'Mysqli',
				'database'  => $database,
				'username'  => $dbParams['username'],
				'password'  => $dbParams['password'],
				'hostname'  => $dbParams['hostname'],
				'options'   => array('buffer_results' => true),
				'charset'   => 'utf8',
				'port' 		=> isset($dbParams['port']) ? $dbParams['port'] : 3306
		);
	}

	public function _($str){
		$translator = $this->getServiceLocator()->get('translator');
		return $translator->translate($str, $this->translator_domain);
	}




	public function pureQuery($query, $vals=array(), $show=false)
	{
	    $conn      = $this->getMysqli();
	    $offset    = 0;
	    foreach ($vals as $v)
	    {
	        $cv         = $conn->real_escape_string($v);//escape the value for avoiding sql injection
	        $fv         = ($v===NULL) ? 'NULL':"'".$cv."'"; //if value is null then insert NULL in db
	        $qpos       = strpos($query, '?', $offset);//replace the ? with the valeue
	        $query      = substr($query, 0, $qpos).$fv.substr($query, $qpos+1);
	        $offset     = $qpos+strlen($cv)+1;
	    }
	
	    $result = $conn->query($query);

	    $rows = array();
	    if($result===true)
	    {
	        return $conn->affected_rows;
	    }
	    else if($result===false)
	    {
// 	        echo "<pre>".$query."</pre>";//TODO solo per admin
	        return false;
	    }
	    else
	    {
	        while ($row = $result->fetch_array(MYSQLI_ASSOC) )
	        {
	            $rows[]=$row;
	        }
	    }
	
	    return $rows;
	}

	function pureQueryVal($db_id, $query, $vals=array(), $show=false)
	{
		$ret = $this->pureQuery($db_id, $query, $vals, $show );
		if(is_array($ret) && count($ret)>0) return reset($ret[0]);

		return false;
	}

	function pureQueryRow($db_id, $query, $vals=array(), $show=false)
	{
		$ret = $this->pureQuery($db_id, $query, $vals, $show );
		if(is_array($ret) && count($ret)>0) return $ret[0];

		return false;
	}
	
	
	//================================== MANANGE DB FUNCTIONS =================================================\\
	public function getMysqli($work_id=null){
	    $config 	= $this->sm->get('Config');
	    if( !$this->conn ) {
    	    $this->conn = new \mysqli(
    	        $config['db_params']['hostname'],
    	        $config['db_params']['username'],
    	        $config['db_params']['password']
    	    );
	    }
	    
	    if($work_id){
	        $db = $config['prefix'].'_'.$work_id;
            $this->conn->query("USE $db");
	    }
	    
	    return $this->conn;
	}
	
	protected function runQueries($queryList, $ech_err = true)
	{
	    $mysqli = $this->getMysqli();
	    $mysqli->multi_query($queryList);
	    $arr 	= explode(';', $queryList);
	    $i 		= 0;
	    $error 	= '';
	    do
	    {
	        if (!$mysqli->more_results())	break;
	        $i++;
	
	        if (!$mysqli->next_result())
	        {
	            $error 		= $mysqli->error;
	            $queryError = $arr[$i];
	            break;
	        }
	
	    }while (true);
	
	    if ( $error )
	    {
	        if($ech_err) echo "$error:\n$queryError\n";
	        return false;
	         
	    }
	    return true;
	}
	
	public function slog($str){
		echo date('Y-m-d H:i:s').' '.$str."\n";
	}
	
	
	/**
	 *
	 * @param file $file
	 * @return multitype:mixed
	 */
	public function splitQueries($file)
	{
		$cont = file_get_contents($file);
		$cont = str_replace(array('USE `workgroup` ;', 'USE `main` ;'), '', $cont);
		$cont = preg_replace("/DROP SCHEMA(.*?);/", '', $cont);
		$cont = preg_replace("/DROP TABLE(.*?);/", '', $cont);
		$mainQueries 		   = '';
		$workgroupQueries 	   = '';
		$queries               = explode(';', $cont);
		 
		foreach ($queries as $k=>$q)
		{
			if( $this->checkQueryType($q, "`workgroup`.") ) {
                $workgroupQueries.= $q.';';
			} elseif( $this->checkQueryType($q, "`main`.`") ) {
                $mainQueries.= $q.';';
			}
		}
		return array('main' => $mainQueries, 'workgroup' => $workgroupQueries);
	}

    public function checkQueryType($q, $find) {
        $pattern  = '/ALTER TABLE '.$find.'(.+)|CREATE TABLE IF NOT EXISTS '.$find.'(.+)|CREATE TABLE '.$find.'(.+)|DROP TABLE IF EXISTS '.$find.'(.+)/';
        $match    = preg_match($pattern, $q);
        return $match;
    }

	public function getBaseUrl() {
		return sprintf("%s://%s", isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off' ? 'https' : 'http', $_SERVER['SERVER_NAME'] );
	}
}
