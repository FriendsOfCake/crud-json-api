Filtering
=========

To enable `JSON API Filtering <http://jsonapi.org/format/#fetching-filtering>`_
install and configure the
``Search`` listener as `described here <http://crud.readthedocs.io/en/latest/listeners/search.html>`_
and then simply use search aliases named ``filter`` like shown below:

.. code-block:: phpinline

  public function searchConfiguration()
  {
    $search = new Manager($this);
    $search->like('filter', [
        'before' => true,
        'after' => true,
        'field' => [$this->aliasField('name')]
    ]);

    return $search;
  }

Which would allow you to search your API using a URL similar to ``/countries?filter=nether``.

Please note that not
`all filtering options <https://github.com/FriendsOfCake/crud/issues/524>`_
have been implemented yet.
