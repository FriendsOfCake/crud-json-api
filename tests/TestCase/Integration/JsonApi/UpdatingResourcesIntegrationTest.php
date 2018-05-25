<?php
namespace CrudJsonApi\Test\TestCase\Integration\JsonApi;

use CrudJsonApi\Test\TestCase\Integration\JsonApiBaseTestCase;

class UpdatingResourcesIntegrationTest extends JsonApiBaseTestCase
{

    /**
     * PhpUnit Data Provider that will call `testUpdateResource()` for every array entry
     * so we can test multiple successful PATCH requests without repeating ourselves.
     *
     * Please note that we MUST update records that already exist in the database fixtures.
     *
     * @return array
     */
    public function updateResourceProvider()
    {
        return [
            # changing USD to RUB
            'update-single-word-no-relationships-all-attributes' => [
                '/currencies/2', // URL
                'update-currency-no-relationships-all-attributes.json', // Fixtures/JsonApiRequestBodies/UpdatingResources
                'updated-currency-no-relationships-all-attributes.json' // Fixtures/JsonApiResponseBodies/UpdatingResources
            ],

            # In this test, by leaving out the `code` attribute, we also assert the JSON API criteria:
            # "If a request does not include all of the attributes for a resource, the server MUST
            #  interpret the missing attributes as if they were included with their current values.
            #  The server MUST NOT interpret missing attributes as null values."
            'update-single-word-no-relationships-single-attribute' => [
                '/currencies/2',
                'update-currency-no-relationships-single-attribute.json',
                'updated-currency-no-relationships-single-attribute.json'
            ],
        ];
    }

    /**
     * @param string $url The endpoint to hit
     * @param string $body JSON API body in CakePHP array format
     * @param string $expectedResponseFile The file to find the expected jsonapi response in
     * @return void
     * @dataProvider updateResourceProvider
     */
    public function testUpdateResource($url, $requestBodyFile, $expectedResponseFile)
    {
        $this->configRequest([
            'headers' => [
                'Accept' => 'application/vnd.api+json',
                'Content-Type' => 'application/vnd.api+json'
            ],
            'input' => $this->_getJsonApiRequestBody('UpdatingResources' . DS . $requestBodyFile)
        ]);

        # execute the PATCH request
        $this->patch($url);

        # assert response
        $this->assertResponseCode(200);
        $this->_assertJsonApiResponseHeaders();
        $this->assertResponseEquals($this->_getExpectedResponseBody('UpdatingResources' . DS . $expectedResponseFile));
    }
}
