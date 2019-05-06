<?php
namespace CrudJsonApi\Test\TestCase\Listener;

use Cake\Controller\Controller;
use Cake\Core\Plugin;
use Cake\Event\Event;
use Cake\Filesystem\File;
use Cake\Http\Response;
use Cake\Http\ServerRequest;
use Cake\ORM\Query;
use Cake\ORM\TableRegistry;
use CrudJsonApi\Listener\JsonApiListener;
use CrudJsonApi\Test\App\Model\Entity\Country;
use Crud\Event\Subject;
use Crud\TestSuite\TestCase;

/**
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 */
class JsonApiListenerTest extends TestCase
{

    /**
     * Path to directory holding the JSON API documents to be tested against the Decoder
     *
     * @var
     */
    protected $_JsonApiDecoderFixtures;

    /**
     * fixtures property
     *
     * @var array
     */
    public $fixtures = [
        'plugin.CrudJsonApi.Countries',
        'plugin.CrudJsonApi.Cultures',
        'plugin.CrudJsonApi.Currencies',
        'plugin.CrudJsonApi.NationalCapitals',
        'plugin.CrudJsonApi.NationalCities',
    ];

    /**
     * setUp().
     */
    public function setUp()
    {
        parent::setUp();

        $this->_JsonApiDecoderFixtures = Plugin::path('Crud') . 'tests' . DS . 'Fixture' . DS . 'JsonApiDecoder';
    }

    /**
     * Make sure we are testing with expected default configuration values.
     */
    public function testDefaultConfig()
    {
        $listener = new JsonApiListener(new Controller());

        $expected = [
            'detectors' => [
                'jsonapi' => ['ext' => false, 'accepts' => 'application/vnd.api+json'],
            ],
            'exception' => [
                'type' => 'default',
                'class' => 'Cake\Http\Exception\BadRequestException',
                'message' => 'Unknown error',
                'code' => 0,
            ],
            'exceptionRenderer' => 'CrudJsonApi\Error\JsonApiExceptionRenderer',
            'setFlash' => false,
            'withJsonApiVersion' => false,
            'meta' => [],
            'links' => [],
            'absoluteLinks' => false,
            'jsonApiBelongsToLinks' => false,
            'jsonOptions' => [],
            'debugPrettyPrint' => true,
            'include' => [],
            'fieldSets' => [],
            'docValidatorAboutLinks' => false,
            'queryParameters' => [
                'include' => [
                    'whitelist' => true,
                    'blacklist' => false
                ]
            ],
            'inflect' => 'dasherize'
        ];

        $this->assertSame($expected, $listener->getConfig());
    }

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

        $this->assertInternalType('array', $result);

        // assert success if a JSON API Accept header is used
        $result = $listener->implementedEvents();

        $expected = [
            'Crud.beforeHandle' => ['callable' => [$listener, 'beforeHandle'], 'priority' => 10],
            'Crud.setFlash' => ['callable' => [$listener, 'setFlash'], 'priority' => 5],
            'Crud.beforeSave' => ['callable' => [$listener, 'beforeSave'], 'priority' => 20],
            'Crud.afterSave' => ['callable' => [$listener, 'afterSave'], 'priority' => 90],
            'Crud.afterDelete' => ['callable' => [$listener, 'afterDelete'], 'priority' => 90],
            'Crud.beforeRender' => ['callable' => [$listener, 'respond'], 'priority' => 100],
            'Crud.beforeRedirect' => ['callable' => [$listener, 'beforeRedirect'], 'priority' => 100],
            'Crud.beforePaginate' => ['callable' => [$listener, 'beforeFind'], 'priority' => 10],
            'Crud.beforeFind' => ['callable' => [$listener, 'beforeFind'], 'priority' => 10],
            'Crud.afterFind' => ['callable' => [$listener, 'afterFind'], 'priority' => 50],
            'Crud.afterPaginate' => ['callable' => [$listener, 'afterFind'], 'priority' => 50],
        ];

        $this->assertSame($expected, $result);
    }

    /**
     * Test beforeHandle() method
     *
     * @return void
     */
    public function testBeforeHandle()
    {
        $controller = $this
            ->getMockBuilder('\Cake\Controller\Controller')
            ->setMethods(['_request'])
            ->disableOriginalConstructor()
            ->getMock();

        $controller->request = $this
            ->getMockBuilder('\Cake\Http\ServerRequest')
            ->setMethods(null)
            ->disableOriginalConstructor()
            ->getMock();

        $controller->request = $controller->request->withData('data', [
            'type' => 'dummy',
            'attributes' => [],
        ]);

        $listener = $this
            ->getMockBuilder('\CrudJsonApi\Listener\JsonApiListener')
            ->setMethods(['_controller', '_checkRequestMethods', '_convertJsonApiDataArray', '_checkRequestData'])
            ->disableOriginalConstructor()
            ->getMock();

        $listener
            ->expects($this->any())
            ->method('_controller')
            ->will($this->returnValue($controller));

        $listener
            ->expects($this->any())
            ->method('_convertJsonApiDataArray')
            ->will($this->returnValue(true));

        $listener
            ->expects($this->any())
            ->method('_checkRequestMethods')
            ->will($this->returnValue(true));

        $listener
            ->expects($this->any())
            ->method('_checkRequestData')
            ->will($this->returnValue(true));

            $this->assertNull($listener->beforeHandle(new Event('Crud.beforeHandle')));
    }

    /**
     * Test afterSave event.
     *
     * @return void
     */
    public function testAfterSave()
    {
        $listener = $this
            ->getMockBuilder('\CrudJsonApi\Listener\JsonApiListener')
            ->disableOriginalConstructor()
            ->setMethods(['_controller', '_response', 'render'])
            ->getMock();

        $controller = $this
            ->getMockBuilder('\Cake\Controller\Controller')
            ->setMethods(null)
            ->getMock();

        $response = $this
            ->getMockBuilder('\Cake\Http\Response')
            ->disableOriginalConstructor()
            ->setMethods(null)
            ->getMock();

        $listener
            ->expects($this->any())
            ->method('_response')
            ->will($this->returnValue($response));

        $listener
            ->expects($this->any())
            ->method('_controller')
            ->will($this->returnValue($controller));

        $listener
            ->expects($this->any())
            ->method('render')
            ->will($this->returnValue(null));

        $event = $this
            ->getMockBuilder('\Cake\Event\Event')
            ->disableOriginalConstructor()
            ->setMethods(['getSubject'])
            ->getMock();

        $subject = $this
            ->getMockBuilder('\Crud\Event\Subject')
            ->disableOriginalConstructor()
            ->setMethods(null)
            ->getMock();

        $event
            ->expects($this->any())
            ->method('getSubject')
            ->will($this->returnValue($subject));

        $this->setReflectionClassInstance($listener);

        // assert nothing happens if `success` is false
        $event->getSubject()->success = false;
        $this->assertFalse($this->callProtectedMethod('afterSave', [$event], $listener));

        // assert nothing happens if `success` is true but both `created` and `id` are false
        $event->getSubject()->success = true;
        $event->getSubject()->created = false;
        $event->getSubject()->id = false;
        $this->assertFalse($this->callProtectedMethod('afterSave', [$event], $listener));

        // assert success
        $table = TableRegistry::get('Countries');
        $entity = $table->find()->first();
        $subject->entity = $entity;

        $event->getSubject()->success = true;
        $event->getSubject()->created = true;
        $event->getSubject()->id = false;
        $this->assertNull($this->callProtectedMethod('afterSave', [$event], $listener));

        $event->getSubject()->success = true;
        $event->getSubject()->created = false;
        $event->getSubject()->id = true;
        $this->assertNull($this->callProtectedMethod('afterSave', [$event], $listener));
    }

    /**
     * Test afterDelete event.
     *
     * @return void
     */
    public function testAfterDelete()
    {
        $listener = $this
            ->getMockBuilder('\CrudJsonApi\Listener\JsonApiListener')
            ->disableOriginalConstructor()
            ->setMethods(['_controller', '_response'])
            ->getMock();

        $controller = $this
            ->getMockBuilder('\Cake\Controller\Controller')
            ->disableOriginalConstructor()
            ->setMethods(null)
            ->getMock();

        $response = $this
            ->getMockBuilder('\Cake\Http\Response')
            ->disableOriginalConstructor()
            ->setMethods(null)
            ->getMock();

        $controller->response = $response;

        $listener
            ->expects($this->any())
            ->method('_response')
            ->will($this->returnValue($response));

        $listener
            ->expects($this->any())
            ->method('_controller')
            ->will($this->returnValue($controller));

        $event = $this
            ->getMockBuilder('\Cake\Event\Event')
            ->disableOriginalConstructor()
            ->setMethods(['getSubject'])
            ->getMock();

        $subject = $this
            ->getMockBuilder('\Crud\Event\Subject')
            ->disableOriginalConstructor()
            ->setMethods(null)
            ->getMock();

        $event
            ->expects($this->any())
            ->method('getSubject')
            ->will($this->returnValue($subject));

        $this->setReflectionClassInstance($listener);

        // assert nothing happens if `success` is false
        $event->getSubject()->success = false;
        $this->assertFalse($this->callProtectedMethod('afterDelete', [$event], $listener));

        $event->getSubject()->success = true;
        $this->assertNull($this->callProtectedMethod('afterDelete', [$event], $listener));
    }

    /**
     * Test beforeRedirect event.
     */
    public function testBeforeRedirect()
    {
        $listener = $this
            ->getMockBuilder('\CrudJsonApi\Listener\JsonApiListener')
            ->disableOriginalConstructor()
            ->setMethods(null)
            ->getMock();

        $this->assertNull($listener->beforeRedirect(new Event('dogs')));
    }

    /**
     * Make sure render() works with find data
     *
     * @return void
     */
    public function testRenderWithResources()
    {
        $controller = $this
            ->getMockBuilder('\Cake\Controller\Controller')
            ->setMethods(null)
            ->enableOriginalConstructor()
            ->setConstructorArgs([null, null, 'Countries'])
            ->getMock();

        $listener = $this
            ->getMockBuilder('\CrudJsonApi\Listener\JsonApiListener')
            ->disableOriginalConstructor()
            ->setMethods(['_controller', '_action'])
            ->getMock();

        $listener
            ->expects($this->any())
            ->method('_controller')
            ->will($this->returnValue($controller));

        $subject = $this
            ->getMockBuilder('\Crud\Event\Subject')
            ->getMock();

        $query = $this
            ->getMockBuilder(Query::class)
            ->disableOriginalConstructor()
            ->getMock();

        $subject->entity = new Country();

        $this->assertInstanceOf('Cake\Http\Response', $listener->render($subject));
    }

    /**
     * Make sure render() works without find data
     *
     * @return void
     */
    public function testRenderWithoutResources()
    {
        $controller = $this
            ->getMockBuilder('\Cake\Controller\Controller')
            ->setMethods(null)
            ->enableOriginalConstructor()
            ->getMock();

        $listener = $this
            ->getMockBuilder('\CrudJsonApi\Listener\JsonApiListener')
            ->disableOriginalConstructor()
            ->setMethods(['_controller', '_action'])
            ->getMock();

        $listener
            ->expects($this->any())
            ->method('_controller')
            ->will($this->returnValue($controller));

        $subject = $this
            ->getMockBuilder('\Crud\Event\Subject')
            ->getMock();

            $this->assertInstanceOf('Cake\Http\Response', $listener->render($subject));
    }

    /**
     * Make sure config option `withJsonApiVersion` accepts a boolean
     *
     * @return void
     */
    public function testValidateConfigOptionWithJsonApiVersionSuccessWithBoolean()
    {
        $listener = $this
            ->getMockBuilder('\CrudJsonApi\Listener\JsonApiListener')
            ->disableOriginalConstructor()
            ->setMethods(null)
            ->getMock();

        $listener->setConfig([
            'withJsonApiVersion' => true
        ]);

        $this->setReflectionClassInstance($listener);
        $this->assertNull($this->callProtectedMethod('_validateConfigOptions', [], $listener));
    }

    /**
     * Make sure config option `withJsonApiVersion` accepts an array
     *
     * @return void
     */
    public function testValidateConfigOptionWithJsonApiVersionSuccessWithArray()
    {
        $listener = $this
            ->getMockBuilder('\CrudJsonApi\Listener\JsonApiListener')
            ->disableOriginalConstructor()
            ->setMethods(null)
            ->getMock();

        $listener->setConfig([
            'withJsonApiVersion' => ['array' => 'accepted']
        ]);

        $this->setReflectionClassInstance($listener);
        $this->assertNull($this->callProtectedMethod('_validateConfigOptions', [], $listener));
    }

    /**
     * Make sure config option `withJsonApiVersion` does not accept a string
     *
     * @expectedException \Crud\Error\Exception\CrudException
     * @expectedExceptionMessage JsonApiListener configuration option `withJsonApiVersion` only accepts a boolean or an array
     */
    public function testValidateConfigOptionWithJsonApiVersionFailWithString()
    {
        $listener = $this
            ->getMockBuilder('\CrudJsonApi\Listener\JsonApiListener')
            ->disableOriginalConstructor()
            ->setMethods(null)
            ->getMock();

        $listener->setConfig([
            'withJsonApiVersion' => 'string-not-accepted'
        ]);

        $this->setReflectionClassInstance($listener);
        $this->callProtectedMethod('_validateConfigOptions', [], $listener);
    }

    /**
     * Make sure config option `meta` accepts an array
     *
     * @return void
     */
    public function testValidateConfigOptionMetaSuccessWithArray()
    {
        $listener = $this
            ->getMockBuilder('\CrudJsonApi\Listener\JsonApiListener')
            ->disableOriginalConstructor()
            ->setMethods(null)
            ->getMock();

        $listener->setConfig([
            'meta' => ['array' => 'accepted']
        ]);

        $this->setReflectionClassInstance($listener);
        $this->assertNull($this->callProtectedMethod('_validateConfigOptions', [], $listener));
    }

    /**
     * Make sure config option `meta` does not accept a string
     *
     * @expectedException \Crud\Error\Exception\CrudException
     * @expectedExceptionMessage JsonApiListener configuration option `meta` only accepts an array
     */
    public function testValidateConfigOptionMetaFailWithString()
    {
        $listener = $this
            ->getMockBuilder('\CrudJsonApi\Listener\JsonApiListener')
            ->disableOriginalConstructor()
            ->setMethods(null)
            ->getMock();

        $listener->setConfig([
            'meta' => 'string-not-accepted'
        ]);

        $this->setReflectionClassInstance($listener);
        $this->callProtectedMethod('_validateConfigOptions', [], $listener);
    }

    /**
     * Make sure config option `absoluteLinks` accepts a boolean
     *
     * @return void
     */
    public function testValidateConfigOptionAbsoluteLinksSuccessWithBoolean()
    {
        $listener = $this
            ->getMockBuilder('\CrudJsonApi\Listener\JsonApiListener')
            ->disableOriginalConstructor()
            ->setMethods(null)
            ->getMock();

        $listener->setConfig([
            'absoluteLinks' => true
        ]);

        $this->setReflectionClassInstance($listener);
        $this->assertNull($this->callProtectedMethod('_validateConfigOptions', [], $listener));
    }

    /**
     * Make sure config option `absoluteLinks` does not accept a string
     *
     * @expectedException \Crud\Error\Exception\CrudException
     * @expectedExceptionMessage JsonApiListener configuration option `absoluteLinks` only accepts a boolean
     */
    public function testValidateConfigOptionAbsoluteLinksFailsWithString()
    {
        $listener = $this
            ->getMockBuilder('\CrudJsonApi\Listener\JsonApiListener')
            ->disableOriginalConstructor()
            ->setMethods(null)
            ->getMock();

        $listener->setConfig([
            'absoluteLinks' => 'string-not-accepted'
        ]);

        $this->setReflectionClassInstance($listener);
        $this->callProtectedMethod('_validateConfigOptions', [], $listener);
    }

    /**
     * Make sure config option `jsonApiBelongsToLinks` accepts a boolean
     *
     * @return void
     */
    public function testValidateConfigOptionJsonApiBelongsToLinksSuccessWithBoolean()
    {
        $listener = $this
            ->getMockBuilder('\CrudJsonApi\Listener\JsonApiListener')
            ->disableOriginalConstructor()
            ->setMethods(null)
            ->getMock();

        $listener->setConfig([
            'jsonApiBelongsToLinks' => true
        ]);

        $this->setReflectionClassInstance($listener);
        $this->assertNull($this->callProtectedMethod('_validateConfigOptions', [], $listener));
    }

    /**
     * Make sure config option `jsonApiBelongsToLinks` does not accept a string
     *
     * @expectedException \Crud\Error\Exception\CrudException
     * @expectedExceptionMessage JsonApiListener configuration option `jsonApiBelongsToLinks` only accepts a boolean
     */
    public function testValidateConfigOptionJsonApiBelongsToLinksFailsWithString()
    {
        $listener = $this
            ->getMockBuilder('\CrudJsonApi\Listener\JsonApiListener')
            ->disableOriginalConstructor()
            ->setMethods(null)
            ->getMock();

        $listener->setConfig([
            'jsonApiBelongsToLinks' => 'string-not-accepted'
        ]);

        $this->setReflectionClassInstance($listener);
        $this->callProtectedMethod('_validateConfigOptions', [], $listener);
    }

    /**
     * Make sure config option `include` does not accept a string
     *
     * @expectedException \Crud\Error\Exception\CrudException
     * @expectedExceptionMessage JsonApiListener configuration option `include` only accepts an array
     */
    public function testValidateConfigOptionIncludeFailWithString()
    {
        $listener = $this
            ->getMockBuilder('\CrudJsonApi\Listener\JsonApiListener')
            ->disableOriginalConstructor()
            ->setMethods(null)
            ->getMock();

        $listener->setConfig([
            'include' => 'string-not-accepted'
        ]);

        $this->setReflectionClassInstance($listener);
        $this->callProtectedMethod('_validateConfigOptions', [], $listener);
    }

    /**
     * Make sure config option `fieldSets` does not accept a string
     *
     * @expectedException \Crud\Error\Exception\CrudException
     * @expectedExceptionMessage JsonApiListener configuration option `fieldSets` only accepts an array
     */
    public function testValidateConfigOptionFieldSetsFailWithString()
    {
        $listener = $this
            ->getMockBuilder('\CrudJsonApi\Listener\JsonApiListener')
            ->disableOriginalConstructor()
            ->setMethods(null)
            ->getMock();

        $listener->setConfig([
            'fieldSets' => 'string-not-accepted'
        ]);

        $this->setReflectionClassInstance($listener);
        $this->callProtectedMethod('_validateConfigOptions', [], $listener);
    }

    /**
     * Make sure config option `jsonOptions` does not accept a string
     *
     * @expectedException \Crud\Error\Exception\CrudException
     * @expectedExceptionMessage JsonApiListener configuration option `jsonOptions` only accepts an array
     */
    public function testValidateConfigOptionJsonOptionsFailWithString()
    {
        $listener = $this
            ->getMockBuilder('\CrudJsonApi\Listener\JsonApiListener')
            ->disableOriginalConstructor()
            ->setMethods(null)
            ->getMock();

        $listener->setConfig([
            'jsonOptions' => 'string-not-accepted'
        ]);

        $this->setReflectionClassInstance($listener);
        $this->callProtectedMethod('_validateConfigOptions', [], $listener);
    }

    /**
     * Make sure config option `debugPrettyPrint` does not accept a string
     *
     * @expectedException \Crud\Error\Exception\CrudException
     * @expectedExceptionMessage JsonApiListener configuration option `debugPrettyPrint` only accepts a boolean
     */
    public function testValidateConfigOptionDebugPrettyPrintFailWithString()
    {
        $listener = $this
            ->getMockBuilder('\CrudJsonApi\Listener\JsonApiListener')
            ->disableOriginalConstructor()
            ->setMethods(null)
            ->getMock();

        $listener->setConfig([
            'debugPrettyPrint' => 'string-not-accepted'
        ]);

        $this->setReflectionClassInstance($listener);
        $this->callProtectedMethod('_validateConfigOptions', [], $listener);
    }

    /**
     * Make sure config option `queryParameters` does not accept a string
     *
     * @expectedException \Crud\Error\Exception\CrudException
     * @expectedExceptionMessage JsonApiListener configuration option `queryParameters` only accepts an array
     */
    public function testValidateConfigOptionQueryParametersPrintFailWithString()
    {
        $listener = $this->getMockBuilder('\CrudJsonApi\Listener\JsonApiListener')
            ->disableOriginalConstructor()
            ->setMethods(null)
            ->getMock();

        $listener->setConfig([
            'queryParameters' => 'string-not-accepted'
        ]);

        $this->setReflectionClassInstance($listener);
        $this->callProtectedMethod('_validateConfigOptions', [], $listener);
    }
    /**
     * Make sure the listener accepts the correct request headers
     *
     * @return void
     */
    public function testCheckRequestMethodsSuccess()
    {
        $request = new ServerRequest();
        $request = $request->withEnv('HTTP_ACCEPT', 'application/vnd.api+json');
        $response = new Response();
        $controller = new Controller($request, $response);
        $listener = new JsonApiListener($controller);
        $listener->setupDetectors();

        $this->setReflectionClassInstance($listener);
        $this->callProtectedMethod('_checkRequestMethods', [], $listener);

        $request = new ServerRequest();
        $request = $request->withEnv('HTTP_ACCEPT', 'application/vnd.api+json')
            ->withEnv('CONTENT_TYPE', 'application/vnd.api+json');
        $response = new Response();
        $controller = new Controller($request, $response);
        $listener = new JsonApiListener($controller);
        $listener->setupDetectors();

        $this->setReflectionClassInstance($listener);
        $this->assertTrue($this->callProtectedMethod('_checkRequestMethods', [], $listener));
    }

    /**
     * Make sure the listener fails on non JSON API request Content-Type header
     *
     * @expectedException \Cake\Http\Exception\BadRequestException
     * @expectedExceptionMessage JSON API requests with data require the "application/vnd.api+json" Content-Type header
     */
    public function testCheckRequestMethodsFailContentHeader()
    {
        $request = new ServerRequest();
        $request = $request->withEnv('HTTP_ACCEPT', 'application/vnd.api+json')
            ->withEnv('CONTENT_TYPE', 'application/json');
        $response = new Response();
        $controller = new Controller($request, $response);
        $listener = new JsonApiListener($controller);
        $listener->setupDetectors();

        $this->setReflectionClassInstance($listener);
        $this->callProtectedMethod('_checkRequestMethods', [], $listener);
    }

    /**
     * Make sure the listener does not accept the PUT method (since the JSON
     * API spec only supports PATCH)
     *
     * @expectedException \Cake\Http\Exception\BadRequestException
     * @expectedExceptionMessage JSON API does not support the PUT method, use PATCH instead
     */
    public function testCheckRequestMethodsFailOnPutMethod()
    {
        $request = new ServerRequest();
        $request = $request->withEnv('HTTP_ACCEPT', 'application/vnd.api+json')
            ->withEnv('REQUEST_METHOD', 'PUT');
        $response = new Response();
        $controller = new Controller($request, $response);
        $listener = new JsonApiListener($controller);
        $listener->setupDetectors();

        $this->setReflectionClassInstance($listener);
        $this->callProtectedMethod('_checkRequestMethods', [], $listener);
    }

    /**
     * Make sure correct find data is returned from subject based on action
     *
     * @return void
     */
    public function testGetFindResult()
    {
        $listener = $this
            ->getMockBuilder('\CrudJsonApi\Listener\JsonApiListener')
            ->disableOriginalConstructor()
            ->setMethods(['_controller'])
            ->getMock();

        $this->setReflectionClassInstance($listener);

        $subject = new Subject();
        $subject->entities = 'return-entities-property-from-subject-if-set';
        $result = $this->callProtectedMethod('_getFindResult', [$subject], $listener);
        $this->assertSame('return-entities-property-from-subject-if-set', $result);

        unset($subject->entities);

        $subject->entities = 'return-entity-property-from-subject-if-set';
        $result = $this->callProtectedMethod('_getFindResult', [$subject], $listener);
        $this->assertSame('return-entity-property-from-subject-if-set', $result);
    }

    /**
     * Make sure single/first entity is returned from subject based on action
     *
     * @return void
     */
    public function testGetSingleEntity()
    {
        $controller = $this
            ->getMockBuilder('\Cake\Controller\Controller')
            ->setMethods(null)
            ->enableOriginalConstructor()
            ->getMock();

        $listener = $this
            ->getMockBuilder('\CrudJsonApi\Listener\JsonApiListener')
            ->disableOriginalConstructor()
            ->setMethods(['_controller', '_event'])
            ->getMock();

        $listener
            ->expects($this->any())
            ->method('_controller')
            ->will($this->returnValue($controller));

        $subject = $this
            ->getMockBuilder('\Crud\Event\Subject')
            ->getMock();

        $subject->entities = $this
            ->getMockBuilder('stdClass')
            ->disableOriginalConstructor()
            ->setMethods(['first'])
            ->getMock();

        $subject->entities
            ->expects($this->any())
            ->method('first')
            ->will($this->returnValue('return-first-entity-if-entities-property-is-set'));

        $this->setReflectionClassInstance($listener);
        $result = $this->callProtectedMethod('_getSingleEntity', [$subject], $listener);
        $this->assertSame('return-first-entity-if-entities-property-is-set', $result);

        unset($subject->entities);

        $subject->entity = 'return-entity-property-from-subject-if-set';
        $this->setReflectionClassInstance($listener);
        $result = $this->callProtectedMethod('_getSingleEntity', [$subject], $listener);
        $this->assertSame($subject->entity, $result);
    }

    /**
     * Make sure associations not present in the find result are stripped
     * from the AssociationCollection. In this test we will remove associated
     * model `Cultures`.
     *
     * @return void
     */
    public function testGetContainedAssociations()
    {
        $table = TableRegistry::get('Countries');
        $table->belongsTo('Currencies');
        $table->hasMany('Cultures');

        // make sure expected associations are there
        $associationsBefore = $table->associations();
        $this->assertNotEmpty($associationsBefore->get('currencies'));
        $this->assertNotEmpty($associationsBefore->get('cultures'));

        // make sure cultures are not present in the find result
        $query = $table->find()->contain([
            'Currencies'
        ]);
        $entity = $query->first();

        $this->assertNotEmpty($entity->currency);
        $this->assertNull($entity->cultures);

        // make sure cultures are removed from AssociationCollection
        $listener = new JsonApiListener(new Controller());
        $this->setReflectionClassInstance($listener);
        $associationsAfter = $this->callProtectedMethod('_getContainedAssociations', [$table, $query->getContain()], $listener);

        $this->assertNotEmpty($associationsAfter['currencies']);
        $this->assertArrayNotHasKey('cultures', $associationsAfter);
    }

    /**
     * Make sure we get a list of repository names for the current entity (name
     * passed as string) and all associated models.
     *
     * @return void
     */
    public function testGetRepositoryList()
    {
        $table = TableRegistry::get('Countries');
        $table->belongsTo('Currencies');
        $table->belongsTo('NationalCapitals');
        $table->hasMany('Cultures');
        $table->hasMany('NationalCities');

        $table->hasMany('SubCountries', [
            'className' => 'Countries',
            'propertyName' => 'subcountry'
        ]);

        $table->belongsTo('SuperCountries', [
            'className' => 'Countries',
            'propertyName' => 'supercountry'
        ]);

        $associations = [];
        foreach ($table->associations() as $association) {
            $associations[strtolower($association->getName())] = [
                'association' => $association,
                'children' => []
            ];
        }

        $associations['currencies']['children'] = [
            'countries' => [
                'association' => $table->Currencies->Countries,
            ]
        ];

        $this->assertArrayHasKey('currencies', $associations);
        $this->assertArrayHasKey('cultures', $associations);

        $listener = new JsonApiListener(new Controller());
        $this->setReflectionClassInstance($listener);
        $result = $this->callProtectedMethod('_getRepositoryList', [$table, $associations], $listener);

        $expected = [
            'Countries' => $table,
            'Currencies' => $table->Currencies->getTarget(),
            'NationalCapitals' => $table->NationalCapitals->getTarget(),
            'Cultures' => $table->Cultures->getTarget(),
            'NationalCities' => $table->NationalCities->getTarget(),
            'SubCountries' => $table->SubCountries->getTarget(),
            'SuperCountries' => $table->SuperCountries->getTarget()
        ];

        $this->assertSame($expected, $result);
    }

    /**
     * _getIncludeList()
     *
     * @return void
     */
    public function testGetIncludeList()
    {
        $listener = $this
            ->getMockBuilder('\CrudJsonApi\Listener\JsonApiListener')
            ->disableOriginalConstructor()
            ->setMethods(['_controller', '_event'])
            ->getMock();

        $this->setReflectionClassInstance($listener);

        // assert the include list is auto-generated for both belongsTo and
        // hasMany relations (if listener config option `include` is not set)
        $this->assertEmpty($listener->getConfig('include'));

        $table = TableRegistry::get('Countries');
        $associations = [];
        foreach ($table->associations() as $association) {
            $associations[strtolower($association->getName())] = [
                'association' => $association,
                'children' => []
            ];
        }

        $associations['currencies']['children'] = [
            'countries' => [
                'association' => $table->Currencies->Countries,
            ]
        ];

        $expected = [
            'currency.countries',
            'national-capital',
            'cultures',
            'national-cities',
            'subcountries',
            'supercountry'
        ];
        $result = $this->callProtectedMethod('_getIncludeList', [$associations], $listener);
        $this->assertSame($expected, $result);

        unset($associations['currencies']['children']['countries']);
        $this->assertSame(['currencies', 'nationalcapitals', 'cultures', 'nationalcities', 'subcountries', 'supercountries'], array_keys($associations));

        $result = $this->callProtectedMethod('_getIncludeList', [$associations], $listener);
        $this->assertSame(['currency', 'national-capital', 'cultures', 'national-cities', 'subcountries', 'supercountry'], $result);

        // assert the include list is still auto-generated if an association is
        // removed from the AssociationsCollection
        unset($associations['cultures']);
        $this->assertSame(['currencies', 'nationalcapitals', 'nationalcities', 'subcountries', 'supercountries'], array_keys($associations));

        $result = $this->callProtectedMethod('_getIncludeList', [$associations], $listener);
        $this->assertSame(['currency', 'national-capital', 'national-cities', 'subcountries', 'supercountry'], $result);

        // assert user specified listener config option is returned as-is (no magic)
        $userSpecifiedIncludes = [
            'user-specified-list',
            'with',
            'associations-to-present-in-included-node'
        ];

        $listener->setConfig('include', $userSpecifiedIncludes);
        $this->assertNotEmpty($listener->getConfig('include'));
        $result = $this->callProtectedMethod('_getIncludeList', [$associations], $listener);

        $this->assertSame($userSpecifiedIncludes, $result);
    }

    /**
     * _checkRequestData()
     *
     * @return void
     * @expectedException \Cake\Http\Exception\BadRequestException
     * @expectedExceptionMessage Missing request data required for POST and PATCH methods. Make sure that you are sending a request body and that it is valid JSON.
     */
    public function testCheckRequestData()
    {
        $controller = $this
            ->getMockBuilder('\Cake\Controller\Controller')
            ->setMethods(['_request'])
            ->disableOriginalConstructor()
            ->getMock();

        $request = $this
            ->getMockBuilder('\Cake\Http\ServerRequest')
            ->setMethods(['contentType', 'getMethod'])
            ->disableOriginalConstructor()
            ->getMock();

        $request
            ->expects($this->at(0))
            ->method('getMethod')
            ->will($this->returnValue('GET'));

        $request
            ->expects($this->at(1))
            ->method('getMethod')
            ->will($this->returnValue('POST'));

        $request
            ->expects($this->at(2))
            ->method('getMethod')
            ->will($this->returnValue('POST'));

        $request
            ->expects($this->at(3))
            ->method('getMethod')
            ->will($this->returnValue('PATCH'));

        $controller->request = $request;

        $listener = $this
            ->getMockBuilder('\CrudJsonApi\Listener\JsonApiListener')
            ->setMethods(['_controller', '_checkRequestMethods', '_convertJsonApiDataArray'])
            ->disableOriginalConstructor()
            ->getMock();

        $listener
            ->expects($this->any())
            ->method('_controller')
            ->will($this->returnValue($controller));

        $listener
            ->expects($this->any())
            ->method('_convertJsonApiDataArray')
            ->will($this->returnValue(true));

        $listener
            ->expects($this->any())
            ->method('_checkRequestMethods')
            ->will($this->returnValue(true));

        $this->setReflectionClassInstance($listener);

        // assert null if there is no Content-Type
        $this->assertNull($this->callProtectedMethod('_checkRequestData', [], $listener));

        // assert POST is processed
        $controller->request = $controller->request->withData('data', [
            'type' => 'dummy',
            'attributes' => [],
        ]);

        $this->callProtectedMethod('_checkRequestData', [], $listener);

        // assert PATCH is processed
        $controller->request = $controller->request->withData('data', [
            'id' => 'f083ea0b-9e48-44a6-af45-a814127a3a70',
            'type' => 'dummy',
            'attributes' => [],
        ]);

        $this->callProtectedMethod('_checkRequestData', [], $listener);

        // make sure the BadRequestException is thrown when request data is missing
        $controller->request = $controller->request->withParsedBody([]);
        $this->assertNull($this->callProtectedMethod('_checkRequestData', [], $listener));
    }

    /**
     * Make sure arrays holding json_decoded JSON API data are properly
     * converted to CakePHP format.
     *
     * Make sure incoming JSON API data is transformed to CakePHP format.
     * Please note that data is already json_decoded by Crud here.
     *
     * @return void
     */
    public function testConvertJsonApiDataArray()
    {
        $listener = new JsonApiListener(new Controller());
        $this->setReflectionClassInstance($listener);

        // assert posted id attribute gets processed as expected
        $jsonApiArray = [
            'data' => [
                'id' => '123'
            ]
        ];

        $expected = [
            'id' => '123'
        ];
        $result = $this->callProtectedMethod('_convertJsonApiDocumentArray', [$jsonApiArray], $listener);
        $this->assertSame($expected, $result);

        // assert success (single entity, no relationships)
        $jsonApiFixture = new File($this->_JsonApiDecoderFixtures . DS . 'incoming-country-no-relationships.json');
        $jsonApiArray = json_decode($jsonApiFixture->read(), true);
        $expected = [
            'code' => 'NL',
            'name' => 'The Netherlands',
            'dummy_counter' => 11111
        ];
        $result = $this->callProtectedMethod('_convertJsonApiDocumentArray', [$jsonApiArray], $listener);
        $this->assertSame($expected, $result);

        // assert success (single entity, multiple relationships, hasMany ignored for now)
        $jsonApiFixture = new File($this->_JsonApiDecoderFixtures . DS . 'incoming-country-mixed-relationships.json');
        $jsonApiArray = json_decode($jsonApiFixture->read(), true);
        $expected = [
            'code' => 'NL',
            'name' => 'The Netherlands',
            'dummy_counter' => 11111,
            'cultures' => [
                [
                    'id' => '2',
                    'name' => 'nl_NL',
                    'language_name' => 'Dutch',
                ],
            ],
            'currency_id' => '3'
        ];
        $result = $this->callProtectedMethod('_convertJsonApiDocumentArray', [$jsonApiArray], $listener);
        $this->assertSame($expected, $result);

        // assert success for relationships with null/empty data
        $jsonApiFixture = new File($this->_JsonApiDecoderFixtures . DS . 'incoming-country-mixed-relationships.json');
        $jsonApiArray = json_decode($jsonApiFixture->read(), true);
        $jsonApiArray['data']['relationships']['cultures']['data'] = null;
        $jsonApiArray['data']['relationships']['currency']['data'] = null;

        $expected = [
            'code' => 'NL',
            'name' => 'The Netherlands',
            'dummy_counter' => 11111
        ];
        $result = $this->callProtectedMethod('_convertJsonApiDocumentArray', [$jsonApiArray], $listener);
        $this->assertSame($expected, $result);
    }

    public function includeQueryProvider()
    {
        return [
            'standard' => [
                'cultures,currencies.countries',
                ['blacklist' => false, 'whitelist' => true],
                [
                    'Cultures',
                    'Currencies' => ['Countries']
                ],
                [
                    'cultures', 'currency.countries',
                ],
            ],
            'singular name' => [
                'cultures,currency',
                ['blacklist' => false, 'whitelist' => true],
                [
                    'Cultures',
                    'Currencies'
                ],
                [
                    'cultures',
                    'currency',
                ],
            ],
            'blacklist' => [
                'cultures,currencies.countries',
                ['blacklist' => ['currencies.countries'], 'whitelist' => true],
                [
                    'Cultures',
                    'Currencies'
                ],
                [
                    'cultures',
                    'currency',
                ],
            ],
            'whitelist' => [
                'cultures,currencies.countries',
                ['blacklist' => false, 'whitelist' => ['cultures']],
                [
                    'Cultures'
                ],
                [
                    'cultures'
                ],
            ],
            'multiple whitelists' => [
                'cultures,currencies.countries,cultures.language',
                ['blacklist' => false, 'whitelist' => ['cultures', 'currencies.countries']],
                [
                    'Cultures',
                    'Currencies' => [
                        'Countries',
                    ]
                ],
                [
                    'cultures',
                    'currency.countries'
                ],
            ],
            'whitelist wildcard' => [
                'cultures,currencies.countries,cultures.language',
                ['blacklist' => false, 'whitelist' => ['currencies.*']],
                [
                    'Currencies' => [
                        'Countries'
                    ]
                ],
                ['currency.countries'],
            ],
            'blacklist wildcard' => [
                'cultures,currencies.countries,currencies.names',
                ['blacklist' => ['currencies.*'], 'whitelist' => true],
                [
                    'Cultures',
                    'Currencies',
                ],
                ['cultures', 'currency']
            ],
            'blacklist with a whitelist wildcard' => [
                'cultures,currencies.countries,currencies.names,cultures.countries',
                ['blacklist' => ['currencies.names'], 'whitelist' => ['cultures', 'currencies.*']],
                [
                    'Currencies' => [
                        'Countries',
                    ],
                    'Cultures',
                ],
                ['cultures', 'currency.countries']
            ],
            'blacklist is more important' => [
                'cultures,currencies.countries',
                ['blacklist' => ['currencies.countries'], 'whitelist' => ['cultures', 'currencies.countries']],
                [
                    'Cultures',
                    'Currencies',
                ],
                ['cultures', 'currency']
            ],
        ];
    }

    /**
     * Make sure that the include query correct splits include string into a containable format
     *
     * @return void
     * @dataProvider includeQueryProvider
     */
    public function testIncludeQuery($include, $options, $expectedContain, $expectedInclude)
    {
        $listener = new JsonApiListener(new Controller());
        $this->setReflectionClassInstance($listener);

        $subject = new Subject();

        $query = $this
            ->getMockBuilder(Query::class)
            ->disableOriginalConstructor()
            ->getMock();

        $subject->query = $query;
        $subject->query
            ->expects($this->any())
            ->method('getRepository')
            ->willReturn(TableRegistry::get('Countries'));

        $this->callProtectedMethod('_includeParameter', [$include, $subject, $options], $listener);
        $this->assertSame($expectedInclude, $listener->getConfig('include'));
    }

    public function includeQueryBadRequestProvider()
    {
        return [
            'blacklist everything' => [
                'cultures,currencies.countries',
                ['blacklist' => true, 'whitelist' => ['cultures', 'currencies.countries']],
                [],
                []
            ],
            'whitelist nothing' => [
                'cultures,currencies.countries',
                ['blacklist' => false, 'whitelist' => false],
                [],
                []
            ],
        ];
    }

    /**
     * Ensure that the whiteList nothing or blackList everything do not accept any include parameter, and responds with
     * BadRequestException
     *
     * @return void
     * @dataProvider includeQueryBadRequestProvider
     * @expectedException \Cake\Http\Exception\BadRequestException
     */
    public function testIncludeQueryBadRequest($include, $options, $expectedContain, $expectedInclude)
    {
        $listener = new JsonApiListener(new Controller());
        $this->setReflectionClassInstance($listener);

        $subject = new Subject();

        $query = $this
            ->getMockBuilder(Query::class)
            ->disableOriginalConstructor()
            ->getMock();

        $subject->query = $query;
        $subject->query
            ->expects($options['blacklist'] !== true && $options['whitelist'] !== false ? $this->once() : $this->never())
            ->method('contain')
            ->with($expectedContain);
        $subject->query
            ->expects($this->any())
            ->method('repository')
            ->willReturn(TableRegistry::get('Countries'));

        $this->callProtectedMethod('_includeParameter', [$include, $subject, $options], $listener);
        $this->assertSame($expectedInclude, $listener->getConfig('include'));
    }

    /**
     * Ensure that sort is not applied to all tables
     *
     * Simulate /countries?include=currencies,national_capitals&sort=code,currencies.code
     *
     * @return void
     */
    public function testSortingNotAppliedToAllTables()
    {
        $listener = new JsonApiListener(new Controller());
        $this->setReflectionClassInstance($listener);

        $subject = new Subject();

        $query = $this
            ->getMockBuilder(Query::class)
            ->disableOriginalConstructor()
            ->getMock();

        $subject->query = $query;
        $subject->query
            ->expects($this->once())
            ->method('contain');
        $subject->query
            ->expects($this->any())
            ->method('getRepository')
            ->willReturn(TableRegistry::get('Countries'));

        $sort = 'code,currency.code';
        $listener->setConfig('include', ['currency', 'national_capitals']);

        $this->callProtectedMethod('_sortParameter', [$sort, $subject, []], $listener);
    }
}
