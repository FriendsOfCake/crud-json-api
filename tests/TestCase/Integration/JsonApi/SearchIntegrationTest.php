<?php
namespace CrudJsonApi\Test\TestCase\Integration\JsonApi;

use Cake\Event\Event;
use Cake\Event\EventManager;
use CrudJsonApi\Test\TestCase\Integration\JsonApiBaseTestCase;

class SearchIntegrationTest extends JsonApiBaseTestCase
{
    /**
     * @return array
     */
    public function viewProvider()
    {
        return [
            // assert single-field searches (case sensitive for now or
            //  Postgres CI tests will fail)
            'single field full search-key' => [
                '/countries?filter=Netherlands',
                'search_single_field.json',
            ],
            'single field partial search-key' => [
                '/countries?filter=Nether',
                'search_single_field.json',
            ]
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
