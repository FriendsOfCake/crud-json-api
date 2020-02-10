<?php
declare(strict_types=1);

namespace CrudJsonApi\Test\App\Model\Table;

use Cake\ORM\Table;

class CurrenciesTable extends Table
{
    public function initialize(array $config): void
    {
        $this->hasMany('Countries');
    }
}
