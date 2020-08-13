<?php

namespace CrudJsonApi\Test\TestCase\Integration\JsonApi;

use CrudJsonApi\Test\TestCase\Integration\JsonApiBaseTestCase;

/**
 * Class RelationshipsIntegrationTest
 */
class RelationshipsIntegrationTest extends JsonApiBaseTestCase
{
    /**
     * @return array
     */
    public function getProvider()
    {
        return [
            'one-to-many: get cultures for country' => [
                '/countries/2/relationships/cultures',
                'get_culture_relationship_for_country.json',
            ],
            'one-to-many: get no cultures for country' => [
                '/countries/4/relationships/cultures',
                'get_culture_relationship_for_country_with_none.json',
            ],
            'many-to-one: get currency for country' => [
                '/countries/2/relationships/currency',
                'get_currency_relationship_for_country.json',
            ],
            'many-to-many: get languages for country' => [
                '/countries/1/relationships/languages',
                'get_languages_relationship_for_country.json',
            ],
        ];
    }

    /**
     * @param string $url The endpoint to hit
     * @param string $expectedFile The file to find the expected result in
     * @return void
     * @dataProvider getProvider
     */
    public function testGet($url, $expectedFile): void
    {
        $this->disableErrorHandlerMiddleware();
        $this->get($url);

        $this->assertResponseSuccess();
        $this->_assertJsonApiResponseHeaders();
        $this->assertResponseSameAsFile('Relationships' . DS . $expectedFile);
    }

    /**
     * @return array
     */
    public function postProvider()
    {
        return [
            'one-to-many: add culture relationship for country' => [
                '/countries/2/relationships/cultures',
                'add-culture-relationship.json',
                'post-add-culture-relationship.json',
            ],
            'one-to-many: add existing culture for country' => [
                '/countries/2/relationships/cultures',
                'add-existing-culture-relationship.json',
                'post-add-existing-culture-relationship.json',
            ],
            'many-to-many: add language to country' => [
                '/countries/1/relationships/languages',
                'add-language-relationship.json',
                'post-add-language-relationship.json',
            ],
        ];
    }

    /**
     * @param string $url The endpoint to hit
     * @param string $requestBodyFile The file to find the request in
     * @param string $expectedResponseFile The file to find the expected response in
     * @return void
     * @dataProvider postProvider
     */
    public function testPost($url, $requestBodyFile, $expectedResponseFile)
    {
        $this->configRequest(
            [
                'headers' => [
                    'Accept' => 'application/vnd.api+json',
                    'Content-Type' => 'application/vnd.api+json',
                ],
                'input' => $this->_getJsonApiRequestBody('Relationships' . DS . $requestBodyFile),
            ]
        );

        // execute the POST request
        $this->post($url);

        // assert the response
        $this->assertResponseCode(200); // https://jsonapi.org/format///crud-updating-relationship-responses-200
        $this->_assertJsonApiResponseHeaders();
        $this->assertResponseNotEmpty();
        $this->assertResponseSameAsFile('Relationships' . DS . $expectedResponseFile);
    }

    /**
     * @return \string[][]
     */
    public function toOne()
    {
        return [
            'POST' => ['post'],
            'DELETE' => ['delete']
        ];
    }

    /**
     * @param string $method Method to call
     * @return void
     * @dataProvider toOne
     */
    public function testNoToOne($method)
    {
        $this->configRequest(
            [
                'headers' => [
                    'Accept' => 'application/vnd.api+json',
                    'Content-Type' => 'application/vnd.api+json',
                ],
                'input' => json_encode(
                    [
                        'data' => [
                            'type' => 'currencies',
                            'id' => 1,
                        ],
                    ]
                ),
            ]
        );

        // execute the POST request
        $this->{$method}('/countries/2/relationships/currency');

        // assert the response
        $this->assertResponseCode(403); // https://jsonapi.org/format///crud-updating-relationship-responses-403
        $this->_assertJsonApiResponseHeaders();
        $this->assertResponseNotEmpty();
    }

    /**
     * @return \string[][]
     */
    public function missingRecordProvider()
    {
        return [
            'POST' => ['post'],
            'PATCH' => ['patch']
        ];
    }

    /**
     * @return void
     * @dataProvider missingRecordProvider
     */
    public function testMissingRecords($method)
    {
        $this->configRequest(
            [
                'headers' => [
                    'Accept' => 'application/vnd.api+json',
                    'Content-Type' => 'application/vnd.api+json',
                ],
                'input' => json_encode(
                    [
                        'data' => [
                            [
                                'type' => 'cultures',
                                'id' => 1,
                            ],
                            [
                                'type' => 'cultures',
                                'id' => 10,
                            ],
                        ],
                    ]
                ),
            ]
        );

        // execute the POST request
        $this->{$method}('/countries/2/relationships/cultures');

        // assert the response
        $this->assertResponseCode(404); // http://jsonapi.org/format///crud-updating-responses-404
        $this->_assertJsonApiResponseHeaders();
        $this->assertResponseNotEmpty();
        $this->assertResponseContains('Not all requested records could be found. Missing IDs are 10');
    }

    /**
     * @return array
     */
    public function patchProvider()
    {
        return [
            'one-to-many: replace culture relationship for country' => [
                '/countries/2/relationships/cultures',
                'replace-culture-relationship.json',
                'patch-replace-culture-relationship.json',
            ],
            'one-to-many: clear all culture for country' => [
                '/countries/2/relationships/cultures',
                'clear-culture-relationship.json',
                'patch-clear-culture-relationship.json',
            ],
            'many-to-many: replace language to country' => [
                '/countries/1/relationships/languages',
                'replace-language-relationship.json',
                'patch-replace-language-relationship.json',
            ],
            'many-to-one: replace currency of country' => [
                '/countries/1/relationships/currency',
                'replace-currency-relationship.json',
                'patch-replace-currency-relationship.json',
            ],
        ];
    }

    /**
     * @param string $url The endpoint to hit
     * @param string $requestBodyFile The file to find the request in
     * @param string $expectedResponseFile The file to find the expected response in
     * @return void
     * @dataProvider patchProvider
     */
    public function testPatch($url, $requestBodyFile, $expectedResponseFile)
    {
        $this->configRequest(
            [
                'headers' => [
                    'Accept' => 'application/vnd.api+json',
                    'Content-Type' => 'application/vnd.api+json',
                ],
                'input' => $this->_getJsonApiRequestBody('Relationships' . DS . $requestBodyFile),
            ]
        );

        $this->disableErrorHandlerMiddleware();
        // execute the POST request
        $this->patch($url);

        // assert the response
        $this->assertResponseCode(200); // https://jsonapi.org/format///crud-updating-relationship-responses-200
        $this->_assertJsonApiResponseHeaders();
        $this->assertResponseNotEmpty();
        $this->assertResponseSameAsFile('Relationships' . DS . $expectedResponseFile);
    }

    /**
     * @return array
     */
    public function deleteProvider()
    {
        return [
            'one-to-many: delete culture relationship for country' => [
                '/countries/2/relationships/cultures',
                'delete-culture-relationship.json',
                'delete-culture-relationship.json',
            ],
            'one-to-many: delete culture relationship that does not exist for country' => [
                '/countries/2/relationships/cultures',
                'delete-not-existing-culture-relationship.json',
                'delete-not-existing-culture-relationship.json',
            ],
            'many-to-many: delete language to country' => [
                '/countries/1/relationships/languages',
                'delete-language-relationship.json',
                'delete-language-relationship.json',
            ],
        ];
    }

    /**
     * @param string $url The endpoint to hit
     * @param string $requestBodyFile The file to find the request in
     * @param string $expectedResponseFile The file to find the expected response in
     * @return void
     * @dataProvider deleteProvider
     */
    public function testDelete($url, $requestBodyFile, $expectedResponseFile)
    {
        $this->configRequest(
            [
                'headers' => [
                    'Accept' => 'application/vnd.api+json',
                    'Content-Type' => 'application/vnd.api+json',
                ],
                'input' => $this->_getJsonApiRequestBody('Relationships' . DS . $requestBodyFile),
            ]
        );

        // execute the DELETE request
        $this->delete($url);

        // assert the response
        $this->assertResponseCode(200); // https://jsonapi.org/format///crud-updating-relationship-responses-200
        $this->_assertJsonApiResponseHeaders();
        $this->assertResponseNotEmpty();
        $this->assertResponseSameAsFile('Relationships' . DS . $expectedResponseFile);
    }
}
