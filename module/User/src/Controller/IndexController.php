<?php
namespace User\Controller;
use User\Services\UserService;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\JsonModel;

class IndexController extends AbstractActionController {
    protected $storage;
    protected $userService;

    public function __construct(UserService $userService)
    {
        $this->userService = $userService;
    }

    public function infoAction() {
        return new JsonModel([
            'success' => true,
            'data' => $this->userService->getUserInfo()
        ]);
    }
}