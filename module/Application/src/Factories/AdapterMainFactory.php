<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/zf2 for the canonical source repository
 * @copyright Copyright (c) 2005-2013 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

namespace Application\Factories;
use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\AbstractFactoryInterface;

class AdapterMainFactory implements AbstractFactoryInterface
{
    public function canCreate(ContainerInterface $container, $requestedName)
    {
        return class_exists($requestedName);
    }

    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $config = $container->get('Config');
        $dbParams = $config['db_params'];

        return new \Zend\Db\Adapter\Adapter(array(
            'driver'    => 'Mysqli',
            'database'  => $dbParams['prefix'].'_main',
            'username'  => $dbParams['username'],
            'password'  => $dbParams['password'],
            'hostname'  => $dbParams['hostname'],
            'options'   => array('buffer_results' => true),
            'charset'   => 'utf8'
        ));
//        return new $requestedName();
    }

//	public function createService(ServiceLocatorInterface $serviceLocator)
//    {
//        $config = $serviceLocator->get('config');
//    	$dbParams = $config['db_params'];
//
//    	return new \Zend\Db\Adapter\Adapter(array(
//    			'driver'    => 'Mysqli',
//    			'database'  => $dbParams['prefix'].'_main',
//    			'username'  => $dbParams['username'],
//    			'password'  => $dbParams['password'],
//    			'hostname'  => $dbParams['hostname'],
//    			'options'   => array('buffer_results' => true),
//    			'charset'   => 'utf8'
//    	));
//    }
    
    
}
