<?php
namespace CrudJsonApi\Test\TestCase\Integration\JsonApi;

use CrudJsonApi\Test\TestCase\Integration\JsonApiBaseTestCase;

class PostRequestIntegrationTest extends JsonApiBaseTestCase
{
    /**
     * PhpUnit Data Provider that will call `testCreateResource()` for every array entry
     * so we can test multiple successful POST requests without repeating ourselves.
     *
     *
     * @return array
     */
    public function createResourceProvider()
    {
        return [
            'create-single-word-resource' => [
                '/countries', // URL
                'create-country-multiple-belongsto-relationships.json', // Fixtures/JsonApiRequestBodies
                'created-country-multiple-belongsto-relationships.json' // Fixtures/JsonApiResponseBodies
            ],
        ];
    }

    /**
     * @param string $url The endpoint to hit
     * @param string $body JSON API body in CakePHP array format
     * @param string $expectedResponseFile The file to find the expected jsonapi response in
     * @return void
     * @dataProvider createResourceProvider
     */
    public function testCreateResource($url, $requestBodyFile, $expectedResponseFile)
    {
        $this->configRequest([
            'headers' => [
                'Accept' => 'application/vnd.api+json',
                'Content-Type' => 'application/vnd.api+json'
            ],
            'input' => $this->_getJsonApiRequestBody('CreatingResources' . DS . $requestBodyFile)
        ]);

        # execute the POST request
        $this->post($url);

        # assert the response
        $this->assertResponseCode(201); # http://jsonapi.org/format/#crud-creating-responses-201
        $this->_assertJsonApiResponseHeaders();
        $this->assertResponseNotEmpty();
        $this->assertResponseEquals($this->_getExpectedResponseBody('CreatingResources' . DS . $expectedResponseFile));
    }
}
