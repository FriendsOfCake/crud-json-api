<?php
namespace CrudJsonApi\Test\TestCase\Integration\JsonApi;

use Cake\Event\Event;
use Cake\Event\EventManager;
use CrudJsonApi\Test\TestCase\Integration\JsonApiBaseTestCase;

class InclusionIntegrationTest extends JsonApiBaseTestCase
{
    /**
     * @return array
     */
    public function inclusionProvider()
    {
        return [
            // assert single-word associations
            'include currency belongsTo plural' => [
                '/countries/1?include=currencies',
                'get-country-include-currency.json'
            ],
            'include currency belongsTo singular' => [
                '/countries/1?include=currency',
                'get-country-include-currency.json'
            ],
            'include culture hasMany' => [
                '/countries/1?include=cultures',
                'get-country-include-culture.json'
            ],
            'include currency and culture' => [
                '/countries/1?include=currencies,cultures',
                'get-country-include-currency-and-culture.json'
            ],
            'include currency and deep countries' => [
                '/countries/1?include=currencies.countries',
                'get-country-include-currency-and-countries.json'
            ],
            // assert multi-word associations
            'include national-capital belongsTo singular' => [
                '/countries/1?include=national-capital',
                'get-country-include-national-capital.json'
            ],
            'include national-capital belongsTo plural' => [
                '/countries/1?include=national-capitals',
                'get-country-include-national-capital.json'
            ],
            'include national-cities hasMany' => [
                '/countries/1?include=national-cities',
                'get-country-include-national-cities.json'
            ],
            // assert all of the above in a single request
            'include all supported associations (singular belongsTo)' => [
                '/countries/1?include=currency,cultures,national-capital,national-cities',
                'get-country-include-all-supported-associations.json'
            ],
            'include all supported associations (plural belongsTo)' => [
                '/countries/1?include=currencies,cultures,national-capitals,national-cities',
                'get-country-include-all-supported-associations.json'
            ],
        ];
    }

    /**
     * @param string $url The endpoint to hit
     * @param string $expectedFile The file to find the expected result in
     * @return void
     * @dataProvider inclusionProvider
     */
    public function testInclusion($url, $expectedFile)
    {
        $this->get($url);

        $this->assertResponseSuccess();
        $this->_assertJsonApiResponseHeaders();
        $this->assertResponseSameAsFile('Inclusion' . DS . $expectedFile);
    }

    /**
     * @return void
     */
    public function testViewWithContain()
    {
        EventManager::instance()
            ->on('Crud.beforeFind', function (Event $event) {
                $event->getSubject()->query->contain([
                    'Currencies',
                    'Cultures',
                ]);
            });
        $this->get('/countries/1');

        $this->assertResponseSuccess();
        $this->assertResponseSameAsFile('Inclusion' . DS . 'get-country-include-currency-and-culture.json');
        EventManager::instance()->off('Crud.beforeFind');
    }

    /**
     * @return void
     */
    public function testViewInvalidInclude()
    {
        $this->get('/countries/1?include=donkey');

        $this->assertResponseError();
    }
}
