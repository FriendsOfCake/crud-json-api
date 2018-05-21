Pagination
==========

This listener comes with an additional `Pagination` listener that, once enabled,
wil add the ``meta`` and ``links`` nodes as per the JSON API specification.

Attach the listener using the components array if you want to attach
it to all controllers, application wide.

.. code-block:: php

  <?php
  class AppController extends Controller {

    public function initialize()
    {
      $this->loadComponent('RequestHandler');
      $this->loadComponent('Crud.Crud', [
        'actions' => [
          'Crud.Index',
          'Crud.View'
        ],
        'listeners' => [
          'CrudJsonApi.JsonApi',
          'CrudJsonApi.Pagination',
        ]
      ]);
    }

Alternatively, attach the listener to your controllers ``beforeFilter``
if you prefer attaching the listener to only specific controllers on the fly.

.. code-block:: php

  <?php
  class SamplesController extends AppController {

    public function beforeFilter(\Cake\Event\Event $event) {
      parent::beforeFilter();
      $this->Crud->addListener('CrudJsonApi.Pagination');
    }
  }

All ``GET`` requests to the index action will now add
JSON API pagination information to the response as shown below.

.. code-block:: json

  {
    "meta": {
      "record_count": 15,
      "page_count": 2,
      "page_limit": null
    },
    "links": {
      "self": "/countries?page=2",
      "first": "/countries?page=1",
      "last": "/countries?page=2",
      "prev": "/countries?page=1",
      "next": null
    }
  }
