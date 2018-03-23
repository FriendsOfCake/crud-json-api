Sorting
=======

CrudJsonApi fully supports
`JSON API Sorting <http://jsonapi.org/format/#fetching-sorting>`_
which allows you to sort the results produced by your API according to one
by passing one or more criteria to your request using the ``sort`` parameter.

Before continuing please note that the default sort order for each field is ascending
UNLESS the field is prefixed with a hyphen (``-``) in which case the sort order will
be descending.

Single Field Sorting
^^^^^^^^^^^^^^^^^^^^

To select all currencies and sort the results by the `code` field:

``/currencies?sort=code``

To select all currencies and sort the results by the `code` field in descending order:

``/currencies?sort=-code``

Multi Field Sorting
^^^^^^^^^^^^^^^^^^^

@rchavik: I THINK WE NEED AN EXAMPLE OR TESTCASE HERE, SEEMS MISSING. E.G.

``/currencies?sort=code,name``

``/currencies?sort=-code,name``

``/currencies?sort=-code,-name``


Sorting Included Data
^^^^^^^^^^^^^^^^^^^^^

It is also possible to sort the data returned in the ``included`` node. In this example ``currencies`` will
use the default sort order whereas all ``countries`` inside the ``included`` node will be sorted ascending,
using their ``code`` field.

``/currencies?include=countries&sort=countries.code``

To sort all ``countries`` in the ``included`` node in descending order:

``/currencies?include=countries&sort=-countries.code``

Combined Sorts
^^^^^^^^^^^^^^

You may also choose to combine sorts. In this case ``currencies`` will be sorted using the ``name`` field
whereas all data inside the ``included`` node will be sorted using their ``code`` field.

``/currencies?include=countries&sort=name,countries.code``

Other variations may include:

``/currencies?include=countries&sort=name,-countries.code``


