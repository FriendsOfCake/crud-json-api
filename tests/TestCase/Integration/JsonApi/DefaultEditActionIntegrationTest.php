<?php
namespace CrudJsonApi\Test\TestCase\Integration\JsonApi;

use CrudJsonApi\Test\TestCase\Integration\JsonApiBaseTestCase;

class DefaultEditActionIntegrationTest extends JsonApiBaseTestCase
{
    /**
     * Make sure successful PATCH requests return HTTP Status Code 200.
     *
     * @link http://jsonapi.org/format/#crud-updating-responses-200
     * @link https://github.com/FriendsOfCake/crud-json-api/issues/23
     * @return void
     */
    public function testSuccessfulPatchReturnsStatusCode201()
    {
        $postData = [
            'data' => [
                'type' => 'countries',
                'id' => '1',
                'attributes' => [
                    'code' => 'NZ',
                    'name' => 'New Zealand',
                    'currency_id' => 10,
                    'national_capital_id' => 3
                ]
            ]
        ];

        $this->configRequest([
            'headers' => [
                'Accept' => 'application/vnd.api+json',
                'Content-Type' => 'application/vnd.api+json'
            ],
            'input' => json_encode($postData)
        ]);

        $this->patch('/countries/1');

        $this->assertResponseOk();
        $this->_assertJsonApiResponseHeaders();
        $this->assertResponseCode(200);
        $this->assertResponseNotEmpty();
    }
}
