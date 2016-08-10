<?php
namespace Application\Model\Entity;
use Application\Model\Entity;

class LoginLog extends Entity{

	const DB_TYPE = 'main';
	const DB_TABLE = 'login_log';
	public $id;
	public $user_id;
	public $username;
	public $date;
	public $server;
	public $client_ip;
	public $user_agent;
	public $token;


	public function exchangeArray($data)
	{
		$this->id = isset($data['id']) ? $data['id'] : null;
		$this->user_id = isset($data['user_id']) ? $data['user_id'] : null;
		$this->username = isset($data['username']) ? $data['username'] : null;
		$this->date = isset($data['date']) ? $data['date'] : null;
		$this->server = isset($data['server']) ? $data['server'] : null;
		$this->client_ip = isset($data['client_ip']) ? $data['client_ip'] : null;
		$this->user_agent = isset($data['user_agent']) ? $data['user_agent'] : null;
		$this->token = isset($data['token']) ? $data['token'] : null;

	}
}
