<?php
namespace OCA\DriverManager\AppInfo;

use OCP\AppFramework\App;

class Application extends App {
    public function __construct(array $urlParams = []) {
        parent::__construct('drivermanager', $urlParams);
        
        // Register the background job
        $container = $this->getContainer();
        $server = $container->getServer();
        $jobList = $server->getJobList();
        
        // Add the background job to the queue
        $jobList->add(\OCA\DriverManager\BackgroundJob\ExpiryNotification::class);
        
        // Log registration for debugging
        $server->getLogger()->debug('DriverManager: ExpiryNotification job registered', ['app' => 'drivermanager']);
    }
}
