Fetching Resources
==================

Fetch a single JSON API Resource by calling the ``view`` action of your API with:

- the ``HTTP GET`` request type
- an ``Accept`` header  set to ``application/vnd.api+json``

A successful request will respond with HTTP response code ``200``
and response body similar to this output produced by
``http://example.com/countries/1``:

.. code-block:: json

  {
    "data": {
      "type": "countries",
      "id": "1",
      "attributes": {
        "code": "NL",
        "name": "The Netherlands",
        "dummy-counter": 11111
      },
      "relationships": {
        "currency": {
          "data": {
            "type": "currencies",
            "id": "1"
          },
          "links": {
            "self": "/currencies/1"
          }
        },
        "national-capital": {
          "data": {
            "type": "national-capitals",
            "id": "1"
          },
          "links": {
            "self": "/national-capitals/1"
          }
        }
      },
      "links": {
        "self": "/countries/1"
      }
    }
  }

.. note::

  When retrieving a single Resource, crud-json-api will automatically generate ``relationships`` links for
  all ``belongsTo`` attributes in your model UNLESS you pass the ``include`` request parameter OR define
  a contain statement inside your Controller.
