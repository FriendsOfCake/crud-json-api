<?php
namespace CrudJsonApi\Test\App;

use Cake\Http\BaseApplication;
use Cake\Routing\Middleware\AssetMiddleware;
use Cake\Routing\Middleware\RoutingMiddleware;

class Application extends BaseApplication
{

    public function middleware($middlewareQueue)
    {
        $middlewareQueue
            // Handle plugin/theme assets like CakePHP normally does.
            ->add(AssetMiddleware::class)

            ->add($this->fixBase())

            // Add routing middleware.
            ->add(new RoutingMiddleware($this));

        return $middlewareQueue;
    }

    public function fixBase()
    {
        return function ($request, $response, $next) {

            if ($request->getAttribute('base')) {
                $request = $request->withAttribute('base', '');
            }

            $response = $next($request, $response);

            return $response;
        };
    }
}
