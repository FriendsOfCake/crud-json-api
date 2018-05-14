<?php
namespace CrudJsonApi\Test\App\Config;

use Cake\Core\Configure;
use Cake\Core\Plugin;
use Cake\Routing\RouteBuilder;
use Cake\Routing\Router;

Router::scope('/', function ($routes) {
    $routes->setExtensions(['json']);

    $routes->connect('/:controller', ['action' => 'index'], ['routeClass' => 'InflectedRoute']);
    $routes->connect('/:controller/:action/*', [], ['routeClass' => 'InflectedRoute']);

    $routes->resources('Countries', function (RouteBuilder $routes) {
        $routes->connect(
            '/relationships/:type',
            [
                'controller' => 'Currencies',
                '_method' => 'GET',
                'action' => 'view',
                'from' => 'Countries',
            ]
        );

        return $routes;
    });
    $routes->resources('Currencies');
    $routes->resources('Cultures');
});
