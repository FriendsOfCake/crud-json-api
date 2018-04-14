<?php
namespace CrudJsonApi\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

class NationalCitiesFixture extends TestFixture
{

    public $fields = [
        'id' => ['type' => 'integer'],
        'name' => ['type' => 'string', 'length' => 100, 'null' => false],
        'country_id' => ['type' => 'integer', 'null' => false],
        '_constraints' => ['primary' => ['type' => 'primary', 'columns' => ['id']]],
    ];

    public $records = [
        ['name' => 'Amsterdam', 'country_id' => 1],
        ['name' => 'Rotterdam', 'country_id' => 1],
        ['name' => 'Sofia', 'country_id' => 2],
        ['name' => 'Plovdiv', 'country_id' => 2],
        ['name' => 'Eindhoven', 'country_id' => 1],
        ['name' => 'Nuenen', 'country_id' => 1],
    ];
}
