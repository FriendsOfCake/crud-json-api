Common Issues
=============

Missing template
^^^^^^^^^^^^^^^^

Crud-json-api does not require you to create templates so if you see the following error you are
most likely not sending the correct ``application/vnd.api+json`` Accept Header with your requests:

.. code-block:: php

  Error: Missing Template

Missing routes
^^^^^^^^^^^^^^

Crud-json-api depends on CakePHP Routing to generate the correct links for all resources
in your JSON API response.

If you encounter errors like the one shown below, make sure that both your primary resource and all related
resources are added to the ``API_RESOURCES`` constant found in your ``config/routes.php`` file.

.. code-block:: php

  A route matching '' could not be found.

Schema not registered
^^^^^^^^^^^^^^^^^^^^^

If you see the following error make sure that valid ``Table`` and ``Entity`` classes are
present for both the primary resource and all related resources.

.. code-block:: php

  Schema is not registered for a resource at path ''.

Normal HTML is returned
^^^^^^^^^^^^^^^^^^^^^^^

If you are just getting back a standard page response, rather than a JSON response (and you have confirmed that you are sending the correct JSON API Request Headers) it is most likely because you already have a controller action defined for the resource you are trying to request. For CRUD to handle it, you must remove any existing controller actions that conflict with the routes you are trying to configure.
