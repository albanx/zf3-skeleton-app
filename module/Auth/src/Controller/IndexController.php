<?php
namespace Auth\Controller;
use Auth\Services\AuthService;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\JsonModel;
use Application\Model\Entity\User;

class IndexController extends AbstractActionController {
    protected $storage;
    protected $authService;

    public function __construct(AuthService $authService)
    {
        $this->authService = $authService;
    }

    /**
     * Check the authentication
     * @return JsonModel
     */
    public function indexAction() {
        $request  	= $this->getRequest();
        if ( $request->isPost() ) {
            $authData = json_decode($request->getContent(), true);
            $tokenJWT = $this->authService->authenticate($authData, 0);
            if ( $tokenJWT ) {
                return new JsonModel( ['success' => true, 'data' => [ 'token' => $tokenJWT ] ]);
            }
        }
        header('HTTP/1.0 401 Unauthorized');
        exit();
    }

    /**
     * Just to create user for demo purpose
     */
    public function createUserAction() {
        $this->authService->createUser();
        die();
    }
}