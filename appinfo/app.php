<?php
use OCP\App;

App::registerRoutes('drivermanager', [
    'routes' => [
        ['name' => 'page#index', 'url' => '/', 'verb' => 'GET'],
        ['name' => 'driver#create', 'url' => '/drivers', 'verb' => 'POST'],
        ['name' => 'driver#update', 'url' => '/drivers/{id}', 'verb' => 'PUT'],
        ['name' => 'driver#destroy', 'url' => '/drivers/{id}', 'verb' => 'DELETE'],
        ['name' => 'driver#index', 'url' => '/drivers', 'verb' => 'GET'],
    ]
]);

$app = new \OCA\DriverManager\AppInfo\Application();
$app->register();