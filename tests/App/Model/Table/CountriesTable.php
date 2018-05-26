<?php
namespace CrudJsonApi\Test\App\Model\Table;

use Cake\ORM\Table;
use Cake\Validation\Validator;

class CountriesTable extends Table
{
    public function initialize(array $config)
    {
        $this->addBehavior('Search.Search');

        $this->belongsTo('Currencies');
        $this->belongsTo('NationalCapitals');

        $this->hasMany('Cultures');
        $this->hasMany('NationalCities');

        // e.g. Italy has Vatican
        $this->hasMany('SubCountries', [
            'className' => 'Countries',
            'foreignKey' => 'supercountry_id',
            'propertyName' => 'subcountries' //not-default to mess a little
        ]);

        // e.g. Vatican has Italy
        $this->belongsTo('SuperCountries', [
            'className' => 'Countries',
            'foreignKey' => 'supercountry_id',
            'propertyName' => 'supercountry'
        ]);
    }

    public function validationDefault(Validator $validator)
    {
        // used for testing built-in validation rules/messages
        $validator
            ->requirePresence('name', 'create')
            ->notEmpty('name');

        // used for testing user-defined rules/messages
        $validator
            ->notEmpty('code')
            ->add('code', [
                'UPPERCASE_ONLY' => [
                    'rule' => ['custom', '/^([A-Z]+)+$/'],
                    'message' => "Field must be uppercase only"
                ]
            ]);

        return $validator;
    }

    // set up the search filter
    public function searchManager()
    {
        $searchManager = $this->behaviors()->Search->searchManager();
        $searchManager->like('filter', [
            'before' => true,
            'after' => true,
            'field' => [$this->aliasField('name')]
        ]);

        return $searchManager;
    }
}
