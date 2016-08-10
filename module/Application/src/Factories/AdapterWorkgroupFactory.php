<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/zf2 for the canonical source repository
 * @copyright Copyright (c) 2005-2013 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

namespace Application\Factories;
use Zend\ServiceManager\FactoryInterface;
use Zend\ServiceManager\ServiceLocatorInterface;

class AdapterWorkgroupFactory implements FactoryInterface
{

    public function createService(ServiceLocatorInterface $serviceLocator)
    {
        $workgrop_session  = new \Zend\Session\Container('Workgroup');
    	$id                = $workgrop_session->workgroup_id;

    	if(!$id) {
    	    die('Cannot call query, missing workgroup id in session');
    		throw new \Exception('Cannot call query, missing workgroup id in session');
    	}
    	$config        = $serviceLocator->get('config');
    	$dbParams      = $config['db_params'];

    	$auth = $serviceLocator->get('PsAuthService');
    	$user = $auth->getStorage()->read();
    	if($user)
    	{
            if($user->superuser)
            {
                $dbParams      = isset($config['db_params_root']) ? $config['db_params_root'] : $config['db_params'];
//                 var_dump($id);
            }
    	}

    	return new \Zend\Db\Adapter\Adapter(array(
    			'driver'    => 'Mysqli',
    			'database'  => $dbParams['prefix'].'_'.$id,
    			'username'  => $dbParams['username'],
    			'password'  => $dbParams['password'],
    			'hostname'  => $dbParams['hostname'],
    			'options'   => array('buffer_results' => true),
    			'charset'   => 'utf8'
    	));
    }


}
