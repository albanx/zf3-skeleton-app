<?php
namespace Auth\Services;

use Application\Model\Entity\User;
use Application\Services\BaseService;
use Application\Utils\Utils;
use Firebase\JWT\JWT;

class AuthService extends BaseService {


    private $isAuthenticated = false;
    private $decodedToken = null;

    public function __construct( )
    {
    }

    public function hasIdentity() {
        return $this->isAuthenticated;
    }

    public function getUserId() {
        if($this->decodedToken) {
            return $this->decodedToken->data->userId;
        }
        return null;
    }

    /**
     * @param $data
     * @param int $remember
     * @return null|string
     */
    public function authenticate($data, $remember = 0) { //TODO remember me
        $token = null;

        if(isset($data['method'])) {
            switch ($data['method']) {
                case 'token':
                    $token = $this->tokenAuth($data['token']);
                    break;
                case 'social':
                    $token = $this->socialAuth($data['social']);
                    break;
                default:
                    $token = $this->classicAuth($data['email'], $data['password']);
            }

            if ($token) {
                $this->isAuthenticated = true;
            }
        }
        return $token;
    }

    /**
     * @param User $user the current user DB record
     * @return int the DB login ID
     */
    private function loginLog(User $user) {
        $table      = $this->getTable('LoginLog');
        $login_id   = $table->insert(array(
            'user_id' 	=> $user->id,
            'username'	=> $user->email,
            'client_ip' => Utils::getUserIp(),
            'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : null
        ));
        return $login_id;
    }

    public function createUser() {
        $this->getTable('User')->insert([
            'email' => 'test@gmail.com',
            'password' => password_hash('test123', PASSWORD_BCRYPT),
            'name' => 'test',
            'surname' => 'boffy',
            'address1' => 'somewhere',
            'city' => 'somecity',
            'postal_code' => '18',
            'phone' => '09999999',

        ]);
    }



    /**
     * Return the current Authenticated user using the token information
     * @return User
     */
    public function getUser() {
        if($this->decodedToken) {
            $userTable = $this->getTable('User');
            $user = $userTable->find($this->getUserId());

            //verify password
            if ($user) {
                return $user->getArrayCopy(false, ['password']);
            }
        }

        return null;
    }

    private function classicAuth($username, $password) {
        if (filter_var($username, FILTER_VALIDATE_EMAIL)) {
            $userTable = $this->getTable('User');
            $user = $userTable->findOneBy(array(
                'email' => $username
            ));

            //verify password
            if(password_verify($password, $user->password)) {
                $this->loginLog($user);
                return $this->generateJWT($user);
            }
        }
        return false;
    }

    private function tokenAuth($tokenJWT) {
        $authConfig = $this->getConfig('authConfig'); // Retrieve the server name from config file
        $secretKey  = $authConfig['jwtKey'];
        $jwtAlg     = $authConfig['jwtAlg'];
        try {
            $this->decodedToken = JWT::decode($tokenJWT, $secretKey, [$jwtAlg]);
            return $tokenJWT;
        } catch(\Exception $e) {
            return false;
        }
    }

    private function socialAuth($provider) {

    }

    /**
     * @param User $user
     * @return string
     */
    private function generateJWT(User $user) {
        $authConfig = $this->getConfig('authConfig'); // Retrieve the server name from config file
        $tokenId    = base64_encode(mcrypt_create_iv(32));
        $issuedAt   = time();
        $notBefore  = $issuedAt;
        $expire     = $notBefore + $authConfig['tokenValidity'];

        $secretKey  = $authConfig['jwtKey'];
        $serverName = $authConfig['serverName'];
        $jwtAlg     = $authConfig['jwtAlg'];

        /*
         * Create the token as an array
         */
        $data = [
            'iat'  => $issuedAt,            // Issued at: time when the token was generated
            'jti'  => $tokenId,             // Json Token Id: an unique identifier for the token
            'iss'  => $serverName,          // Issuer
            'nbf'  => $notBefore,           // Not before
            'exp'  => $expire,              // Expire
            'data' => [                     // Data related to the signer user
                'userId'   => $user->id,    // userId from the users table
                'userName' => $user->email, // User name
            ]
        ];

        $this->decodedToken = (object) $data;
        $jwt = JWT::encode($this->decodedToken, $secretKey, $jwtAlg);
        return $jwt;
    }
}