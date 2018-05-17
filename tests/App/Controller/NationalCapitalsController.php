<?php
namespace CrudJsonApi\Test\App\Controller;

use Cake\Controller\Controller;
use Cake\Event\Event;
use Crud\Controller\ControllerTrait;

/**
 * Test controller for JsonApiListener integration tests.
 */
class NationalCapitalsController extends Controller
{

    use ControllerTrait;

    public $components = [
        'RequestHandler',
        'Flash',
        'Crud.Crud' => [
            'actions' => [
                'Crud.Index',
                'Crud.Add',
                'Crud.Edit',
                'Crud.View',
                'Crud.Delete',
            ],
            'listeners' => [
                'CrudJsonApi.JsonApi',
                'CrudJsonApi.Pagination',
            ]
        ]
    ];
}
