Listener Options
================

The output produced by the listener is highly configurable using the Crud
configuration options described in this section.

Either configure the options on the fly per action or enable them for all
actions in your controller by adding them to your contoller's ``initialize()`` event
like this:

.. code-block:: phpinline

  public function initialize()
  {
    parent::initialize();
    $this->Crud->config('listeners.jsonApi.withJsonApiVersion', true);
  }

withJsonApiVersion
^^^^^^^^^^^^^^^^^^

Pass this **mixed** option a boolean with value true (default: false) to
make the listener add the top-level ``jsonapi`` node with member node
``version`` to each response like shown below.

.. code-block:: json

  {
    "jsonapi": {
      "version": "1.0"
    }
  }

Passing an array or hash will achieve the same result but will also generate
the additional `meta` child node.

.. code-block:: json

  {
    "jsonapi": {
      "version": "1.0",
      "meta": {
        "cool": "stuff"
      }
    }
  }

meta
^^^^

Pass this **array** option (default: empty) an array or hash will make the listener
add the the top-level ``jsonapi`` node with member node ``meta`` to each response
like shown below.

.. code-block:: json

  {
    "jsonapi": {
      "meta": {
        "copyright": {
          "name": "FriendsOfCake"
        }
      }
    }
  }

absoluteLinks
^^^^^^^^^^^^^

Setting this **boolean** option to true (default: false) will make the listener
generate absolute links for the JSON API responses.

debugPrettyPrint
^^^^^^^^^^^^^^^^

Setting this **boolean** option to false (default: true) will make the listener
render non-pretty json in debug mode.

jsonOptions
^^^^^^^^^^^

Pass this **array** option (default: empty) an array with
`PHP Predefined JSON Constants <http://php.net/manual/en/json.constants.php>`_
to manipulate the generated json response. For example:

.. code-block:: phpinline

  public function initialize()
  {
    parent::initialize();
    $this->Crud->config('listeners.jsonApi.jsonOptions', [
      JSON_HEX_QUOT,
      JSON_UNESCAPED_UNICODE,
    ]);
  }

include
^^^^^^^

Pass this **array** option (default: empty) an array with associated entity
names to limit the data added to the json ``included`` node.

Please note that entity names:

- must be lowercased
- must be singular for entities with a belongsTo relationship
- must be plural for entities with a hasMany relationship

.. code-block:: phpinline

  $this->Crud->config('listeners.jsonApi.include', [
    'currency', // belongsTo relationship and thus singular
    'cultures' // hasMany relationship and thus plural
  ]);

.. note::

  The value of the ``include`` configuration will be overwritten if the
  the client uses the ``?include`` query parameter.

fieldSets
^^^^^^^^^

Pass this **array** option (default: empty) a hash with
field names to limit the attributes/fields shown in the
generated json. For example:

.. code-block:: phpinline

  $this->Crud->config('listeners.jsonApi.fieldSets', [
    'countries' => [ // main record
      'name'
    ],
    'currencies' => [ // associated data
      'code'
    ]
  ]);

.. note::

  Please note that there is no need to hide ``id`` fields as this
  is handled by the listener automatically as per the
  `JSON API specification <http://jsonapi.org/format/#document-resource-object-fields>`_.

docValidatorAboutLinks
^^^^^^^^^^^^^^^^^^^^^^

Setting this **boolean** option to true (default: false) will make the listener
add an ``about`` link pointing to an explanation for all validation errors caused
by posting request data in a format that does not comply with the JSON API document
structure.

This option is mainly intended to help developers understand what's wrong with their
posted data structure. An example of an about link for a validation error caused
by a missing ``type`` node in the posted data would be:

.. code-block:: json

  {
    "errors": [
      {
        "links": {
          "about": "http://jsonapi.org/format/#crud-creating"
        },
        "title": "_required",
        "detail": "Primary data does not contain member 'type'",
        "source": {
          "pointer": "/data"
        }
      }
    ]
  }

queryParameters
^^^^^^^^^^^^^^^

This **array** option allows you to specify query parameters to parse in your application.
Currently this listener supports the official ``include`` parameter. You can easily add your own
by specifying a callable.

.. code-block:: phpinline

  $this->Crud->listener('jsonApi')->config('queryParameter.parent', [
    'callable' => function ($queryData, $subject) {
      $subject->query->where('parent' => $queryData);
    }
  ]);
