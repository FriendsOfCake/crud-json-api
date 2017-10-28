<?php
namespace CrudJsonApi\Test\TestCase\Listener;

use Cake\Controller\Controller;
use Cake\Core\Plugin;
use Cake\Event\Event;
use Cake\Filesystem\File;
use Cake\Network\Request;
use Cake\Network\Response;
use Cake\ORM\Query;
use Cake\ORM\TableRegistry;
use CrudJsonApi\Listener\PaginationListener;
use CrudJsonApi\Test\App\Model\Entity\Country;
use Crud\Event\Subject;
use Crud\TestSuite\TestCase;

/**
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 */
class PaginationListenerTest extends TestCase
{

    /**
     * fixtures property
     *
     * @var array
     */
    public $fixtures = [
        'plugin.CrudJsonApi.countries',
        'plugin.CrudJsonApi.cultures',
        'plugin.CrudJsonApi.currencies',
        'plugin.CrudJsonApi.national_capitals',
        'plugin.CrudJsonApi.national_cities',
    ];

    /**
     * Test implementedEvents with API request
     *
     * @return void
     */
    public function testImplementedEvents()
    {
        /*
        $expected = [
            'Crud.beforeRender' => ['callable' => 'beforeRender', 'priority' => 75]
        ];

        $this->assertSame($expected, $result);*/
        $this->markTestIncomplete('Not implemented yet.');
    }

    /**
     * Test beforeRender() method
     *
     * @return void
     */
    public function testBeforeRender()
    {
        $this->markTestIncomplete('Not implemented yet.');
    }

    /**
     * Test _getJsonApiPaginationResponse() method
     *
     * @return void
     */

    public function testGetJsonApiPaginationResponse()
    {
        $this->markTestIncomplete('Not implemented yet.');
    }
}
