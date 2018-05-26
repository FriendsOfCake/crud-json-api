<?php
namespace CrudJsonApi\Test\TestCase\Integration\JsonApi;

use Cake\ORM\TableRegistry;
use Cake\Utility\Inflector;
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
            'update-single-word-resource-no-relationships-all-attributes' => [
                '/currencies/2', // URL
                'update-currency-no-relationships-all-attributes.json', // Fixtures/JsonApiRequestBodies/UpdatingResources
                'updated-currency-no-relationships-all-attributes.json', // Fixtures/JsonApiResponseBodies/UpdatingResources
                [
                    'id' => 2,
                    'code' => 'RUB', // array subset we expect to see in the database after PATCHing
                    'name' => 'Russian Ruble'
                ]
            ],

            # In this test, by leaving out the `code` attribute, we also assert this JSON API criteria:
            # "If a request does not include all of the attributes for a resource, the server MUST
            #  interpret the missing attributes as if they were included with their current values.
            #  The server MUST NOT interpret missing attributes as null values."
            'update-single-word-resource-no-relationships-single-attribute' => [
                '/currencies/2',
                'update-currency-no-relationships-single-attribute.json',
                'updated-currency-no-relationships-single-attribute.json',
                [
                    'id' => 2,
                    'code' => 'USD', // unchanged
                    'name' => 'Russian Ruble'
                ]
            ],

            'update-multi-word-resource-no-relationships' => [
                '/national-capitals/6',
                'update-national-capital-no-relationships.json',
                'updated-national-capital-no-relationships.json',
                [
                    'id' => 6,
                    'name' => 'Hollywood',
                    'description' => 'National capital of the cinematic world'
                ]
            ],

            # here we are changing both the name attribute (Rotterdam) and
            # the related country (NL) to Milan and Italy
            'update-multi-word-resource-single-belongsto-relationships' => [
                '/national-cities/2',
                'update-national-city-single-belongsto-relationship.json',
                'updated-national-city-single-belongsto-relationship.json',
                [
                    'id' => 2,
                    'name' => 'Milan',
                    'country_id' => 3
                ]
            ],

        ];
    }

    /**
     * @param string $url The endpoint to hit
     * @param string $body JSON API body in CakePHP array format
     * @param string $expectedResponseFile The file to find the expected jsonapi response in
     * @param array $expectedRecordSubset Array with key/values that MUST be present when looking up the PATCHed database record
     * @return void
     * @dataProvider updateResourceProvider
     */
    public function testUpdateResource($url, $requestBodyFile, $expectedResponseFile, $expectedRecordSubset)
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

        # generate variables required to retrieve updated database record
        preg_match('/\/(.+)\/(\d+|\d)/', $url, $matches);
        $tableName = Inflector::underscore($matches[1]);
        $tableName = Inflector::camelize($tableName);
        $recordId = $matches[2];

        # assert the database record got updated like expected
        $table = TableRegistry::get($tableName);
        $record = $table->get($recordId)->toArray();
        $this->assertArraySubset($expectedRecordSubset, $record);
    }
}
