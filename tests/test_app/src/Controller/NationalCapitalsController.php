<?php
declare(strict_types=1);

namespace CrudJsonApi\Test\App\Controller;

use Cake\Controller\Controller;
use Crud\Controller\ControllerTrait;

/**
 * Test controller for JsonApiListener integration tests.
 */
class NationalCapitalsController extends Controller
{
    use ControllerTrait;

    public function initialize(): void
    {
        parent::initialize();

        $this->loadComponent('RequestHandler');
        $this->loadComponent('Flash');
        $this->loadComponent(
            'Crud.Crud',
            [
                'actions' => [
                    'Crud.Index',
                    'Crud.Add',
                    'Crud.Edit',
                    'CrudJsonApi.View',
                    'Crud.Delete',
                    'CrudJsonApi.Relationships',
                ],
                'listeners' => [
                    'CrudJsonApi.JsonApi',
                    'CrudJsonApi.Pagination',
                ],
            ]
        );
    }
}
