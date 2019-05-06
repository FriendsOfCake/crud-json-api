<?php
namespace CrudJsonApi\Test\TestCase\Integration\JsonApi;

use CrudJsonApi\Test\TestCase\Integration\JsonApiBaseTestCase;

class FetchingResourcesIntegrationTest extends JsonApiBaseTestCase
{
    /**
     * PhpUnit Data Provider that will call `testFetchResource()` for every array entry
     * so we can test multiple successful GET requests without repeating ourselves.
     *
     * @return array
     */
    public function fetchResourceProvider()
    {
        return [
            'fetch-single-word-resource-with-no-relationships' => [
                '/currencies/1',
                'get-currency-no-relationships.json'
            ],

            'fetch-single-word-resource-with-multiple-belongsto-relationships' => [
                '/countries/1',
                'get-country-multiple-belongsto-relationships.json'
            ],

            'fetch-multi-word-resource-with-no-relationships' => [
                '/national-capitals/1',
                'get-national-capital-no-relationships.json'
            ],

            'fetch-multi-word-resource-with-single-belongsTo-relationship' => [
                '/national-cities/1',
                'get-national-city-single-belongsto-relationship.json'
            ],
        ];
    }

    /**
     * @param string $url The endpoint to hit
     * @param string $expectedResponseFile The file to find the expected jsonapi response in
     * @return void
     * @dataProvider fetchResourceProvider
     */
    public function testFetchResource($url, $expectedResponseFile)
    {
        $this->configRequest([
            'headers' => [
                'Accept' => 'application/vnd.api+json'
            ]
        ]);

        # execute the GET request
        $this->get($url);

        # assert the response
        $this->assertResponseCode(200);
        $this->_assertJsonApiResponseHeaders();
        $this->assertResponseSameAsFile('FetchingResources' . DS . $expectedResponseFile);
    }
}
