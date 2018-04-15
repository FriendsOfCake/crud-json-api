Sparse Fieldsets
================

`JSON API Sparse Fieldsets <http://jsonapi.org/format/#fetching-sparse-fieldsets>`_
allow you to limit the fields returned by your API by passing the ``fields`` parameter
in your request.

To select all countries but only retrieve their `code` field:

``/countries?fields[countries]=code``

To select a single country and only retrieve its `name` field:

``/countries/1?fields[countries]=name``

Limiting Associated Data
^^^^^^^^^^^^^^^^^^^^^^^^

It is also possible to limit the fields of associated data. The following example will
return all fields for ``countries`` (the primary data) but will limit the fields returned
for ``currencies`` (the associated data) to ``id`` and ``name``.

``/countries?include=currencies&fields[currencies]=id,name``

Please note that you MUST include the associated data in the fields args, eg:

- ``/countries?fields[countries]=name&include=currencies&fields[currencies]=id,code`` will NOT work
- ``/countries?fields[countries]=name,currency&include=currencies&fields[currencies]=id,code`` does WORK

Combinations
^^^^^^^^^^^^

You may also use any combination of the above. In this case we are limiting the fields for both the primary
resource and the associated data.

``/countries/1?fields[countries]=name,currency&include=currencies&fields[currencies]=id,name``
