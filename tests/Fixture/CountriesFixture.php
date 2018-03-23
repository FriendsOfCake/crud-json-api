<?php
namespace CrudJsonApi\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

class CountriesFixture extends TestFixture
{

    public $fields = [
        'id' => ['type' => 'integer'],
        'code' => ['type' => 'string', 'length' => 2, 'null' => false],
        'name' => ['type' => 'string', 'length' => 255, 'null' => false],
        'dummy_counter' => ['type' => 'integer'],
        'currency_id' => ['type' => 'integer', 'null' => false],
        'national_capital_id' => ['type' => 'integer', 'null' => false],
        'supercountry_id' => ['type' => 'integer', 'null' => true],
        '_constraints' => ['primary' => ['type' => 'primary', 'columns' => ['id']]],
    ];

    public $records = [
        ['code' => 'NL', 'name' => 'The Netherlands', 'dummy_counter' => 11111, 'currency_id' => 1, 'national_capital_id' => 1],
        ['code' => 'BE', 'name' => 'Belgium', 'dummy_counter' => 22222, 'currency_id' => 1, 'national_capital_id' => 2],
        ['code' => 'IT', 'name' => 'Italy', 'dummy_counter' => 33333, 'currency_id' => 1, 'national_capital_id' => 4],
        ['code' => 'VT', 'name' => 'Vatican', 'dummy_counter' => 33333, 'currency_id' => 1, 'national_capital_id' => 5, 'supercountry_id' => 3],
    ];
}
