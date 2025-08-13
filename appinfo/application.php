<?php
namespace OCA\DriverManager\AppInfo;

use OCP\AppFramework\App;
use OCP\AppFramework\IAppContainer;

class Application extends App {
    public function __construct(array $urlParams = []) {
        parent::__construct('drivermanager', $urlParams);
        
        $container = $this->getContainer();
        
        // Register services if needed
        $container->registerService('UserId', function(IAppContainer $c) {
            return \OCP\User::getUser();
        });
    }
}