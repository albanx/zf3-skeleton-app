<?php
namespace Application\Model;
class Entity  {
	public function getArrayCopy($validValue = false, $removeFields = [] )
	{
		$arr = get_object_vars($this);
		if($validValue){
			$arr = array_filter($arr, 'strlen');
		}

		foreach($removeFields as $field) {
            if(isset($arr[$field])) {
                unset($arr[$field]);
            }
		}
		return $arr;
	}

	public function setValue(array $arr){
		foreach ($arr as $field=>$val){
			$this->$field = $val;
		}
	}
}