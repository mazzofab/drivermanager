<?php

return [
    'routes' => [
        ['name' => 'page#index', 'url' => '/', 'verb' => 'GET'],
        ['name' => 'driver#index', 'url' => '/api/drivers', 'verb' => 'GET'],
        ['name' => 'driver#create', 'url' => '/api/drivers', 'verb' => 'POST'],
        ['name' => 'driver#update', 'url' => '/api/drivers/{id}', 'verb' => 'PUT'],
        ['name' => 'driver#destroy', 'url' => '/api/drivers/{id}', 'verb' => 'DELETE'],
    ]
];
