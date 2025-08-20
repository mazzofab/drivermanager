<?php

declare(strict_types=1);

namespace OCA\DriverManager\AppInfo;

use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use Psr\Log\LoggerInterface;

class Application extends App implements IBootstrap {
    public const APP_ID = 'drivermanager';

    public function __construct(array $urlParams = []) {
        parent::__construct(self::APP_ID, $urlParams);
    }

    public function register(IRegistrationContext $context): void {
        // Register services, event listeners, middleware, etc.
    }

    public function boot(IBootContext $context): void {
        $context->injectFn(function () {
            // Register the background job
            $jobList = \OC::$server->getJobList();
            $jobList->add(\OCA\DriverManager\BackgroundJob\ExpiryNotification::class);
            
            // Log registration for debugging - using the new PSR-3 logger
            $logger = \OC::$server->get(LoggerInterface::class);
            $logger->debug(
                'DriverManager: ExpiryNotification job registered',
                ['app' => self::APP_ID]
            );
        });
    }
}