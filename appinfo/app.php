<?php
// Register the background job for expiry notifications
\OC::$server->getJobList()->add(\OCA\DriverManager\BackgroundJob\ExpiryNotification::class);

// Log that the job has been registered (for debugging)
\OC::$server->getLogger()->debug('DriverManager: ExpiryNotification job registered', ['app' => 'drivermanager']);

// Register the app in navigation
\OC::$server->getNavigationManager()->add(function () {
    return [
        'id' => 'drivermanager',
        'order' => 10, 
        'href' => \OC::$server->getURLGenerator()->linkToRoute('drivermanager.page.index'),
        'icon' => \OC::$server->getURLGenerator()->imagePath('drivermanager', 'app.svg'),
        'name' => 'Driver Manager',
    ];  
});
