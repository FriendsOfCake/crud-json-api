<?php
namespace CrudJsonApi\Test\App\Config;

use Cake\Routing\Route\InflectedRoute;
use Cake\Routing\RouteBuilder;
use Cake\Routing\Router;
use CrudJsonApi\Route\JsonApiRoutes;

Router::createRouteBuilder('/')->scope('/', function (RouteBuilder $routes) {
    $routes->setRouteClass(InflectedRoute::class);
    $routes->setExtensions(['json']);

    $routes->connect('/:controller', ['action' => 'index'], ['routeClass' => 'InflectedRoute']);
    $routes->connect('/:controller/:action/*', [], ['routeClass' => 'InflectedRoute']);

    JsonApiRoutes::mapModels([
        'Countries',
        'Currencies',
        'Cultures',
    ], $routes);
});
