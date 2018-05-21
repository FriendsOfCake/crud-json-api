Fetching Resources
==================

Fetching a single JSON API Resource is done by calling the ``view`` action of your API with:

- the ``HTTP GET`` request type
- an ``Accept`` header  set to ``application/vnd.api+json``

A successful request will respond with HTTP response code ``200``
and response body similar to this output produced by
````http://example.com/countries/1``:

.. code-block:: json

  {
    "data": {
      "type": "countries",
      "id": "1",
      "attributes": {
        "code": "NL",
        "name": "The Netherlands"
      },
      "links": {
        "self": "/countries/1"
      }
    }
  }
