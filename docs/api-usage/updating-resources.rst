
Updating Resources
==================

Updating an existing JSON API Resource is done by calling the ``edit`` action of your API with:

- the ``HTTP PATCH`` request type
- an ``Accept`` header  set to ``application/vnd.api+json``
- a ``Content-Type`` header  set to ``application/vnd.api+json``
- request data in valid JSON API document format
- request data containing the ``id`` of the resource to update

A successful request will respond with HTTP response code ``200``
and response body similar to the one produced by the ``view`` action.

A valid JSON API document structure for updating the ``name`` field
for a Country with ``id`` 10 would look similar to the following output
produced by ``http://example.com/countries/1``:

.. code-block:: json

  {
    "data": {
      "type": "countries",
      "id": "10",
      "attributes": {
        "name": "My new name"
      }
    }
  }

Updating To-One Relationships
^^^^^^^^^^^^^^^^^^^^^^^^^^^^^

When updating a primary JSON API Resource, you can use the same PATCH request to set one or multiple To-One
(or ``belongsTo``) relationships but only as long as the following conditions are met:

- the ``id`` of the related resource MUST correspond with an EXISTING foreign key
- the related resource MUST belong to the primary resource being PATCHed

For example, a valid JSON API document structure that would set a single related
``national-capital`` for a given ``country`` would look like:

.. code-block:: json

  {
    "data": {
      "type": "countries",
      "id": "2",
      "relationships": {
        "national-capital": {
          "data": {
            "type": "national-capitals",
            "id": "4"
          }
        }
      }
    }
  }

.. note::

  Please note that JSON API does not support updating attributes for the related resource(s) and thus
  will simply ignore them if detected in the request body.

Updating To-Many Relationships
^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^

When updating a primary JSON API Resource, you can use the same PATCH request to set one or multiple To-Many
(or ``hasMany``) relationships but only as long as the following conditions are met:

- the ``id`` of the related resource MUST correspond with an EXISTING foreign key
- the related resource MUST belong to the primary resource being PATCHed

For example, a valid JSON API document structure that would set multiple related ``cultures``
for a given ``country`` would look like:

.. code-block:: json

  {
    "data": {
      "type": "countries",
      "id": "2",
      "relationships": {
        "cultures": {
          "data": [
            {
              "type": "cultures",
              "id": "2"
            },
            {
              "type": "cultures",
              "id": "3"
            }
          ]
        }
      }
    }
  }

.. note::

  Please note that JSON API does not support updating attributes for the related resource(s) and thus
  will simply ignore them if detected in the request body.
