<?php
declare(strict_types=1);

namespace CrudJsonApi\Listener;

use Cake\Event\EventInterface;
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
    public function implementedEvents(): array
    {
        if (!$this->_checkRequestType('jsonapi')) {
            return [];
        }

        return [
            'Crud.beforeRender' => ['callable' => 'beforeRender', 'priority' => 75],
        ];
    }

    /**
     * Appends the pagination information to the JSON or XML output
     *
     * @param  \Cake\Event\EventInterface $event Event
     * @return void
     */
    public function beforeRender(EventInterface $event): void
    {
        $paging = $this->_request()->getAttribute('paging');

        if (empty($paging)) {
            return;
        }

        $pagination = current($paging);
        if (empty($pagination)) {
            return;
        }

        $this->_controller->viewBuilder()->setOption('pagination', $this->_getJsonApiPaginationResponse($pagination));
    }

    /**
     * Generates pagination viewVars with JSON API compatible hyperlinks.
     *
     * @param  array $pagination CakePHP pagination result
     * @return array
     */
    protected function _getJsonApiPaginationResponse(array $pagination): array
    {
        $query = array_intersect_key(
            $pagination,
            [
            'sort' => null,
            'page' => null,
            'limit' => null,
            ],
            $pagination
        );

        $request = $this->_request();
        $query += [
            'include' => $request->getQuery('include'),
            'fields' => $request->getQuery('fields'),
            'filter' => $request->getQuery('filter'),
        ];

        if ($query['sort'] === null && $request->getQuery('sort')) {
            $query['sort'] = $request->getQuery('sort');
        }

        $fullBase = (bool)$this->_controller()->Crud->getConfig('listeners.jsonApi.absoluteLinks');

        $self = Router::url(
            [
            'controller' => $this->_controller()->getName(),
            'action' => 'index',
            '_method' => 'GET',
            '?' => ['page' => $pagination['page']] + $query
            ],
            $fullBase
        );

        $first = Router::url(
            [
            'controller' => $this->_controller()->getName(),
            'action' => 'index',
            '_method' => 'GET',
            '?' => ['page' => 1] + $query
            ],
            $fullBase
        );

        $last = Router::url(
            [
            'controller' => $this->_controller()->getName(),
            'action' => 'index',
            'page' => $pagination['pageCount'],
            '_method' => 'GET',
            '?' => ['page' => $pagination['pageCount']] + $query
            ],
            $fullBase
        );

        $prev = null;
        if ($pagination['prevPage']) {
            $prev = Router::url(
                [
                'controller' => $this->_controller()->getName(),
                'action' => 'index',
                '?' => ['page' => $pagination['page'] - 1] + $query,
                '_method' => 'GET',
                ],
                $fullBase
            );
        }

        $next = null;
        if ($pagination['nextPage']) {
            $next = Router::url(
                [
                'controller' => $this->_controller()->getName(),
                'action' => 'index',
                '?' => ['page' => $pagination['page'] + 1] + $query,
                '_method' => 'GET',
                ],
                $fullBase
            );
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
