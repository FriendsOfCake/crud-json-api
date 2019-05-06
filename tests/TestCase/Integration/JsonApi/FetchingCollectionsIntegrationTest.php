<?php
namespace CrudJsonApi\Test\TestCase\Integration\JsonApi;

use CrudJsonApi\Test\TestCase\Integration\JsonApiBaseTestCase;

class FetchingCollectionsIntegrationTest extends JsonApiBaseTestCase
{
    /**
     * PhpUnit Data Provider for testing (only) successful GET requests.
     *
     * @return array
     */
    public function getProvider()
    {
        return [
            #
            # Test fetching a single-word collection.
            #
            'fetch-single-word-collection' => [
                '/countries',
                'get-countries-with-pagination.json'
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

        $this->assertResponseSameAsFile('FetchingCollections' . DS . $expectedResponseFile);
    }
}
