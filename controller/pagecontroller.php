<?php
namespace OCA\DriverManager\Controller;

use OCP\IRequest;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Controller;
use OCP\IUserSession;

class PageController extends Controller {
    private $userId;

    public function __construct($AppName, IRequest $request, IUserSession $userSession){
        parent::__construct($AppName, $request);
        $user = $userSession->getUser();
        $this->userId = $user ? $user->getUID() : null;
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function index() {
        return new TemplateResponse('drivermanager', 'index');
    }
}