Creating Resources
==================

Creating a new JSON API Resource is done by calling the ``add`` action of your API with:

- the ``HTTP POST`` request type
- an ``Accept`` header  set to ``application/vnd.api+json``
- a ``Content-Type`` header  set to ``application/vnd.api+json``
- request data in valid JSON API document format

A successful request will respond with HTTP response code ``201``
and a JSON API response body presenting the newly created Resource
along with ``id``, ``attributes`` and ``belongsTo`` relationships.

Request Data
^^^^^^^^^^^^

All data posted to the listener is transformed from JSON API format to
standard CakePHP format so it can be processed "as usual" once the data
is accepted.

To make sure posted data complies with the JSON API
specification it is first validated by the listener's DocumentValidator which
will throw a (422) ValidationException if it does not comply along
with a pointer to the cause.

A valid JSON API request body for creating a new Country would look similar to:

.. code-block:: json

  {
    "data": {
      "type": "countries",
      "attributes": {
        "code": "NL",
        "name": "The Netherlands"
      }
    }
  }

The same applies when creating new resources with relationships. For example, the JSON API
request body for creating a new Country with ``currency_id=1`` would look similar to:

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

.. note::

  See this link for more examples of
  `valid JsonApiRequestBodies <https://github.com/FriendsOfCake/crud-json-api/tree/master/tests/Fixture/JsonApiRequestBodies>`_.

Side-Posting
^^^^^^^^^^^^

Side-posting is an often requested feature which would allow creating multiple resources (and/or relationships) using a single POST request.

However, this functionality is NOT supported by version 1.0 of the JSON API specification and is therefore NOT supported by crud-json-api.

Work for this feature is in progress and might land in version 1.1 of the specification, more information
`available here <https://github.com/json-api/json-api/pull/1197>`_.
