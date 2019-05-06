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
            'update-single-word-resource-attributes-only-all' => [
                '/currencies/2', // URL
                'update-currency-attributes-only-all.json', // Fixtures/JsonApiRequestBodies/UpdatingResources
                'updated-currency-attributes-only-all.json', // Fixtures/JsonApiResponseBodies/UpdatingResources
                [
                    'id' => 2,
                    'code' => 'RUB', // updated
                    'name' => 'Russian Ruble' // updated
                ]
            ],

            # In this test, by leaving out the `code` attribute, we also assert this JSON API criteria:
            # "If a request does not include all of the attributes for a resource, the server MUST
            #  interpret the missing attributes as if they were included with their current values.
            #  The server MUST NOT interpret missing attributes as null values."
            'update-single-word-resource-attributes-only-single' => [
                '/currencies/2',
                'update-currency-attributes-only-single.json',
                'updated-currency-attributes-only-single.json',
                [
                    'id' => 2,
                    'code' => 'USD', // unchanged
                    'name' => 'Russian Ruble' // updated
                ]
            ],

            # Make sure `belongsTo` relationships gets updated when we pass both
            # `attributes` and `relationships` nodes to a single-word Resource.
            'update-single-word-resource-attributes-and-multiple-belongsto-relationships' => [
                '/countries/2',
                'update-country-attributes-and-multiple-belongsto-relationships.json',
                'updated-country-attributes-and-multiple-belongsto-relationships.json',
                [
                    'id' => 2,
                    'code' => 'JM', // updated
                    'name' => 'Jamaica', // updated
                    'dummy_counter' => 12345, // updated
                    'currency_id' => 2, // updated
                    'national_capital_id' => 5 // updated
                ]
            ],

            # Make sure `belongsTo` relationships get updated when we only pass the `relationships` node to
            # a single-word Resource (and thus the `attributes` node is absent in the POST request body)
            'update-single-word-resource-multiple-belongsto-relationships-only' => [
                '/countries/2',
                'update-country-multiple-belongsto-relationships-only.json',
                'updated-country-multiple-belongsto-relationships-only.json',
                [
                    'id' => 2,
                    'code' => 'BG', // unchanged
                    'name' => 'Bulgaria', // unchanged
                    'dummy_counter' => 22222, // unchanged
                    'currency_id' => 2, // updated
                    'national_capital_id' => 4 // updated
                ]
            ],

            # Make sure `hasMany` relationships get updated (here we provide
            # one culture so the result will contain only one culture, all
            # others are wiped out
            'update-single-word-resource-single-hasmany-relationship-only' => [
                '/countries/2',
                'update-country-set-single-hasmany-relationship.json',
                'updated-country-set-single-hasmany-relationship.json',
            ],

            # Make sure `hasMany` relationships get updated (here we provide
            # two cultures so the result will contain just those two cultures, all
            # others are wiped out
            'update-single-word-resource-set-multiple-hasmany-relationships' => [
                '/countries/2',
                'update-country-set-multiple-hasmany-relationships.json',
                'updated-country-set-multiple-hasmany-relationships.json',
            ],

            # Make sure we can update multi-word attributes
            'update-multi-word-resource-attributes-only' => [
                '/national-capitals/6',
                'update-national-capital-attributes-only.json',
                'updated-national-capital-attributes-only.json',
                [
                    'id' => 6,
                    'name' => 'Hollywood', // updated
                    'description' => 'National capital of the cinematic world' // updated
                ]
            ],

            # Make sure `belongsTo` relationships gets updated when we pass both
            # `attributes` and `relationships` nodes to a multi-word Resource.
            'update-multi-word-resource-single-belongsto-relationships' => [
                '/national-cities/2',
                'update-national-city-attributes-and-single-belongsto-relationship.json',
                'updated-national-city-attributes-and-single-belongsto-relationship.json',
                [
                    'id' => 2,
                    'name' => 'Milan', // updated
                    'country_id' => 3 // updated
                ]
            ],

            # Make sure `belongsTo` relationships get updated when we only pass the `relationships` node to
            # a multi-word Resource (and thus the `attributes` node is absent in the POST request body)
            'update-multi-word-resource-single-belongsto-relationships-only' => [
                '/national-cities/2',
                'update-national-city-single-belongsto-relationship-only.json',
                'updated-national-city-single-belongsto-relationship-only.json',
                [
                    'id' => 2,
                    'name' => 'Rotterdam', // unchanged
                    'country_id' => 3 // updated
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
    public function testUpdateResource($url, $requestBodyFile, $expectedResponseFile, $expectedRecordSubset = null)
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
        $this->assertResponseSameAsFile('UpdatingResources' . DS . $expectedResponseFile);

        # only check database if array is passed
        if (empty($expectedRecordSubset)) {
            return;
        }

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
