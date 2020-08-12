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
    public function viewProvider()
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
     * @dataProvider viewProvider
     */
    public function testView($url, $expectedFile)
    {
        $this->disableErrorHandlerMiddleware();
        $this->get($url);

        $this->assertResponseSuccess();
        $this->_assertJsonApiResponseHeaders();
        $this->assertResponseSameAsFile('Relationships' . DS . $expectedFile);
    }
}
