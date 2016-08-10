<?php
namespace Application\Model\Repository;
use Application\Model\BaseTable;

class UserTable extends BaseTable
{
	public function find($id)
	{
		$rowset = $this->tableGateway->select(array('id' => $id));
		$row = $rowset->current();
		return $row;
	}

	public function delete($id)
	{
		$this->tableGateway->delete(array('id' => $id));
	}
}