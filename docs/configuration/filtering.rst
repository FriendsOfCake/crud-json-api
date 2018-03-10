Filtering/Search
================

`JSON API Filtering <http://jsonapi.org/format/#fetching-filtering>`_
requires installing and configuring the
``Crud SearchListener`` as `described here <http://crud.readthedocs.io/en/latest/listeners/search.html>`_.

After you have installed the plugin simply use search aliases named ``filter`` like shown below:

.. code-block:: phpinline
// src/Model/Table/CountriesTable.php

public function searchManager()
{
    $searchManager = $this->behaviors()->Search->searchManager();
    $searchManager->like('filter', [
        'before' => true,
        'after' => true,
        'field' => [$this->aliasField('name')]
    ]);

    return $searchManager;
}

Which would then allow you to search your API using a URL similar to:

- ``/countries?filter=netherlands``
- ``/countries?filter=nether``

Please note that the following requests would also be matched:

- ``/countries?filter[id]=1``
- ``/countries?filter[id][]=1&filter[id][]=2``
