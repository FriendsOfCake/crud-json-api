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
        $listener = $this
            ->getMockBuilder('\CrudJsonApi\Listener\PaginationListener')
            ->setMethods(['_checkRequestType'])
            ->disableOriginalConstructor()
            ->getMock();

        $listener
            ->expects($this->at(0))
            ->method('_checkRequestType')
            ->will($this->returnValue(false)); // for asserting missing JSON API Accept header

        $listener
            ->expects($this->at(1))
            ->method('_checkRequestType')
            ->will($this->returnValue(true)); // for asserting valid JSON API Accept header

        // assert that listener does nothing if JSON API Accept header is missing
        $result = $listener->implementedEvents();

        $this->assertNull($result);

        // assert success if a JSON API Accept header is used
        $result = $listener->implementedEvents();

        $expected = [
            'Crud.beforeRender' => ['callable' => 'beforeRender', 'priority' => 75]
        ];

        $this->assertSame($expected, $result);
    }

    /**
     * Test beforeRender() method
     *
     * @return void
     */
    public function testBeforeRender()
    {
        $request_null = $this
            ->getMockBuilder('\Cake\Network\Request')
            ->setMethods(null)
            ->disableOriginalConstructor()
            ->getMock();

        $request_paging = $this
            ->getMockBuilder('\Cake\Network\Request')
            ->setMethods(null)
            ->disableOriginalConstructor()
            ->getMock();

        $controller = $this
            ->getMockBuilder('\Cake\Controller\Controller')
            ->setMethods(['set'])
            ->disableOriginalConstructor()
            ->getMock();

        $listener = $this
            ->getMockBuilder('\CrudJsonApi\Listener\PaginationListener')
            ->setMethods(['_controller', '_request'])
            ->disableOriginalConstructor()
            ->getMock();

        $listener
            ->expects($this->at(0))
            ->method('_request')
            ->will($this->returnValue($request_null));

        $listener->beforeRender(new Event('Crud.beforeRender'));

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
