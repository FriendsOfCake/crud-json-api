<?php
namespace CrudJsonApi\Test\TestCase\Integration\JsonApi;

use Cake\Event\Event;
use Cake\Event\EventManager;
use CrudJsonApi\Test\TestCase\Integration\JsonApiBaseTestCase;

class FilteringIntegrationTest extends JsonApiBaseTestCase
{
    /**
     * @return array
     */
    public function filterProvider()
    {
        return [
            // assert single-field searches (case sensitive for now or
            //  Postgres CI tests will fail)
            'single field full search-key' => [
                '/countries?filter=Netherlands',
                'filter-single-field.json',
            ],
            'single field partial search-key' => [
                '/countries?filter=Nether',
                'filter-single-field-partial.json',
            ]
        ];
    }

    /**
     * @param string $url The endpoint to hit
     * @param string $expectedFile The file to find the expected result in
     * @return void
     * @dataProvider filterProvider
     */
    public function testFilter($url, $expectedFile)
    {
        $this->get($url);

        $this->assertResponseSuccess();
        $this->_assertJsonApiResponseHeaders();
        $this->assertResponseSameAsFile('Filtering' . DS . $expectedFile);
    }
}
