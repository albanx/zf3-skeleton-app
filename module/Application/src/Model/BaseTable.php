<?php
namespace Application\Model;
use Zend\Db\TableGateway\TableGateway;
use Zend\Db\Sql\Sql;
use Zend\Db\Sql\Where;
use Zend\Db\Sql\Predicate\Like;

class BaseTable{

	protected $tableGateway;

	public function __construct(TableGateway $tableGateway)
	{
		$this->tableGateway = $tableGateway;
	}
	public function getDBPrefix(){
		$db = $this->tableGateway->adapter->getCurrentSchema();
		$ind = strrpos($db, '_');
		$pre = substr($db, 0, $ind);
		return $pre;
	}
	public function findAll($orderby, $asc_desc='asc')
	{
		$sql      = new Sql($this->tableGateway->adapter);
		$select   = new \Zend\Db\Sql\Select;
		$select->from($this->tableGateway->getTable() );
		$select->columns(array('*'))->order($orderby.' '.$asc_desc);
		$stm      = $sql->prepareStatementForSqlObject($select);
		$resultSet = $stm->execute();
		return $resultSet;
	}


	/**
	 *
	 * @param mixed $where array or string where condition
	 * @param string $orderby order field
	 * @param string $dir order direction
	 */
	public function findBy($where, $orderby=false, $dir='asc', $limit=false, $offset=false)
	{
	    $select = new \Zend\Db\Sql\Select;
	    $select->from($this->tableGateway->getTable() );
	    $select->columns(array('*'));

	    if(count($where)){
            $select->where($where);
	    }
	    if($orderby)   $select->order($orderby.' '.$dir);
	    if($limit)     $select->limit($limit);
	    if($offset)    $select->offset($offset);

	    return $this->tableGateway->selectWith($select);
	}

	/**
	 * @param $where
	 * @param bool $orderBy
	 * @param string $dir
	 * @return Entity
	 */
	public function findOneBy($where, $orderBy=false, $dir='asc')
	{
		$resultSet = $this->findBy($where, $orderBy, $dir, 1);
		return $resultSet->count() ? $resultSet->current() : false;
	}

	public function deleteBy ($array)
	{
		if(count($array)){
			$this->tableGateway->delete($array);
		}
	}

	public function insert($data)
	{
		$this->tableGateway->insert($data);
		return $this->tableGateway->getLastInsertValue();
	}

	public function update($data, $where){
		return $this->tableGateway->update($data, $where);
	}

	protected function createDTJoins(\Zend\Db\Sql\Select $select){

	}

	protected function getDefaultOrderDT(){
		return array('id', 'asc');

	}

    /**
     * Function to map fields to alias, for custom db datatable names
     * @param $field_map
     */
    protected function setFieldMapDT(&$field_map){
    }

	/*
	 * Data Table Search:
	 * $where fatto da due array: array dei search in OR, e dei search in AND
	 */
	public function DTSeachAndCount($dwhere=array(), $orderBy=null, $limit=null, $offset=null)
	{
	    $field_map = array(
	    );

	    $this->setFieldMapDT($field_map);
	    $sql      = new Sql($this->tableGateway->adapter);
	    $select   = new \Zend\Db\Sql\Select;
	    $select->columns(array('*'));
	    $select->from(array('a'=>$this->tableGateway->table));
	    $this->createDTJoins($select);


	    $orWhere = new Where();
	    foreach($dwhere['or'] as $field=>$value)
	    {
	        $field = isset($field_map[$field]) ? $field_map[$field] : $field;
	        if(! is_array($field) ) $field = array($field);

	        foreach ($field as $ff)
	        {
	            $like = new Like();
	            $like->setIdentifier($ff);
	            $like->setLike('%'.$value.'%');
	            $orWhere->orPredicate($like);
	        }
	    }

	    $andWhere = new Where();
	    foreach($dwhere['and'] as $field=>$value)
	    {
	        $field = isset($field_map[$field]) ? $field_map[$field] : $field;
	        if(! is_array($field) ) $field = array($field);

	        foreach ($field as $ff)
	        {
	            $like = new Like();
	            $like->setIdentifier($ff);
	            $like->setLike($value);
	            $andWhere->andPredicate($like);
	        }
	    }

	    if(count($dwhere['or']))  $select->where->andPredicate($orWhere);
	    if(count($dwhere['and'])) $select->where->andPredicate($andWhere);

	    $count        = $this->countResults($sql, $select);

	    if($orderBy)
	        $select->order($orderBy);
	    else
	        $select->order(join(' ', $this->getDefaultOrderDT()));

	    if($limit)    $select->limit($limit);
	    if($offset)   $select->offset($offset);

	    $statement    = $sql->prepareStatementForSqlObject($select);
// 	    var_dump($sql->getSqlStringForSqlObject($select));
	    $result       = $statement->execute();
	    return array('result'=>$result, 'count'=>$count);
	}

	/**
	 * funzione per eseguire query pure, simile a vecchi mms
	 * @param unknown $sql
	 * @param unknown $params
	 */
	public function query($sql, $params=array()){
		return $this->tableGateway->adapter->query($sql, $params);
	}

	public function countResults($sql, $select){
		$c_state  = $sql->prepareStatementForSqlObject($select);
		//die($sql->getSqlStringForSqlObject($select));
		return $c_state->execute()->count();
	}



	//FIXME use in the future
	private function getMysqli()
	{
		$data = $this->tableGateway->adapter->getDriver()->getConnection()->getConnectionParameters();
		return new \mysqli(
				$data['hostname'],
				$data['username'],
				$data['password'],
				$data['database'],
				isset($data['port']) ? $data['port'] : 3306
		);
	}

	/**
	 * retrieve query result with mysql object
	 * @param unknown $sql
	 * @param unknown $select
	 * @param string $schema
	 * @return multitype:
	 */
	public function queryMysqli($sql, $select){
		$mysqli   = $this->getMysqli();
		$q        = $sql->getSqlStringForSqlObject($select);
		$result   = $mysqli->query($q);
		$res_set  = is_object($result) ? $result->fetch_all(MYSQLI_ASSOC) : array() ;
		return $res_set;
	}
}