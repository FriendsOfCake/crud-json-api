<?php
namespace Crud\Action\Bulk;

use Cake\Controller\Controller;
use Cake\Database\Expression\QueryExpression;
use Cake\Network\Exception\NotImplementedException;
use Cake\ORM\Query;

/**
 * Handles Bulk 'Toggle' Crud actions
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 */
class ToggleAction extends BaseAction
{
    /**
     * Constructor
     *
     * @param \Cake\Controller\Controller $Controller Controller instance
     * @param array $config Default settings
     * @return void
     */
    public function __construct(Controller $Controller, $config = [])
    {
        $this->_defaultConfig['actionName'] = 'Toggle';
        $this->_defaultConfig['messages'] = [
            'success' => [
                'text' => 'Value toggled successfully'
            ],
            'error' => [
                'text' => 'Could not toggle value'
            ]
        ];
        return parent::__construct($controller, $config);
    }

    /**
     * Handle a bulk event
     *
     * @return \Cake\Network\Response
     */
    protected function _handle()
    {
        $field = $this->config('field');
        if (empty($field)) {
            throw new NotImplementedException('No field value specified');
        }

        return parent::_handle();
    }

    /**
     * Handle a bulk toggle
     *
     * @param \Cake\ORM\Query $query The query to act upon
     * @return boolean
     */
    protected function _bulk(Query $query = null)
    {
        $field = $this->config('field');
        $expression = new QueryExpression(sprintf('%s = NOT %s', $field));
        $query->update()->set([$expression]);
        $statement = $query->execute();
        $statement->closeCursor();
        return $statement->rowCount();
    }
}
