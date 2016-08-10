<?php
namespace Application;

use Zend\Mvc\MvcEvent;

class Module
{
    public function getConfig()
    {
        $config     			= include __DIR__ . '/../config/module.config.php';
        $config['model_entity'] = include __DIR__ . '/../config/entity.config.php';
        return $config;
    }


}
