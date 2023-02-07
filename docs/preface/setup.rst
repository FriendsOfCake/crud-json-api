Setup
=====

Before you can start producing JSON:API you will have to set up
your application by following the steps in this section.

Application
^^^^^^^^^^^

CakePHP needs to be told that JSON:API requests should be parsed
as JSON.

To do this, the ``BodyParserMiddleware`` must be added to your application
middleware queue, and a parser for the ``application/vnd.api+json`` mime-type
must be added.

In your ``Application`` class' ``middleware`` method, add the following.

.. code-block:: phpinline

    $bodies = new BodyParserMiddleware();
    $bodies->addParser(['application/vnd.api+json'], function ($body) {
        return json_decode($body, true);
    });

    $middlewareQueue->add($bodies);

Assuming you are using the default App Skeleton's middleware queue, change it to.

.. code-block:: phpinline

    public function middleware(MiddlewareQueue $middlewareQueue): MiddlewareQueue
    {
        $bodies = new BodyParserMiddleware();
        $bodies->addParser(['application/vnd.api+json'], function ($body) {
            return json_decode($body, true);
        });

        $middlewareQueue
            // Catch any exceptions in the lower layers,
            // and make an error page/response
            ->add(new ErrorHandlerMiddleware(Configure::read('Error')))

            // Handle plugin/theme assets like CakePHP normally does.
            ->add(new AssetMiddleware([
                'cacheTime' => Configure::read('Asset.cacheTime'),
            ]))

            // Add routing middleware.
            // If you have a large number of routes connected, turning on routes
            // caching in production could improve performance. For that when
            // creating the middleware instance specify the cache config name by
            // using it's second constructor argument:
            // `new RoutingMiddleware($this, '_cake_routes_')`
            ->add(new RoutingMiddleware($this))

            // Parse various types of encoded request bodies so that they are
            // available as array through $request->getData()
            // https://book.cakephp.org/4/en/controllers/middleware.html#body-parser-middleware
            ->add($bodies);

        return $middlewareQueue;
    }

Controller
^^^^^^^^^^

Attach the listener using the components array if you want to attach
it to all controllers, application wide, and make sure ``RequestHandler``
is loaded **before** ``Crud``.

.. code-block:: php

  class AppController extends Controller
  {
      public function initialize()
      {
          $this->loadComponent('RequestHandler');
          $this->loadComponent('Crud.Crud', [
              'actions' => [
                  'Crud.Index',
                  'Crud.View',
              ],
              'listeners' => ['CrudJsonApi.JsonApi'],
          ]);
      }
  }

Alternatively, attach the listener to your controllers ``beforeFilter``
if you prefer attaching the listener to only specific controllers on the fly.

.. code-block:: php

  class SamplesController extends AppController
  {
      public function beforeFilter(\Cake\Event\Event $event) {
          parent::beforeFilter();
          $this->Crud->addListener('CrudJsonApi.JsonApi');
      }
  }

Exception Handler
^^^^^^^^^^^^^^^^^

The JsonApi listener overrides the ``Exception.renderer`` for ``jsonapi`` requests,
so in case of an error, a standardized error will be returned,
according to the JSON API specification.

Create a custom exception renderer by extending the Crud's ``JsonApiExceptionRenderer``
class and enabling it with the ``exceptionRenderer`` configuration option.

.. code-block:: php

  class AppController extends Controller
  {
      public function initialize()
      {
          parent::initialize();
          $this->Crud->setConfig(['listeners.jsonApi.exceptionRenderer' => 'App\Error\JsonApiExceptionRenderer']);
      }
  }

.. note::

  The listener setting above is ignored when using CakePHP's PSR7 middleware feature.

If you want to use CakePHP's ``ErrorHandlerMiddleware``:

- make sure that you are using CakePHP 3.4+
- set the ``Error.exceptionRenderer`` option in ``config/app.php`` to ``'CrudJsonApi\Error\JsonApiExceptionRenderer'`` like shown below:

.. code-block:: php

    'Error' => [
        'errorLevel' => E_ALL,
        'exceptionRenderer' => 'CrudJsonApi\Error\JsonApiExceptionRenderer',
        'skipLog' => [],
        'log' => true,
        'trace' => true,
    ],

Routing
^^^^^^^

Only controllers explicitly mapped can be exposed as API resources so make sure
to configure your global routing scope in ``config/routes.php`` similar to:

.. code-block:: phpinline

  const API_RESOURCES = [
      'Countries',
      'Currencies',
  ];

  Router::scope('/', function ($routes) {
      foreach (API_RESOURCES as $apiResource) {
          $routes->resources($apiResource, [
              'inflect' => 'dasherize',
          ]);
      }
  });

Request detector
^^^^^^^^^^^^^^^^

The JsonApi Listener adds the ``jsonapi`` request detector
to your ``Request`` object which checks if the request
contains a ``HTTP Accept`` header set to ``application/vnd.api+json``
and can be used like this inside your application:

.. code-block:: php

  if ($this->request->is('jsonapi')) {
      return 'cool, using JSON API';
  }

.. note::

  To make sure the listener won't get in your way it will
  return ``null`` for all requests unless ``is('jsonapi')`` is true.
