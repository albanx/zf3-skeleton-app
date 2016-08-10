<?php
namespace User;
use User\Services\UserService;

class Module
{
    public function getConfig()
    {
        return include __DIR__ . '/../config/module.config.php';
    }

    public function getServiceConfig()
    {
        return array(
            'factories' => array(
                'UserService' => function ($sl) {
                    $srv = new UserService();
                    $srv->setServiceLocator($sl);
                    return $srv;
                }
            )
        );
    }
}
