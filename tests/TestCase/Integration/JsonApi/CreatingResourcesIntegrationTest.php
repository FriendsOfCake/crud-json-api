<?php
namespace CrudJsonApi\Test\TestCase\Integration\JsonApi;

use CrudJsonApi\Test\TestCase\Integration\JsonApiBaseTestCase;

class PostRequestIntegrationTest extends JsonApiBaseTestCase
{

    /**
     * Make sure attempts to side-post/create related hasMany records throws an exception.
     *
     * @return void
     */
    public function testSidePostingException()
    {
        $this->configRequest([
            'headers' => [
                'Accept' => 'application/vnd.api+json',
                'Content-Type' => 'application/vnd.api+json'
            ],
            'input' => $this->_getJsonApiRequestBody('CreatingResources' . DS . 'create-country-throw-side-posting-exception.json')
        ]);

        $this->post('/countries');
        $this->assertResponseCode(400); // bad request
        $responseBodyArray = json_decode((string)$this->_response->getBody(), true);

        $expectedErrorMessage = [
            'errors' => [
                [
                    'status' => 400,
                    'title' => 'Bad Request',
                    'detail' => 'JSON API 1.1 does not support sideposting (hasMany relationships detected in the request body)'
                ]
            ]
        ];

        $this->assertArraySubset($expectedErrorMessage, $responseBodyArray);
    }

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
            'create-single-word-resource-no-relationships' => [
                '/currencies', // URL
                'create-currency-no-relationships.json', // Fixtures/JsonApiRequestBodies/CreatingResources
                'created-currency-no-relationships.json' // Fixtures/JsonApiResponseBodies/CreatingResources
            ],

            'create-single-word-resource-multiple-existing-belongsto-relationships' => [
                '/countries',
                'create-country-multiple-existing-belongsto-relationships.json',
                'created-country-multiple-existing-belongsto-relationships.json'
            ],

            'create-multi-word-resource-no-relationships' => [
                '/national-capitals',
                'create-national-capital-no-relationships.json',
                'created-national-capital-no-relationships.json'
            ],

            'create-multi-word-resource-single-existing-belongsto-relationships' => [
                '/national-cities',
                'create-national-city-single-existing-belongsto-relationship.json',
                'created-national-city-single-existing-belongsto-relationship.json'
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
        $this->assertResponseSameAsFile('CreatingResources' . DS . $expectedResponseFile);
    }
}
