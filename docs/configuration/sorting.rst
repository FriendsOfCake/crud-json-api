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

To sort by a single field passing in in ascending order:

``/currencies?sort=code``

To sort in descending order:

``/currencies?sort=-code``

Multi Field Sorting
^^^^^^^^^^^^^^^^^^^

To sort by multiple fields simply pass them as comma-separated sort fields
in the order you want them applied:

- ``/currencies?sort=code,name``
- ``/currencies?sort=-code,name``
- ``/currencies?sort=-code,-name``
- ``/currencies?sort=name,code``

Sorting By Related Data
^^^^^^^^^^^^^^^^^^^^^^^

You may want to sort your primary data using fields in the related data. In this case
all ``currencies`` (the primary data) would be sorted using the ``code`` field of the
associated ``countries``.

- ``/currencies?include=countries&sort=countries.code``
- ``/currencies?include=countries&sort=-countries.code``

Sorting Included Data
^^^^^^^^^^^^^^^^^^^^^

Does this still apply?


Combined Sorts
^^^^^^^^^^^^^^

You may also choose to combine sorts. In this case ``currencies`` will be sorted using the ``name`` field
whereas all data inside the ``included`` node will be sorted using their ``code`` field.

``/currencies?include=countries&sort=name,countries.code``

Other variations may include:

``/currencies?include=countries&sort=name,-countries.code``


