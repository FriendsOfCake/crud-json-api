<?php
namespace CrudJsonApi\Test\TestCase\Integration\JsonApi;

use CrudJsonApi\Test\TestCase\Integration\JsonApiBaseTestCase;

class UpdatingResourcesIntegrationTest extends JsonApiBaseTestCase
{

    /**
     * PhpUnit Data Provider for testing (only) successful PATCH requests.
     *
     * Each array-entry is executed against the `testPatch()` method in this Integration test which
     * will automatically check for the correct (jsonapi) response headers, the expected HTTP Response
     * Code 200 and the expected (jsonapi) response body.
     *
     * @return array
     */
    public function patchProvider()
    {
        return [
            #
            # Test updating a single-word resource.
            #
            # By leaving out the `dummy-counter` attribute we also assert the following JSON API criteria:
            # "If a request does not include all of the attributes for a resource, the server MUST
            #  interpret the missing attributes as if they were included with their current values.
            #  The server MUST NOT interpret missing attributes as null values."
            #
            'patch-single-word-resource-only' => [
                '/countries/1',
                [
                    'data' => [
                        'type' => 'countries',
                        'id' => '1',
                        'attributes' => [
                            'code' => 'NL',
                            'name' => 'Patched Netherlands'
                        ]
                    ]
                ],
                'PatchRequest/patch-single-word-resource.json',
            ],

            #
            # Test updating a multi-word resource.
            #
            'patch-multi-word-resource' => [
                '/national-capitals/1',
                [
                    'data' => [
                        'type' => 'national-capitals',
                        'id' => '1',
                        'attributes' => [
                            'name' => 'Patched Amsterdam'
                        ]
                    ]
                ],
                'PatchRequest/patch-multi-word-resource',
            ],

        ];
    }

    /**
     * @param string $url The endpoint to hit
     * @param string $body JSON API body in CakePHP array format
     * @param string $expectedResponseFile The file to find the expected jsonapi response in
     * @return void
     * @dataProvider patchProvider
     */
    public function testPatch($url, $body, $expectedResponseFile)
    {
        $this->markTestSkipped("Awaiting PATCH analysis");

        $this->configRequest([
            'headers' => [
                'Accept' => 'application/vnd.api+json',
                'Content-Type' => 'application/vnd.api+json'
            ],
            'input' => json_encode($body)
        ]);

        # execute the PATCH request
        $this->patch($url);
        $this->assertResponseCode(200);
        $this->_assertJsonApiResponseHeaders();
        $this->assertResponseEquals($this->_getJsonApiResponseBody($expectedResponseFile));
    }
}
