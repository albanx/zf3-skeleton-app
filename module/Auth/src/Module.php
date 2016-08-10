<?php
namespace Auth;
use Auth\Services\AuthService;
use Zend\Mvc\MvcEvent;

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
                'AuthService' => function ($sl) {
                    $authService = new AuthService();
                    $authService->setServiceLocator($sl);
                    return $authService;
                }
            )
        );
    }

    public function onBootstrap(MvcEvent $e)
    {
        $app    = $e->getApplication();
        $em     = $app->getEventManager();
        $sm     = $app->getServiceManager();

        /** @var $auth AuthService */
        $auth   = $sm->get('AuthService');
        $em->attach(MvcEvent::EVENT_ROUTE, function($e) use ($auth, $sm) {
            $match      = $e->getRouteMatch();
            $request    = $e->getRequest();

            header("Access-Control-Allow-Credentials: true");
            header('Access-Control-Allow-Origin: http://localhost:4200');//FIXME just for test purpose
            header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
            header("Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept, Authorization");
            if($request->getMethod() === 'OPTIONS') {
                header( "HTTP/1.1 200 OK" );
                exit();
            }

            $authHeader = $request->getHeader('authorization');
            if ($authHeader) {
                //Extract the jwt from the Bearer and authenticate user
                list($jwt) = sscanf($authHeader->toString(), 'Authorization: Bearer %s');
                $auth->authenticate(['method'=>'token', 'token' => $jwt]);
            }
            $protectedRoutes = $sm->get('Config')['protectedRoutes'];

            // Route is protected TODO verify auth
            $name = $match->getMatchedRouteName();
            if (in_array($name, $protectedRoutes) && !$auth->hasIdentity()) {
                header('HTTP/1.0 401 Unauthorized');
                die();
            }

//            if( $e->getRequest()->isXmlHttpRequest() ){
//                return new JsonModel( array('success' => false, 'message' => 'Access forbiden', 'redirect' => $redirectTo) );
//            }

        }, -100);
    }
}
