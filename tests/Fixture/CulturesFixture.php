<?php
namespace CrudJsonApi\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

class CulturesFixture extends TestFixture
{

    public $fields = [
        'id' => ['type' => 'integer'],
        'code' => ['type' => 'string', 'length' => 5, 'null' => false],
        'name' => ['type' => 'string', 'length' => 100, 'null' => false],
        'another_dummy_counter' => ['type' => 'integer'],
        'country_id' => ['type' => 'integer', 'null' => false],
        '_constraints' => ['primary' => ['type' => 'primary', 'columns' => ['id']]],
    ];

    public $records = [
        ['code' => 'nl-NL', 'name' => 'Dutch', 'another_dummy_counter' => 11111, 'country_id' => 1],
        ['code' => 'nl-BE', 'name' => 'Dutch (Belgium)', 'another_dummy_counter' => 22222, 'country_id' => 2],
        ['code' => 'fr-BE', 'name' => 'French (Belgium)', 'another_dummy_counter' => 22222, 'country_id' => 2],
    ];
}
