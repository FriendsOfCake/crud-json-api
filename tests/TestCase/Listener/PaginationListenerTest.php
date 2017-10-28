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
        $controller = $this
            ->getMockBuilder('\Cake\Controller\Controller')
            ->setMethods(['foobar'])
            ->disableOriginalConstructor()
            ->getMock();

        $controller->RequestHandler = $this->getMockBuilder('\Cake\Controller\Component\RequestHandlerComponent')
            ->setMethods(['config'])
            ->disableOriginalConstructor()
            ->getMock();

        $listener = $this
            ->getMockBuilder('\CrudJsonApi\Listener\JsonApiListener')
            ->setMethods(['setupDetectors', '_checkRequestType', '_controller'])
            ->disableOriginalConstructor()
            ->getMock();

        $listener
            ->expects($this->at(1))
            ->method('_checkRequestType')
            ->will($this->returnValue(false)); // for asserting missing JSON API Accept header

        $listener
            ->expects($this->at(3))
            ->method('_checkRequestType')
            ->will($this->returnValue(true)); // for asserting valid JSON API Accept header

        $listener
            ->expects($this->once())
            ->method('_controller')
            ->will($this->returnValue($controller));

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

    }

    /**
     * Test _getJsonApiPaginationResponse() method
     *
     * @return void
     */

    public function testGetJsonApiPaginationResponse()
    {

    }
}
