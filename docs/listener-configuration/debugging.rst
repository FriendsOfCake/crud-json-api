Query Logs
==========

This listener fully supports the Crud ``API Query Log`` listener and will,
once enabled as `described here <https://crud.readthedocs.io/en/latest/listeners/api-query-log.html#setup>`_
, add a top-level ``query`` node to every response when debug mode is enabled.

.. code-block:: json

  {
    "query": {
      "default": [
        {
          "query": "SHOW FULL COLUMNS FROM `countries`",
          "took": 0,
          "params": [],
          "numRows": 10,
          "error": null
        }
    }
  }
