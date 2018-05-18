<?php
namespace CrudJsonApi\Test\TestCase\Integration\JsonApi;

use CrudJsonApi\Test\TestCase\Integration\JsonApiBaseTestCase;

class PostRequestIntegrationTest extends JsonApiBaseTestCase
{
    /**
     * PhpUnit Data Provider for testing (only) successful POST requests.
     *
     * @return array
     */
    public function postProvider()
    {
        return [
            #
            # Test creating a single-word resource.
            #
            'post-single-word-collection' => [
                '/countries',
                [
                    'data' => [
                        'type' => 'countries',
                        'attributes' => [
                            'code' => 'NZ',
                            'name' => 'New Zealand',
                            'currency_id' => 1,
                            'national_capital_id' => 3
                        ]
                    ]
                ],
                'get-countries-with-pagination.json'
            ],
        ];
    }

    /**
     * @param string $url The endpoint to hit
     * @param string $body JSON API body in CakePHP array format
     * @param string $expectedResponseFile The file to find the expected jsonapi response in
     * @return void
     * @dataProvider postProvider
     */
    public function testPost($url, $body, $expectedResponseFile)
    {
        $this->configRequest([
            'headers' => [
                'Accept' => 'application/vnd.api+json',
                'Content-Type' => 'application/vnd.api+json'
            ],
            'input' => json_encode($body)
        ]);

        # execute the PATCH request
        $this->post($url);
        $this->assertResponseCode(201); # http://jsonapi.org/format/#crud-creating-responses-201
        $this->_assertJsonApiResponseHeaders();
        $this->assertResponseNotEmpty();
        #$this->assertResponseEquals($this->_getExpected('CreatingResources' . DS . 'post-country.json'));
    }
}
