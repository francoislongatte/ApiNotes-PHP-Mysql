<?php

require_once  __DIR__ . '/../vendor/autoload.php';
require_once  __DIR__ . '/../helper/DbHandler.php';
require_once  __DIR__ . '/../helper/PassHash.php';



$app = new \Slim\App([
    'settings' => [
        'displayErrorDetails' => true,
        "allowMethods" => array('GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'),
        'determineRouteBeforeAppMiddleware' => true,
        'addContentLengthHeader' => false
    ]
]);

$container = $app->getContainer();

$container['view'] = function ($container) {
    return new \Slim\Views\PhpRenderer('../templates/');
};

require_once  __DIR__ . '/../routes/routes.php';

$app->add(function ($req, $res, $next) {
    $response = $next($req, $res);
    return $response
        ->withHeader('Access-Control-Allow-Origin', 'http')
        ->withHeader('Access-Control-Allow-Credentials', 'true')
        ->withHeader('Access-Control-Allow-Headers', 'Origin, Content-Type, Accept, Authorization')
        ->withHeader('Access-Control-Allow-Methods', 'GET,POST,PUT,DELETE,OPTIONS');
});
