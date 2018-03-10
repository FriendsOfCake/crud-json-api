<?php
namespace CrudJsonApi\Test\TestCase\Integration\JsonApi;

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
            'unsorted with include' => [
                '/currencies?include=countries',
                'get_currencies_and_countries_no_sort.json',
            ],
            'sorted with include' => [
                '/currencies?include=countries&sort=countries.code',
                'get_currencies_and_countries_sort_by_code.json',
            ],
            'sorted desc with include' => [
                '/currencies?include=countries&sort=-countries.code',
                'get_currencies_and_countries_sort_by_code_desc.json',
            ],
            'multi fields sorting' => [
                '/currencies?include=countries&sort=code,countries.code',
                'get_currencies_and_countries_sort_by_code.json',
            ],
            'multi fields sorting with direction' => [
                '/currencies?include=countries&sort=code,-countries.code',
                'get_currencies_and_countries_sort_by_code_desc.json',
            ],
            'view with multi fields sorting with direction' => [
                '/currencies/1?include=countries&sort=code,-countries.code',
                'get_currency_and_countries_sort_by_code_desc.json',
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
}
