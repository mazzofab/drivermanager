<?php

declare(strict_types=1);

namespace OCA\DriverManager\AppInfo;

use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCA\DriverManager\Notification\Notifier;

class Application extends App implements IBootstrap {
    public const APP_ID = 'drivermanager';

    public function __construct(array $urlParams = []) {
        parent::__construct(self::APP_ID, $urlParams);
    }

    public function register(IRegistrationContext $context): void {
        $context->registerNotifierService(Notifier::class);
        $context->registerBackgroundJob(\OCA\DriverManager\BackgroundJob\ExpiryNotification::class);
    }

    public function boot(IBootContext $context): void {
    }
}
