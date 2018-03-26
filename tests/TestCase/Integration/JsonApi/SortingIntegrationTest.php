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
    public function viewProvider()
    {
        return [
            'unsorted' => [
                '/currencies',
                'get_currencies_no_sort.json',
            ],
            'sorted' => [
                '/currencies?sort=code',
                'get_currencies_no_sort.json',
            ],
            'sorted descending' => [
                '/currencies?sort=-code',
                'get_currencies_sort_by_code_desc.json',
            ],
            'sort single field ascending (default)' => [
                '/countries?sort=name',
                'sorting/sort_single_field_ascending.json',
            ],
            'sort primary data by related resource (single field)' => [
                '/countries?include=national_capital&sort=national_capital.name&limit=10',
                'sorting/sort_primary_data_by_related_resource_single_field.json',
            ],
            'unsorted with include' => [
                '/currencies?include=countries',
                'get_currencies_and_countries_no_sort.json',
            ],
            'sorted with include' => [
                '/countries?include=currency&sort=code',
                'sorting/sorted_with_include.json',
            ],
            'sorted with include page 2' => [
                '/countries?include=currency&sort=code&page=2',
                'sorting/sorted_with_include_page_2.json',
            ],
            'sorted desc with include' => [
                '/countries?include=currency&sort=-code',
                'sorting/sorted_desc_with_include.json'
            ],
            'multi fields sorting' => [
                '/countries?include=currency&sort=dummy_counter,code',
                'sorting/multi_fields_sorting.json'
            ],
            'multi fields sorting with direction' => [
                '/countries?include=currency&sort=-dummy_counter,-code',
                //'get_currencies_and_countries_sort_by_code_desc.json',
                'sorting/multi_fields_sorting_with_direction.json'
            ],
            'view with multi fields sorting with direction' => [
                '/currencies/1?include=countries&sort=code,-countries.code',
                'get_currency_and_countries_sort_by_code_desc.json',
            ],
            'sort fields not in include' => [
                '/countries?include=currencies&sort=code,national_capitals.name',
                'get_countries_and_currencies_sort_by_code.json',
            ],
            'sort fields in include' => [
                '/countries?include=currencies,national_capitals&sort=countries.code,national_capitals.name',
                'get_countries_and_currencies_and_capitals_sort_by_code.json',
            ],
            // Aside from the pagination payload in "meta", this should
            // produce identical result as 'sort fields not in include'.
            // Test two things:
            // - fields from non included type does generate a contain()
            // - dot notation against primary type is correctly handled
            'sort primary field in dot notation' => [
                '/countries?include=currencies&sort=countries.code,national_capitals.name',
                'sort_primary_field_in_dot_notation.json',
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
