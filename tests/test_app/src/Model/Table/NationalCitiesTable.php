<?php
declare(strict_types=1);

namespace CrudJsonApi\Test\App\Model\Table;

use Cake\ORM\Table;
use Cake\Validation\Validator;

class NationalCitiesTable extends Table
{
    public function initialize(array $config): void
    {
        $this->belongsTo('Countries');
    }

    // prevent unintentionally creating hasMany records
    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->requirePresence('name', 'create')
            ->notEmptyString('name');

        return $validator;
    }
}
