Filtering
=========

`JSON API Filtering <http://jsonapi.org/format/#fetching-filtering>`_
allow searching your API and requires:

1. Composer installing `friendsofcake/search`
2. Configuring the ``Crud SearchListener`` as `described here <http://crud.readthedocs.io/en/latest/listeners/search.html>`_

Now create search aliases named ``filter`` in your tables like shown below:

.. code-block:: phpinline

  // src/Model/Table/CountriesTable.php

  public function searchManager()
  {
      $searchManager = $this->behaviors()->Search->searchManager();
      $searchManager->like('filter', [
          'before' => true,
          'after' => true,
          'field' => [$this->aliasField('name')],
      ]);

      return $searchManager;
  }

Once that is done you will be able to search your API using URLs similar to:

- ``/countries?filter=netherlands``
- ``/countries?filter=nether``
