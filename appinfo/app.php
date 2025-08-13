<?php
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

// Instantiate the application to register services
$app = new \OCA\DriverManager\AppInfo\Application();