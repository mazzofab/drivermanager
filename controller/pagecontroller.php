<?php
namespace OCA\DriverManager\Controller;

use OCP\IRequest;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Controller;

class PageController extends Controller {
    private $userId;

    public function __construct($AppName, IRequest $request){
        parent::__construct($AppName, $request);
        
        // Get current user
        $userSession = \OC::$server->getUserSession();
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