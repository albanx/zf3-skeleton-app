<?php

namespace User\Services;
use Application\Services\BaseService;
use Auth\Services\AuthService;

class UserService extends BaseService {

    public function __construct( )
    {
    }

    public function getUserInfo() {
        /** @var AuthService $auth */
        $auth = $this->sm->get('AuthService');

        if($auth->hasIdentity()) {
            $userId = $auth->getUserId();
            $user = $this->getTable('User')->find($userId);
            return $user->getArrayCopy(false, ['password']);
        }

        return [];
    }
}