Errors and Exceptions
=====================

Default Errors
^^^^^^^^^^^^^^

The listener will produce error responses in the following
JSON API format for all standard errors and all non-validation
exceptions:

.. code-block:: json

  {
    "errors": [
      {
        "code": "501",
        "title": "Not Implemented"
      }
    ],
    "debug": {
      "class": "Cake\\Network\\Exception\\NotImplementedException",
      "trace": []
    }
  }

.. note::

  Please note that the ``debug`` node with the stack trace will only be included if ``debug`` is true.

Validation Errors
^^^^^^^^^^^^^^^^^

The listener will produce validation error (422) responses
in the following JSON API format for all validation errors:

.. code-block:: json

  {
    "errors": [
      {
        "title": "_required",
        "detail": "Primary data does not contain member 'type'",
        "source": {
          "pointer": "/data"
        }
      }
    ]
  }

Invalid Request Data
^^^^^^^^^^^^^^^^^^^^

Please be aware that the listener will also respond with (422) validation errors
if request data is posted in a structure that does not comply with the
JSON API specification.
