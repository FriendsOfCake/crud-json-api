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

        if (empty($request->paging)) {
            return;
        }

        $controller = $this->_controller();

        list(, $modelClass) = pluginSplit($controller->modelClass);

        if (!array_key_exists($modelClass, $request->paging)) {
            return;
        }

        $pagination = $request->paging[$modelClass];
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
        $routerMethod = 'normalize'; // produce relative links

        if ($this->_controller()->Crud->config('listeners.jsonApi.absoluteLinks') === true) {
            $routerMethod = 'url'; // produce absolute links
        }

        $self = Router::$routerMethod([
            'controller' => $this->_controller()->name,
            'action' => 'index',
            'page' => $pagination['page'],
            '_method' => 'GET',
        ], true);

        $first = Router::$routerMethod([
            'controller' => $this->_controller()->name,
            'action' => 'index',
            'page' => 1,
            '_method' => 'GET',
        ], true);

        $last = Router::$routerMethod([
            'controller' => $this->_controller()->name,
            'action' => 'index',
            'page' => $pagination['pageCount'],
            '_method' => 'GET',
        ], true);

        $prev = null;
        if ($pagination['prevPage']) {
            $prev = Router::$routerMethod([
                'controller' => $this->_controller()->name,
                'action' => 'index',
                'page' => $pagination['page'] - 1,
                '_method' => 'GET',
            ], true);
        }

        $next = null;
        if ($pagination['nextPage']) {
            $next = Router::$routerMethod([
                'controller' => $this->_controller()->name,
                'action' => 'index',
                'page' => $pagination['page'] + 1,
                '_method' => 'GET',
            ], true);
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
