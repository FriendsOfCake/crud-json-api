<?php
namespace CrudJsonApi\Test\App\Model\Table;

class NationalCapitalsTable extends \Cake\ORM\Table
{
    public function initialize(array $config)
    {
        $this->hasMany('Countries');
    }
}
