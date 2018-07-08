Common Issues
=============

Missing routes
^^^^^^^^^^^^^^

Crud-json-api depends on CakePHP Routing to generate the correct links for all resources
in your JSON API response. To prevent missing route errors, make sure that all (related) resources
are added to the ``API_RESOURCES`` constant found in your ``config/routes.php`` file.

Schema not registered
^^^^^^^^^^^^^^^^^^^^^

If you see the following error make sure that valid ``Table`` and ``Entity`` classes are
present for both the primary resource and all related resources.

```
Schema is not registered for a resource at path ''
```

