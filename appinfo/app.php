<?php
// Register the background job for expiry notifications directly
\OC::$server->getJobList()->add(\OCA\DriverManager\BackgroundJob\ExpiryNotification::class);

// Log that the job has been registered (for debugging)
\OC::$server->getLogger()->debug('DriverManager: ExpiryNotification job registered from app.php', ['app' => 'drivermanager']);

// Register the app in navigation
\OC::$server->getNavigationManager()->add(function () {
    $urlGenerator = \OC::$server->getURLGenerator();
    
    // Debug: Log the icon path
    $iconPath = $urlGenerator->imagePath('drivermanager', 'app.svg');
    \OC::$server->getLogger()->debug('DriverManager icon path: ' . $iconPath, ['app' => 'drivermanager']);
    
    return [
        'id' => 'drivermanager',
        'order' => 10, 
        'href' => $urlGenerator->linkToRoute('drivermanager.page.index'),
        'icon' => $iconPath,
        'name' => 'Driver Manager',
    ];  
});
