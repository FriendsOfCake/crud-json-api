<?php
namespace CrudJsonApi\Test\TestCase\Integration\JsonApi;

use Cake\Core\Configure;
use Cake\Event\Event;
use Cake\Event\EventManager;
use CrudJsonApi\Test\TestCase\Integration\JsonApiBaseTestCase;

class SparseFieldsetsIntegrationTest extends JsonApiBaseTestCase
{
    /**
     * @return array
     */
    public function viewProvider()
    {
        return [
            // assert "single-field" sparse for index actions
            'single-field sparse index' => [
                '/countries?fields[countries]=name',
                'sparse-fieldsets/index_single_field_sparse.json',
            ],
            'single-field sparse for included index data' => [
                '/countries?include=currencies&fields[currencies]=id,name',
                'sparse-fieldsets/index_single_field_sparse_for_included_data.json',
            ],
            'combined single-field sparse index (both primary and included data)' => [
                '/countries?fields[countries]=name,currency&include=currencies&fields[currencies]=id,code',
                'sparse-fieldsets/index_single_field_sparse_for_primary_and_included_data.json'
            ],

            // assert "single-field" sparse for view actions
            'single-field sparse view' => [
                '/countries/1?fields[countries]=name',
                'sparse-fieldsets/view_single_field_sparse.json',
            ],
            'single-field sparse for included view data' => [
                '/countries/1?include=currencies&fields[currencies]=id,name',
                'sparse-fieldsets/view_single_field_sparse_for_included_data.json',
            ],
            'combined single-field sparse view (both primary and included data)' => [
                '/countries/1?fields[countries]=name,currency&include=currencies&fields[currencies]=id,code',
                'sparse-fieldsets/view_single_field_sparse_for_primary_and_included_data.json'
            ],

            // assert "multi-field" sparse for index actions
            'multi-field sparse index' => [
                '/countries?fields[countries]=name,code',
                'sparse-fieldsets/index_multi_field_sparse.json',
            ],
            'multi-field sparse for included index data' => [
                '/countries?include=currencies&fields[currencies]=id,name,code',
                'sparse-fieldsets/index_multi_field_sparse_for_included_data.json'
            ],
            'combined multi-field sparse index (both primary and included data)' => [
                '/countries?fields[countries]=code,name,currency&include=currencies&fields[currencies]=id,code,name',
                'sparse-fieldsets/index_multi_field_sparse_for_primary_and_included_data.json'
            ],

            // assert "multi-field" sparse for view actions
            'multi-field sparse view' => [
                '/countries/1?fields[countries]=name,code',
                'sparse-fieldsets/view_multi_field_sparse.json',
            ],
            'multi-field sparse for included view data' => [
                '/countries/1?include=currencies&fields[currencies]=id,name,code',
                'sparse-fieldsets/view_multi_field_sparse_for_included_data.json',
            ],
            'combined multi-field sparse view (both primary and included data)' => [
                '/countries/1?fields[countries]=name,code,currency&include=currencies&fields[currencies]=id,code,name',
                'sparse-fieldsets/view_multi_field_sparse_for_primary_and_included_data.json'
            ],

            'sparse fieldsets - view no relationship' => [
                '/countries/1?fields[countries]=name',
                'sparse-fieldsets/view_no_relationship.json',
            ],
            'sparse fields - index no relationship' => [
                '/countries?fields[countries]=name',
                'sparse-fieldsets/index_no_relationship.json',
            ],
            'sparse fields - index with include' => [
                '/countries?fields[countries]=name,currency&include=currencies&fields[currencies]=id,name',
                'sparse-fieldsets/index_with_include.json',
            ],

            'sparse fieldsets - index with include and sort' => [
                '/countries?fields[countries]=name,currency&include=currencies&fields[currencies]=id,name&sort=name',
                'sparse-fieldsets/index_with_include_and_sort.json',
            ],
            'sparse fieldsets - index with include and sort desc' => [
                '/countries?fields[countries]=name,currency&include=currencies&fields[currencies]=id,name&sort=-name',
                'sparse-fieldsets/index_with_include_and_sort_desc.json',
            ],
        ];
    }

    /**
     * @return array
     */
    public function paginationUrlProvider()
    {
        return [
            'pagination' => [
                '/national-cities?page=2&limit=2',
                'national_cities_absolute_links.json',
            ],
        ];
    }

    /**
     * @param string $url The endpoint to hit
     * @param string $expectedFile The file to find the expected result in
     * @return void
     * @dataProvider viewProvider
     */
    public function testView($url, $expectedFile)
    {
        $this->get($url);

        $this->assertResponseSuccess();
        $this->_assertJsonApiResponseHeaders();
        $this->assertResponseEquals($this->_getExpected($expectedFile));
    }

    /**
     * @param string $url The endpoint to hit
     * @param string $expectedFile The file to find the expected result in
     * @return void
     * @dataProvider paginationUrlProvider
     */
    public function testAbsoluteLinksInPagination($url, $expectedFile)
    {
        Configure::write('App.fullBaseUrl', 'http://test-server');
        $this->get($url);

        $this->assertResponseSuccess();
        $this->_assertJsonApiResponseHeaders();
        $this->assertResponseEquals($this->_getExpected($expectedFile));
    }
}
