<?php
namespace CrudJsonApi\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

class CountriesLanguagesFixture extends TestFixture
{
    public $fields = [
        'id' => ['type' => 'integer'],
        'country_id' => ['type' => 'integer', 'length' => 3, 'null' => false],
        'language_id' => ['type' => 'integer', 'length' => 100, 'null' => false],
        '_constraints' => ['primary' => ['type' => 'primary', 'columns' => ['id']]],
    ];

    public $records = [
        ['country_id' => 1, 'language_id' => 1],
        ['country_id' => 1, 'language_id' => 2],
        ['country_id' => 2, 'language_id' => 4],
        ['country_id' => 3, 'language_id' => 3],
        ['country_id' => 4, 'language_id' => 4],
        ['country_id' => 5, 'language_id' => 1],
    ];
}
