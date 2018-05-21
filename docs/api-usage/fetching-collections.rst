Fetching Collections
====================

Fetching JSON API Resource Collections is done by calling the ``index`` action of your API with:

- the ``HTTP GET`` request type
- an ``Accept`` header  set to ``application/vnd.api+json``

A successful request will respond with HTTP response code ``200``
and response body similar to this output produced by
``http://example.com/countries``:

.. code-block:: json

  {
    "data": [
      {
        "type": "countries",
        "id": "1",
        "attributes": {
          "code": "NL",
          "name": "The Netherlands"
        },
        "links": {
          "self": "/countries/1"
        }
      },
      {
        "type": "countries",
        "id": "2",
        "attributes": {
          "code": "BE",
          "name": "Belgium"
        },
        "links": {
          "self": "/countries/2"
        }
      }
    ]
  }
