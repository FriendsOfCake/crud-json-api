<?php
namespace CrudJsonApi\Test\App\Model\Table;

use Cake\Validation\Validator;

class NationalCitiesTable extends \Cake\ORM\Table
{
    public function initialize(array $config)
    {
        $this->belongsTo('Countries');
    }

    // prevent unintentionally creating hasMany records
    public function validationDefault(Validator $validator)
    {
        $validator
            ->requirePresence('name', 'create')
            ->notEmpty('name');

        return $validator;
    }
}
