<?php
namespace Application\Model\Entity;
use Application\Model\Entity;

class User extends Entity{

	const DB_TYPE = 'main';
	const DB_TABLE = 'user';
	public $id;
	public $name;
	public $surname;
	public $address1;
	public $address2;
	public $postal_code;
	public $city;
	public $province;
	public $country;
	public $phone;
	public $email;
	public $date_birth;
	public $gender;
	public $password;
	public $date_creation;
	public $date_update;
	public $document_type;
	public $document_number;
	public $document_image;
	public $active;


	public function exchangeArray($data)
	{
		$this->id = isset($data['id']) ? $data['id'] : null;
		$this->name = isset($data['name']) ? $data['name'] : null;
		$this->surname = isset($data['surname']) ? $data['surname'] : null;
		$this->address1 = isset($data['address1']) ? $data['address1'] : null;
		$this->address2 = isset($data['address2']) ? $data['address2'] : null;
		$this->postal_code = isset($data['postal_code']) ? $data['postal_code'] : null;
		$this->city = isset($data['city']) ? $data['city'] : null;
		$this->province = isset($data['province']) ? $data['province'] : null;
		$this->country = isset($data['country']) ? $data['country'] : null;
		$this->phone = isset($data['phone']) ? $data['phone'] : null;
		$this->email = isset($data['email']) ? $data['email'] : null;
		$this->date_birth = isset($data['date_birth']) ? $data['date_birth'] : null;
		$this->gender = isset($data['gender']) ? $data['gender'] : null;
		$this->password = isset($data['password']) ? $data['password'] : null;
		$this->date_creation = isset($data['date_creation']) ? $data['date_creation'] : null;
		$this->date_update = isset($data['date_update']) ? $data['date_update'] : '0000-00-00 00:00:00';
		$this->document_type = isset($data['document_type']) ? $data['document_type'] : null;
		$this->document_number = isset($data['document_number']) ? $data['document_number'] : null;
		$this->document_image = isset($data['document_image']) ? $data['document_image'] : null;
		$this->active = isset($data['active']) ? $data['active'] : '0';

	}
}
