Common Issues
=============

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
