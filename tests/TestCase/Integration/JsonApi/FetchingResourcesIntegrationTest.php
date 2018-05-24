<?php
namespace CrudJsonApi\Test\TestCase\Integration\JsonApi;

use CrudJsonApi\Test\TestCase\Integration\JsonApiBaseTestCase;

class FetchingResourcesIntegrationTest extends JsonApiBaseTestCase
{
    /**
     * PhpUnit Data Provider for testing (only) successful GET requests.
     *
     * @return array
     */
    public function getProvider()
    {
        return [
            'fetch-single-word-resource-with-no-relationships' => [
                '/currencies/1',
                'get-currency.json'
            ],

            'fetch-single-word-resource-with-multiple-belongsto-relationships' => [
                '/countries/1',
                'get-country.json'
            ],

            'fetch-multi-word-resource-with-no-belongsTo-associations' => [
                '/national-capitals/1',
                'get-national-capital.json'
            ],
        ];
    }

    /**
     * @param string $url The endpoint to hit
     * @param string $expectedResponseFile The file to find the expected jsonapi response in
     * @return void
     * @dataProvider getProvider
     */
    public function testGet($url, $expectedResponseFile)
    {
        $this->configRequest([
            'headers' => [
                'Accept' => 'application/vnd.api+json'
            ]
        ]);

        # execute the GET request
        $this->get($url);
        $this->assertResponseCode(200);
        $this->_assertJsonApiResponseHeaders();

        $this->assertResponseEquals($this->_getExpectedResponseBody('FetchingResources' . DS . $expectedResponseFile));
    }
}
