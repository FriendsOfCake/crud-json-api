<?php
namespace CrudJsonApi\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

class LanguagesFixture extends TestFixture
{
    public $fields = [
        'id' => ['type' => 'integer'],
        'code' => ['type' => 'string', 'length' => 2, 'null' => false],
        'name' => ['type' => 'string', 'length' => 100, 'null' => false],
        '_constraints' => ['primary' => ['type' => 'primary', 'columns' => ['id']]],
    ];

    public $records = [
        ['id' => 1, 'code' => 'en', 'name' => 'English'],
        ['id' => 2, 'code' => 'nl', 'name' => 'Dutch'],
        ['id' => 3, 'code' => 'it', 'name' => 'Italian'],
        ['id' => 4, 'code' => 'bg', 'name' => 'Bulgarian'],
    ];
}
