Customizing Output
==================

Date Formats
^^^^^^^^^^^^

By default crud-json-api will return timestamps in the following format:

.. code-block:: json

  "created-at": "2018-06-10T13:41:05+00:00"

If you prefer a different format, either specify it in your ``bootstrap.php`` file or right before a
specific action. E.g.

.. code-block:: php

  \Cake\I18n\FrozenTime::setJsonEncodeFormat("yyyy-MM-dd'T'HH:mm:ss'Z'");
  \Cake\I18n\FrozenDate::setJsonEncodeFormat('yyyy-MM-dd');
