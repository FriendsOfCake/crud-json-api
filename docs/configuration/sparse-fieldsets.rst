Sparse Fieldsets
================

CrudJsonApi fully supports
`JSON API Sparse Fieldsets <http://jsonapi.org/format/#fetching-sparse-fieldsets>`_
which allows you to limit the fields returned by your API by passing the ``fields`` parameter
in your request.

To select all countries but only retrieve their `code` field:

``/countries?fields[countries]=code``

To select a single country and only retrieve its `name` field:

``/countries/1?fields[countries]=name`

It is also possible to limit the fields of associated data. The following example will
show all fields for ``countries`` but will limit the fields shown for associated ``currencies``
to ``id`` and ``name``.

@rchavik: TEST NEEDED? SIMPLE CASE BUT SEEMS MISSING

``/countries?include=currencies&fields[currencies]=id,name``

Combinations are also possible. In this case we are limiting the fields for both the primary
resource and the included data.

``/countries/1?fields[countries]=name,currency&include=currencies&fields[currencies]=id,name``
