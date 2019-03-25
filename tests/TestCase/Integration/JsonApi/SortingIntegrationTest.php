<?php
namespace CrudJsonApi\Test\TestCase\Integration\JsonApi;

use Cake\Core\Configure;
use Cake\Event\Event;
use Cake\Event\EventManager;
use CrudJsonApi\Test\TestCase\Integration\JsonApiBaseTestCase;

class SortingIntegrationTest extends JsonApiBaseTestCase
{
    /**
     * @return array
     */
    public function sortProvider()
    {
        return [
            'unsorted' => [
                '/currencies',
                'get-currencies-no-sort.json',
            ],
            'sorted' => [
                '/currencies?sort=code',
                'get-currencies-no-sort.json',
            ],
            'sorted descending' => [
                '/currencies?sort=-code',
                'get-currencies-sort-by-code-desc.json',
            ],
            'sort single field ascending (default)' => [
                '/countries?sort=name',
                'sort-single-field-ascending.json',
            ],
            'sort primary data by related resource (single field)' => [
                '/countries?include=national_capital&sort=national_capital.name&limit=10',
                'sort-primary-data-by-related-resource-single-field.json',
            ],
            'unsorted with include' => [
                '/currencies?include=countries',
                'get-currencies-and-countries-no-sort.json',
            ],
            'sorted with include' => [
                '/countries?include=currency&sort=code',
                'sorted-with-include.json',
            ],
            'sorted with include page 2' => [
                '/countries?include=currency&sort=code&page=2',
                'sorted-with-include-page-2.json',
            ],
            'sorted desc with include' => [
                '/countries?include=currency&sort=-code',
                'sorted-desc-with-include.json'
            ],
            'multi fields sorting' => [
                '/countries?include=currency&sort=dummy_counter,code',
                'multi-fields-sorting.json'
            ],
            'multi fields sorting with direction' => [
                '/countries?include=currency&sort=-dummy_counter,-code',
                //'get_currencies_and_countries_sort_by_code_desc.json',
                'multi-fields-sorting-with-direction.json'
            ],

            'hasMany - index with multi fields sorting with direction' => [
                '/currencies?include=countries&sort=code,-countries.code&limit=5',
                'hasmany-index-with-multi-fields-sorting-with-direction.json',
            ],

            'view with multi fields sorting with direction' => [
                '/currencies/1?include=countries&sort=code,-countries.code',
                'get-currency-and-countries-sort-by-code-desc.json',
            ],
            'sort fields not in include' => [
                '/countries?include=currencies&sort=code,national_capitals.name',
                'get-countries-and-currencies-sort-by-code.json',
            ],
            'sort fields in include' => [
                '/countries?include=currencies,national_capitals&sort=countries.code,national_capitals.name',
                'get-countries-and-currencies-and-capitals-sort-by-code.json',
            ],
            // Aside from the pagination payload in "meta", this should
            // produce identical result as 'sort fields not in include'.
            // Test two things:
            // - fields from non included type does generate a contain()
            // - dot notation against primary type is correctly handled
            'sort primary field in dot notation' => [
                '/countries?include=currencies&sort=countries.code,national_capitals.name',
                'sort-primary-field-in-dot-notation.json',
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
     * @dataProvider sortProvider
     */
    public function testView($url, $expectedFile)
    {
        $this->get($url);

        $this->assertResponseSuccess();
        $this->_assertJsonApiResponseHeaders();
        $this->assertResponseSameAsFile('Sorting' . DS . $expectedFile);
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
        $this->assertResponseSameAsFile('Sorting' . DS . $expectedFile);
    }
}
