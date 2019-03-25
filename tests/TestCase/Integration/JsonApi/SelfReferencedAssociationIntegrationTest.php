<?php
namespace CrudJsonApi\Test\TestCase\Integration\JsonApi;

use CrudJsonApi\Test\TestCase\Integration\JsonApiBaseTestCase;

class SelfReferencedAssociationIntegrationTest extends JsonApiBaseTestCase
{
    /**
     * @return array
     */
    public function viewProvider()
    {
        return [
            'get supercountry with subcountries' => [
                '/countries/3?include=subcountries',
                'get_supercountry_with_subcountries.json'
            ],
            'get subcountry with supercountry' => [
                '/countries/4?include=supercountries',
                'get_subcountry_with_supercountry.json'
            ]
        ];
    }

    /**
     * @param string $url The endpoint to hit
     * @param string $expectedFile The file to find the expected result in
     * @return void
     * @dataProvider viewProvider
     */
    public function testView($url, $expectedFile)
    {
        $this->get($url);

        $this->assertResponseSuccess();
        $this->_assertJsonApiResponseHeaders();
        $this->assertResponseSameAsFile($expectedFile);
    }
}
