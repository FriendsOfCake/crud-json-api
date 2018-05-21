Creating Resources
==================

Creating a new JSON API Resource is done by calling the ``add`` action of your API with:

- the ``HTTP POST`` request type
- an ``Accept`` header  set to ``application/vnd.api+json``
- a ``Content-Type`` header  set to ``application/vnd.api+json``
- request data in valid JSON API document format

A successful request will respond with HTTP response code ``201``
and response body containing the ``id`` of the newly created
record.

The response body will look similar to this output produced by
``http://example.com/countries``:

.. code-block:: json

  {
    "data": {
      "type": "countries",
      "id": "28",
      "attributes": {
        "code": "DK",
        "name": "Denmark"
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
        }
      },
      "links": {
        "self": "/countries/10"
      }
    }

Request Data
^^^^^^^^^^^^

All data posted to the listener is transformed from JSON API format to
standard CakePHP format so it can be processed "as usual" once the data
is accepted.

To make sure posted data complies with the JSON API
specification it is first validated by the listener's DocumentValidator which
will throw a (422) ValidationException if it does not comply along
with a pointer to the cause.

A valid JSON API document structure for creating a new Country
would look similar to:

.. code-block:: json

  {
    "data": {
      "type": "countries",
      "attributes": {
        "code": "NL",
        "name": "The Netherlands"
      },
      "relationships": {
        "currency": {
          "data": {
            "type": "currencies",
            "id": "1"
          }
        }
      }
    }
  }
