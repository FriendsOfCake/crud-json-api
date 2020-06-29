<?php
declare(strict_types=1);

namespace CrudJsonApi\Test\App\Controller;

use Cake\Controller\Controller;
use Crud\Controller\ControllerTrait;

/**
 * Test controller for JsonApiListener integration tests.
 */
class CurrenciesController extends Controller
{
    use ControllerTrait;

    public $paginate = ['limit' => 3];

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
                    'Crud.View',
                    'Crud.Delete',
                ],
                'listeners' => [
                    'CrudJsonApi.JsonApi',
                ],
            ]
        );
    }
}
