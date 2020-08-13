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

        # execute the POST request
        $this->post($url);

        # assert the response
        $this->assertResponseCode(200); # https://jsonapi.org/format/#crud-updating-relationship-responses-200
        $this->_assertJsonApiResponseHeaders();
        $this->assertResponseNotEmpty();
        $this->assertResponseSameAsFile('Relationships' . DS . $expectedResponseFile);
    }

    /**
     * @return void
     */
    public function testNoPostOneToOne()
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

        # execute the POST request
        $this->post('/countries/2/relationships/currency');

        # assert the response
        $this->assertResponseCode(403); # https://jsonapi.org/format/#crud-updating-relationship-responses-403
        $this->_assertJsonApiResponseHeaders();
        $this->assertResponseNotEmpty();
    }
}
