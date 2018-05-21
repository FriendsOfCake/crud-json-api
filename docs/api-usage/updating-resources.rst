
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
