Sorting
=======

`JSON API Sorting <http://jsonapi.org/format/#fetching-sorting>`_
allows you to sort the results produced by your API according to one
by passing one or more criteria to your request using the ``sort`` parameter.

Before continuing please note that the default sort order for each field is ascending
UNLESS the field is prefixed with a hyphen (``-``) in which case the sort order will
be descending.

Single Field Sorting
^^^^^^^^^^^^^^^^^^^^

To sort by a single field using ascending order:

``/currencies?sort=code``

To sort descending:

``/currencies?sort=-code``

Multi Field Sorting
^^^^^^^^^^^^^^^^^^^

To sort by multiple fields simply pass comma-separated sort fields
in the order you want them applied:

- ``/currencies?sort=code,name``
- ``/currencies?sort=-code,name``
- ``/currencies?sort=-code,-name``
- ``/currencies?sort=name,code``

Sorting By Related Data
^^^^^^^^^^^^^^^^^^^^^^^

You can also sort your primary data using fields in the related data. In this case
all ``currencies`` (the primary data) would be sorted using the ascending order of the
``code`` field in ``countries`` (the associated data).

- ``/currencies?include=countries&sort=countries.code``
- ``/currencies?include=countries&sort=-countries.code``

Combined Sorts
^^^^^^^^^^^^^^

CrudJsonApi supports any combination of the above sorts. E.g.

- ``/currencies?include=countries&sort=name,countries.code``
- ``/currencies?include=countries&sort=name,-countries.code``
