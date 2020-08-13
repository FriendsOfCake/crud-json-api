<?php
declare(strict_types=1);

namespace CrudJsonApi\Test\App\Model\Table;

use Cake\ORM\Table;

/**
 * Class LanguagesTable
 */
class LanguagesTable extends Table
{
    public function initialize(array $config): void
    {
        $this->belongsToMany('Countries');
    }
}
