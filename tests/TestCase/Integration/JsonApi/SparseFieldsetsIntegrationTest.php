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
                'index-single-field-sparse.json',
            ],
            'single-field sparse for included index data' => [
                '/countries?include=currencies&fields[currencies]=id,name',
                'index-single-field-sparse-for-included-data.json',
            ],
            'combined single-field sparse index (both primary and included data)' => [
                '/countries?fields[countries]=name,currency&include=currencies&fields[currencies]=id,code',
                'index-single-field-sparse-for-primary-and-included-data.json'
            ],

            // assert "single-field" sparse for view actions
            'single-field sparse view' => [
                '/countries/1?fields[countries]=name',
                'view-single-field-sparse.json',
            ],
            'single-field sparse for included view data' => [
                '/countries/1?include=currencies&fields[currencies]=id,name',
                'view-single-field-sparse-for-included-data.json',
            ],
            'combined single-field sparse view (both primary and included data)' => [
                '/countries/1?fields[countries]=name,currency&include=currencies&fields[currencies]=id,code',
                'view-single-field-sparse-for-primary-and-included-data.json'
            ],

            // assert "multi-field" sparse for index actions
            'multi-field sparse index' => [
                '/countries?fields[countries]=name,code',
                'index-multi-field-sparse.json',
            ],
            'multi-field sparse for included index data' => [
                '/countries?include=currencies&fields[currencies]=id,name,code',
                'index-multi-field-sparse-for-included-data.json'
            ],
            'combined multi-field sparse index (both primary and included data)' => [
                '/countries?fields[countries]=code,name,currency&include=currencies&fields[currencies]=id,code,name',
                'index-multi-field-sparse-for-primary-and-included-data.json'
            ],

            // assert "multi-field" sparse for view actions
            'multi-field sparse view' => [
                '/countries/1?fields[countries]=name,code',
                'view-multi-field-sparse.json',
            ],
            'multi-field sparse for included view data' => [
                '/countries/1?include=currencies&fields[currencies]=id,name,code',
                'view-multi-field-sparse-for-included-data.json',
            ],
            'combined multi-field sparse view (both primary and included data)' => [
                '/countries/1?fields[countries]=name,code,currency&include=currencies&fields[currencies]=id,code,name',
                'view-multi-field-sparse-for-primary-and-included-data.json'
            ],

            'sparse fieldsets - view no relationship' => [
                '/countries/1?fields[countries]=name',
                'view-no-relationship.json',
            ],
            'sparse fields - index no relationship' => [
                '/countries?fields[countries]=name',
                'index-no-relationship.json',
            ],
            'sparse fields - index with include' => [
                '/countries?fields[countries]=name,currency&include=currencies&fields[currencies]=id,name',
                'index-with-include.json',
            ],

            'sparse fieldsets - index with include and sort' => [
                '/countries?fields[countries]=name,currency&include=currencies&fields[currencies]=id,name&sort=name',
                'index-with-include-and-sort.json',
            ],
            'sparse fieldsets - index with include and sort desc' => [
                '/countries?fields[countries]=name,currency&include=currencies&fields[currencies]=id,name&sort=-name',
                'index-with-include-and-sort-desc.json',
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
                'national-cities-absolute-links.json',
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
        $this->assertResponseSameAsFile('SparseFieldsets' . DS . $expectedFile);
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
        $this->assertResponseSameAsFile('SparseFieldsets' . DS . $expectedFile);
    }
}
