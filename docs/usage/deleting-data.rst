Deleting Data
=============

HTTP DELETE (delete)
^^^^^^^^^^^^^^^^^^^^

All requests to the ``delete`` action **must** use:

- the ``HTTP DELETE`` request type
- an ``Accept`` header  set to ``application/vnd.api+json``
- a ``Content-Type`` header  set to ``application/vnd.api+json``
- request data in valid JSON API document format
- request data containing the ``id`` of the resource to delete

A successful request will return HTTP response code ``204`` (No Content)
and empty response body. Failed requests will return HTTP response
code ``400`` with empty response body.

An valid JSON API document structure for deleting a Country
with ``id`` 10 would look similar to:

.. code-block:: json

  {
    "data": {
      "type": "countries",
      "id": "10"
      }
    }
  }
