Schemas
=======

This listener makes use of `NeoMerx schemas <https://github.com/neomerx/json-api/wiki/Schemas>`_
to handle the heavy lifting that is required for converting CakePHP entities to JSON API format.

By default all entities in the ``_entities`` viewVar will be passed to the
Listener's ``DynamicEntitySchema`` for conversion. This dynamic schema extends
``Neomerx\JsonApi\Schema\SchemaProvider`` and is, amongst other things, used to
override NeoMerx methods so we can generate CakePHP specific output (like links).

Even though the dynamic entity schema provided by Crud should cater to the
needs of most users, creating your own custom schemas is also supported. When
using custom schemas please note that the listener will use the first matching
schema, following this order:

1. Custom entity schema
2. Custom dynamic schema
3. Crud's dynamic schema

Custom entity schema
^^^^^^^^^^^^^^^^^^^^

Use a custom entity schema in situations where you need to alter the
generated JSON API but only for a specific controller/entity.

An example would be overriding the NeoMerx ``getSelfSubUrl`` method used
to prefix all ``self`` links in the generated json for a ``Countries``
controller. This would require creating a ``src/Schema/JsonApi/CountrySchema.php``
file looking similar to:

.. code-block:: phpinline

  <?php
  namespace App\Schema\JsonApi;

  use CrudJsonApi\Schema\JsonApi\DynamicEntitySchema;

  class CountrySchema extends DynamicEntitySchema
  {
    public function getSelfSubUrl($entity = null)
    {
      return 'http://prefix.only/countries/controller/self-links/';
    }
  }

Custom dynamic schema
^^^^^^^^^^^^^^^^^^^^^

Use a custom dynamic schema if you need to alter the generated JSON API for all
controllers, application wide.

An example of a custom dynamic schema would require creating
a ``src/Schema/JsonApi/DynamicEntitySchema.php`` file looking similar to:

.. code-block:: phpinline

  <?php
  namespace App\Schema\JsonApi;

  use CrudJsonApi\Schema\JsonApi\DynamicEntitySchema as CrudDynamicEntitySchema;

  class DynamicEntitySchema extends CrudDynamicEntitySchema
  {
    public function getSelfSubUrl($entity = null)
    {
      return 'http://prefix.all/controller/self-links/';
    }
  }
