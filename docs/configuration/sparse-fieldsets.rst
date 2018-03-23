Sparse Fieldsets
================

CrudJsonApi fully supports
`JSON API Sparse Fieldsets <http://jsonapi.org/format/#fetching-sparse-fieldsets>`_
which allows you to limit the fields returned by your API by passing the `fields` parameter
in your request.

To select all countries but only retrieve their `code` field:

``/countries?fields[countries]=code``

To select a single country and only retrieve its `name` field:

``/countries/1?fields[countries]=name`

It is also possible to limit the fields of associated data. The following example will select all countries with:

- `country` fields limited to `name` and `currency`
- associated `currencies` fields limited to `id` and `name`

``/countries?fields[countries]=name,currency&include=currencies&fields[currencies]=id,name``

Please note that you MUST add the associated model to both `include` and `fields` for this to work.
