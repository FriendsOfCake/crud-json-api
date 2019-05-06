<?php
namespace CrudJsonApi\Listener;

use Cake\Event\Event;
use Cake\Routing\Router;
use Crud\Listener\ApiPaginationListener as BaseListener;

/**
 * When loaded Crud API Pagination Listener will include
 * pagination information in the response
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 */
class PaginationListener extends BaseListener
{

    /**
     * Returns a list of all events that will fire in the controller during its life-cycle.
     * You can override this function to add you own listener callbacks
     *
     * We attach at priority 10 so normal bound events can run before us
     *
     * @return array|null
     */
    public function implementedEvents()
    {
        if (!$this->_checkRequestType('jsonapi')) {
            return null;
        }

        return [
            'Crud.beforeRender' => ['callable' => 'beforeRender', 'priority' => 75]
        ];
    }

    /**
     * Appends the pagination information to the JSON or XML output
     *
     * @param \Cake\Event\Event $event Event
     * @return void
     */
    public function beforeRender(Event $event)
    {
        $request = $this->_request();

        if (empty($request->getParam('paging'))) {
            return;
        }

        $controller = $this->_controller();

        list(, $modelClass) = pluginSplit($controller->modelClass);

        if (!array_key_exists($modelClass, $request->getParam('paging'))) {
            return;
        }

        $pagination = $request->getParam('paging')[$modelClass];
        if (empty($pagination)) {
            return;
        }

        $controller->set('_pagination', $this->_getJsonApiPaginationResponse($pagination));
    }

    /**
     * Generates pagination viewVars with JSON API compatible hyperlinks.
     *
     * @param array $pagination CakePHP pagination result
     * @return array
     */
    protected function _getJsonApiPaginationResponse(array $pagination)
    {
        $defaultUrl = array_intersect_key($pagination, [
            'sort' => null,
            'page' => null,
            'limit' => null,
        ], $pagination);

        $request = $this->_request();
        $defaultUrl += [
            'include' => $request->getQuery('include'),
            'fields' => $request->getQuery('fields'),
            'filter' => $request->getQuery('filter'),
        ];

        if ($defaultUrl['sort'] === null && $request->getQuery('sort')) {
            $defaultUrl['sort'] = $request->getQuery('sort');
        }

        $fullBase = (bool)$this->_controller()->Crud->getConfig('listeners.jsonApi.absoluteLinks');

        $self = Router::url([
            'controller' => $this->_controller()->getName(),
            'action' => 'index',
            'page' => $pagination['page'],
            '_method' => 'GET',
        ] + $defaultUrl, $fullBase);

        $first = Router::url([
            'controller' => $this->_controller()->getName(),
            'action' => 'index',
            'page' => 1,
            '_method' => 'GET',
        ] + $defaultUrl, $fullBase);

        $last = Router::url([
            'controller' => $this->_controller()->getName(),
            'action' => 'index',
            'page' => $pagination['pageCount'],
            '_method' => 'GET',
        ] + $defaultUrl, $fullBase);

        $prev = null;
        if ($pagination['prevPage']) {
            $prev = Router::url([
                'controller' => $this->_controller()->getName(),
                'action' => 'index',
                'page' => $pagination['page'] - 1,
                '_method' => 'GET',
            ] + $defaultUrl, $fullBase);
        }

        $next = null;
        if ($pagination['nextPage']) {
            $next = Router::url([
                'controller' => $this->_controller()->getName(),
                'action' => 'index',
                'page' => $pagination['page'] + 1,
                '_method' => 'GET',
            ] + $defaultUrl, $fullBase);
        }

        return [
            'self' => $self,
            'first' => $first,
            'last' => $last,
            'prev' => $prev,
            'next' => $next,
            'record_count' => $pagination['count'],
            'page_count' => $pagination['pageCount'],
            'page_limit' => $pagination['limit'],
        ];
    }
}
