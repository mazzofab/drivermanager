<?php

declare(strict_types=1);

namespace OCA\DriverManager\Controller;

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IRequest;
use OCP\IUserSession;

class PageController extends Controller {
    private IUserSession $userSession;
    private ?string $userId;

    public function __construct(
        IRequest $request,
        IUserSession $userSession
    ) {
        parent::__construct('drivermanager', $request);
        $this->userSession = $userSession;
        
        $user = $this->userSession->getUser();
        $this->userId = $user ? $user->getUID() : null;
    }

    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function index(): TemplateResponse {
        return new TemplateResponse('drivermanager', 'index');
    }
}
