<?php
namespace Application\Factories;
use Zend\ServiceManager\FactoryInterface;
use Zend\ServiceManager\ServiceLocatorInterface;

class AdapterMysqliMainFactory implements FactoryInterface
{

    public function createService(ServiceLocatorInterface $serviceLocator)
    {
        $service = $serviceLocator->get('MmsService');
    	$data = $service->getAdapterData('main');
    	return new \mysqli(
				$data['hostname'],
    			$data['username'],
    			$data['password'],
    			$data['database'],
				$data['port']
		);
    }


}