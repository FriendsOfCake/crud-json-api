<?php
declare(strict_types=1);

namespace CrudJsonApi\Test\TestCase\Listener;

use Cake\Controller\Controller;
use Cake\Core\Plugin;
use Cake\Datasource\ResultSetDecorator;
use Cake\Event\Event;
use Cake\Filesystem\File;
use Cake\Http\Exception\BadRequestException;
use Cake\Http\Exception\MethodNotAllowedException;
use Cake\Http\Response;
use Cake\Http\ServerRequest;
use Cake\ORM\Entity;
use Cake\ORM\Query;
use Cake\ORM\ResultSet;
use Cake\ORM\TableRegistry;
use Cake\Routing\RouteBuilder;
use Cake\Routing\Router;
use Crud\Event\Subject;
use Crud\TestSuite\TestCase;
use CrudJsonApi\Listener\JsonApiListener;
use CrudJsonApi\Test\App\Model\Entity\Country;

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
    public function setUp(): void
    {
        parent::setUp();

        Router::scope(
            '/',
            static function (RouteBuilder $routeBuilder) {
                $routeBuilder->fallbacks();
            }
        );

        $this->_JsonApiDecoderFixtures = Plugin::path('CrudJsonApi') . 'tests' . DS . 'Fixture' . DS . 'JsonApiDecoder';
    }

    /**
     * Make sure we are testing with expected default configuration values.
     */
    public function testDefaultConfig()
    {
        $listener = new JsonApiListener(new Controller());

        $expected = [
            'detectors' => [
                'jsonapi' => ['ext' => false, 'accept' => ['application/vnd.api+json']],
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
                    'blacklist' => false,
                ],
            ],
            'inflect' => 'variable',
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
        $listener = $this
            ->getMockBuilder(JsonApiListener::class)
            ->onlyMethods(['setupDetectors', '_checkRequestType', '_controller'])
            ->disableOriginalConstructor()
            ->getMock();

        $listener
            ->expects($this->at(1))
            ->method('_checkRequestType')
            ->willReturn(false); // for asserting missing JSON API Accept header

        $listener
            ->expects($this->at(3))
            ->method('_checkRequestType')
            ->willReturn(true); // for asserting valid JSON API Accept header

        // assert that listener does nothing if JSON API Accept header is missing
        $result = $listener->implementedEvents();

        $this->assertIsArray($result);

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
            ->getMockBuilder(Controller::class)
            ->addMethods(['_request'])
            ->disableOriginalConstructor()
            ->getMock();

        $controller->setRequest($this
            ->getMockBuilder(ServerRequest::class)
            ->onlyMethods([])
            ->disableOriginalConstructor()
            ->getMock());

        $controller->setRequest($controller->getRequest()->withData('data', [
            'type' => 'dummy',
            'attributes' => [],
        ]));

        $listener = $this
            ->getMockBuilder(JsonApiListener::class)
            ->onlyMethods(['_controller', '_checkRequestMethods', '_convertJsonApiDocumentArray', '_checkRequestData'])
            ->disableOriginalConstructor()
            ->getMock();

        $listener
            ->method('_controller')
            ->willReturn($controller);

        $listener
            ->method('_convertJsonApiDocumentArray')
            ->willReturn([]);

        $listener->beforeHandle(new Event('Crud.beforeHandle'));
        $this->assertTrue(true);
    }

    /**
     * Test afterSave event.
     *
     * @return void
     */
    public function testAfterSave()
    {
        $listener = $this
            ->getMockBuilder(JsonApiListener::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['_controller', '_response', 'render'])
            ->getMock();

        $controller = $this
            ->getMockBuilder(Controller::class)
            ->onlyMethods([])
            ->getMock();

        $response = $this
            ->getMockBuilder(Response::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();

        $listener
            ->method('_response')
            ->willReturn($response);

        $listener
            ->method('_controller')
            ->willReturn($controller);

        $listener
            ->method('render')
            ->willReturn($response);

        $event = $this
            ->getMockBuilder(Event::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getSubject'])
            ->getMock();

        $subject = $this
            ->getMockBuilder(Subject::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();

        $event
            ->method('getSubject')
            ->willReturn($subject);

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
            ->getMockBuilder(JsonApiListener::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['_controller', '_response'])
            ->getMock();

        $controller = $this
            ->getMockBuilder(Controller::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();

        $response = $this
            ->getMockBuilder(Response::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();

        $controller->response = $response;

        $listener
            ->method('_response')
            ->willReturn($response);

        $listener
            ->method('_controller')
            ->willReturn($controller);

        $event = $this
            ->getMockBuilder(Event::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getSubject'])
            ->getMock();

        $subject = $this
            ->getMockBuilder(Subject::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();

        $event
            ->method('getSubject')
            ->willReturn($subject);

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
            ->getMockBuilder(JsonApiListener::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
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
            ->getMockBuilder(Controller::class)
            ->onlyMethods([])
            ->enableOriginalConstructor()
            ->setConstructorArgs([null, null, 'Countries'])
            ->getMock();

        $listener = $this
            ->getMockBuilder(JsonApiListener::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['_controller', '_action'])
            ->getMock();

        $listener
            ->method('_controller')
            ->willReturn($controller);

        $subject = $this
            ->getMockBuilder(Subject::class)
            ->getMock();

        $subject->entity = new Country();

        $this->assertInstanceOf(Response::class, $listener->render($subject));
    }

    /**
     * Make sure render() works without find data
     *
     * @return void
     */
    public function testRenderWithoutResources()
    {
        $controller = $this
            ->getMockBuilder(Controller::class)
            ->onlyMethods([])
            ->enableOriginalConstructor()
            ->getMock();

        $listener = $this
            ->getMockBuilder(JsonApiListener::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['_controller', '_action'])
            ->getMock();

        $listener
            ->method('_controller')
            ->willReturn($controller);

        $subject = $this
            ->getMockBuilder(Subject::class)
            ->getMock();

        $this->assertInstanceOf(Response::class, $listener->render($subject));
    }

    /**
     * Make sure config option `withJsonApiVersion` accepts a boolean
     *
     * @return void
     */
    public function testValidateConfigOptionWithJsonApiVersionSuccessWithBoolean()
    {
        $listener = $this
            ->getMockBuilder(JsonApiListener::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();

        $listener->setConfig([
            'withJsonApiVersion' => true,
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
            ->getMockBuilder(JsonApiListener::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();

        $listener->setConfig([
            'withJsonApiVersion' => ['array' => 'accepted'],
        ]);

        $this->setReflectionClassInstance($listener);
        $this->assertNull($this->callProtectedMethod('_validateConfigOptions', [], $listener));
    }

    /**
     * Make sure config option `withJsonApiVersion` does not accept a string
     */
    public function testValidateConfigOptionWithJsonApiVersionFailWithString()
    {
        $this->expectException('Crud\Error\Exception\CrudException');
        $this->expectExceptionMessage('JsonApiListener configuration option `withJsonApiVersion` only accepts a boolean or an array');
        $listener = $this
            ->getMockBuilder(JsonApiListener::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();

        $listener->setConfig([
            'withJsonApiVersion' => 'string-not-accepted',
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
            ->getMockBuilder(JsonApiListener::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();

        $listener->setConfig([
            'meta' => ['array' => 'accepted'],
        ]);

        $this->setReflectionClassInstance($listener);
        $this->assertNull($this->callProtectedMethod('_validateConfigOptions', [], $listener));
    }

    /**
     * Make sure config option `meta` does not accept a string
     */
    public function testValidateConfigOptionMetaFailWithString()
    {
        $this->expectException('Crud\Error\Exception\CrudException');
        $this->expectExceptionMessage('JsonApiListener configuration option `meta` only accepts an array');
        $listener = $this
            ->getMockBuilder(JsonApiListener::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();

        $listener->setConfig([
            'meta' => 'string-not-accepted',
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
            ->getMockBuilder(JsonApiListener::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();

        $listener->setConfig([
            'absoluteLinks' => true,
        ]);

        $this->setReflectionClassInstance($listener);
        $this->assertNull($this->callProtectedMethod('_validateConfigOptions', [], $listener));
    }

    /**
     * Make sure config option `absoluteLinks` does not accept a string
     */
    public function testValidateConfigOptionAbsoluteLinksFailsWithString()
    {
        $this->expectException('Crud\Error\Exception\CrudException');
        $this->expectExceptionMessage('JsonApiListener configuration option `absoluteLinks` only accepts a boolean');
        $listener = $this
            ->getMockBuilder(JsonApiListener::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();

        $listener->setConfig([
            'absoluteLinks' => 'string-not-accepted',
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
            ->getMockBuilder(JsonApiListener::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();

        $listener->setConfig([
            'jsonApiBelongsToLinks' => true,
        ]);

        $this->setReflectionClassInstance($listener);
        $this->assertNull($this->callProtectedMethod('_validateConfigOptions', [], $listener));
    }

    /**
     * Make sure config option `jsonApiBelongsToLinks` does not accept a string
     */
    public function testValidateConfigOptionJsonApiBelongsToLinksFailsWithString()
    {
        $this->expectException('Crud\Error\Exception\CrudException');
        $this->expectExceptionMessage('JsonApiListener configuration option `jsonApiBelongsToLinks` only accepts a boolean');
        $listener = $this
            ->getMockBuilder(JsonApiListener::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();

        $listener->setConfig([
            'jsonApiBelongsToLinks' => 'string-not-accepted',
        ]);

        $this->setReflectionClassInstance($listener);
        $this->callProtectedMethod('_validateConfigOptions', [], $listener);
    }

    /**
     * Make sure config option `include` does not accept a string
     */
    public function testValidateConfigOptionIncludeFailWithString()
    {
        $this->expectException('Crud\Error\Exception\CrudException');
        $this->expectExceptionMessage('JsonApiListener configuration option `include` only accepts an array');
        $listener = $this
            ->getMockBuilder(JsonApiListener::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();

        $listener->setConfig([
            'include' => 'string-not-accepted',
        ]);

        $this->setReflectionClassInstance($listener);
        $this->callProtectedMethod('_validateConfigOptions', [], $listener);
    }

    /**
     * Make sure config option `fieldSets` does not accept a string
     */
    public function testValidateConfigOptionFieldSetsFailWithString()
    {
        $this->expectException('Crud\Error\Exception\CrudException');
        $this->expectExceptionMessage('JsonApiListener configuration option `fieldSets` only accepts an array');
        $listener = $this
            ->getMockBuilder(JsonApiListener::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();

        $listener->setConfig([
            'fieldSets' => 'string-not-accepted',
        ]);

        $this->setReflectionClassInstance($listener);
        $this->callProtectedMethod('_validateConfigOptions', [], $listener);
    }

    /**
     * Make sure config option `jsonOptions` does not accept a string
     */
    public function testValidateConfigOptionJsonOptionsFailWithString()
    {
        $this->expectException('Crud\Error\Exception\CrudException');
        $this->expectExceptionMessage('JsonApiListener configuration option `jsonOptions` only accepts an array');
        $listener = $this
            ->getMockBuilder(JsonApiListener::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();

        $listener->setConfig([
            'jsonOptions' => 'string-not-accepted',
        ]);

        $this->setReflectionClassInstance($listener);
        $this->callProtectedMethod('_validateConfigOptions', [], $listener);
    }

    /**
     * Make sure config option `debugPrettyPrint` does not accept a string
     */
    public function testValidateConfigOptionDebugPrettyPrintFailWithString()
    {
        $this->expectException('Crud\Error\Exception\CrudException');
        $this->expectExceptionMessage('JsonApiListener configuration option `debugPrettyPrint` only accepts a boolean');
        $listener = $this
            ->getMockBuilder(JsonApiListener::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();

        $listener->setConfig([
            'debugPrettyPrint' => 'string-not-accepted',
        ]);

        $this->setReflectionClassInstance($listener);
        $this->callProtectedMethod('_validateConfigOptions', [], $listener);
    }

    /**
     * Make sure config option `queryParameters` does not accept a string
     */
    public function testValidateConfigOptionQueryParametersPrintFailWithString()
    {
        $this->expectException('Crud\Error\Exception\CrudException');
        $this->expectExceptionMessage('JsonApiListener configuration option `queryParameters` only accepts an array');
        $listener = $this->getMockBuilder(JsonApiListener::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();

        $listener->setConfig([
            'queryParameters' => 'string-not-accepted',
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
        $this->callProtectedMethod('_checkRequestMethods', [], $listener);
        $this->assertTrue(true); //No exception was thrown
    }

    /**
     * Make sure the listener fails on non JSON API request Content-Type header
     */
    public function testCheckRequestMethodsFailContentHeader()
    {
        $this->expectException(BadRequestException::class);
        $this->expectExceptionMessage('JSON API requests with data require the "application/vnd.api+json" Content-Type header');
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
     */
    public function testCheckRequestMethodsFailOnPutMethod()
    {
        $this->expectException(MethodNotAllowedException::class);
        $this->expectExceptionMessage('JSON API does not support the PUT method, use PATCH instead');
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
            ->getMockBuilder(JsonApiListener::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['_controller'])
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
            ->getMockBuilder(Controller::class)
            ->onlyMethods([])
            ->enableOriginalConstructor()
            ->getMock();

        $listener = $this
            ->getMockBuilder(JsonApiListener::class)
            ->disableOriginalConstructor()
            ->addMethods(['_event'])
            ->onlyMethods(['_controller'])
            ->getMock();

        $listener
            ->method('_controller')
            ->willReturn($controller);

        $entity = new Entity();
        $subject = $this
            ->getMockBuilder(Subject::class)
            ->getMock();

        $subject->entities = $this
            ->getMockBuilder(ResultSet::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['first'])
            ->getMock();

        $subject->entities
            ->method('first')
            ->willReturn($entity);

        $this->setReflectionClassInstance($listener);
        $result = $this->callProtectedMethod('_getSingleEntity', [$subject], $listener);
        $this->assertSame($entity, $result);

        $subject->entities = $this->getMockBuilder(ResultSetDecorator::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['first'])
            ->getMock();

        $subject->entities->method('first')
            ->willReturn($entity);

        $this->setReflectionClassInstance($listener);
        $result = $this->callProtectedMethod('_getSingleEntity', [$subject], $listener);
        $this->assertSame($entity, $result);

        unset($subject->entities);

        $subject->entity = $entity;
        $this->setReflectionClassInstance($listener);
        $result = $this->callProtectedMethod('_getSingleEntity', [$subject], $listener);
        $this->assertSame($subject->entity, $result);
    }

    public function testGetSingleEntityForEmptyResultSet()
    {
        $controller = $this
            ->getMockBuilder(Controller::class)
            ->onlyMethods([])
            ->enableOriginalConstructor()
            ->getMock();

        $listener = $this
            ->getMockBuilder(JsonApiListener::class)
            ->disableOriginalConstructor()
            ->addMethods(['_event'])
            ->onlyMethods(['_controller'])
            ->getMock();

        $listener
            ->method('_controller')
            ->willReturn($controller);

        $entity = new Entity();

        $subject = $this
            ->getMockBuilder(Subject::class)
            ->getMock();

        $subject->entities = $this
            ->getMockBuilder(ResultSet::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['first'])
            ->getMock();

        $subject->entities
            ->method('first')
            ->willReturn(null);

        $query = $this
            ->getMockBuilder(Query::class)
            ->disableOriginalConstructor()
            ->getMock();

        $subject->query = $query;
        $subject->query
            ->method('getRepository')
            ->willReturn(TableRegistry::get('Countries'));

        $this->setReflectionClassInstance($listener);
        $result = $this->callProtectedMethod('_getSingleEntity', [$subject], $listener);

        $this->assertInstanceOf('Cake\ORM\Entity', $result);
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
        $this->assertNotEmpty($associationsBefore->get('Currencies'));
        $this->assertNotEmpty($associationsBefore->get('Cultures'));

        // make sure cultures are not present in the find result
        $query = $table->find()->contain([
            'Currencies',
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
            'propertyName' => 'subcountry',
        ]);

        $table->belongsTo('SuperCountries', [
            'className' => 'Countries',
            'propertyName' => 'supercountry',
        ]);

        $associations = [];
        foreach ($table->associations() as $association) {
            $associations[strtolower($association->getName())] = [
                'association' => $association,
                'children' => [],
            ];
        }

        $associations['currencies']['children'] = [
            'countries' => [
                'association' => $table->Currencies->Countries,
            ],
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
            'SuperCountries' => $table->SuperCountries->getTarget(),
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
            ->getMockBuilder(JsonApiListener::class)
            ->disableOriginalConstructor()
            ->addMethods(['_event'])
            ->onlyMethods(['_controller'])
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
                'children' => [],
            ];
        }

        $associations['currencies']['children'] = [
            'countries' => [
                'association' => $table->Currencies->Countries,
            ],
        ];

        $expected = [
            'currency.countries',
            'nationalCapital',
            'cultures',
            'nationalCities',
            'subcountries',
            'supercountry',
        ];
        $result = $this->callProtectedMethod('_getIncludeList', [$associations], $listener);
        $this->assertSame($expected, $result);

        unset($associations['currencies']['children']['countries']);
        $this->assertSame(['currencies', 'nationalcapitals', 'cultures', 'nationalcities', 'subcountries', 'supercountries'], array_keys($associations));

        $result = $this->callProtectedMethod('_getIncludeList', [$associations], $listener);
        $this->assertSame(['currency', 'nationalCapital', 'cultures', 'nationalCities', 'subcountries', 'supercountry'], $result);

        // assert the include list is still auto-generated if an association is
        // removed from the AssociationsCollection
        unset($associations['cultures']);
        $this->assertSame(['currencies', 'nationalcapitals', 'nationalcities', 'subcountries', 'supercountries'], array_keys($associations));

        $result = $this->callProtectedMethod('_getIncludeList', [$associations], $listener);
        $this->assertSame(['currency', 'nationalCapital', 'nationalCities', 'subcountries', 'supercountry'], $result);

        // assert user specified listener config option is returned as-is (no magic)
        $userSpecifiedIncludes = [
            'user-specified-list',
            'with',
            'associations-to-present-in-included-node',
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
     */
    public function testCheckRequestData()
    {
        $this->expectException(BadRequestException::class);
        $this->expectExceptionMessage(
            'Missing request data required for POST and PATCH methods. ' .
            'Make sure that you are sending a request body and that it is valid JSON.'
        );
        $controller = $this
            ->getMockBuilder(Controller::class)
            ->addMethods(['_request'])
            ->disableOriginalConstructor()
            ->getMock();

        $request = $this
            ->getMockBuilder(ServerRequest::class)
            ->onlyMethods(['contentType', 'getMethod'])
            ->disableOriginalConstructor()
            ->getMock();

        $request
            ->expects($this->at(0))
            ->method('getMethod')
            ->willReturn('GET');

        $request
            ->expects($this->at(1))
            ->method('getMethod')
            ->willReturn('POST');

        $request
            ->expects($this->at(2))
            ->method('getMethod')
            ->will($this->returnValue('POST'));

        $request
            ->expects($this->at(3))
            ->method('getMethod')
            ->willReturn('PATCH');

        $controller->setRequest($request);

        $listener = $this
            ->getMockBuilder(JsonApiListener::class)
            ->onlyMethods(['_controller', '_checkRequestMethods', '_convertJsonApiDocumentArray'])
            ->disableOriginalConstructor()
            ->getMock();

        $listener
            ->method('_controller')
            ->willReturn($controller);

        $listener
            ->method('_convertJsonApiDocumentArray')
            ->willReturn([]);

        $this->setReflectionClassInstance($listener);

        // assert null if there is no Content-Type
        $this->assertNull($this->callProtectedMethod('_checkRequestData', [], $listener));

        // assert POST is processed
        $controller->setRequest($controller->getRequest()->withData('data', [
            'type' => 'dummy',
            'attributes' => [],
        ]));

        $this->callProtectedMethod('_checkRequestData', [], $listener);

        // assert PATCH is processed
        $controller->setRequest($controller->getRequest()->withData('data', [
            'id' => 'f083ea0b-9e48-44a6-af45-a814127a3a70',
            'type' => 'dummy',
            'attributes' => [],
        ]));

        $this->callProtectedMethod('_checkRequestData', [], $listener);

        // make sure the BadRequestException is thrown when request data is missing
        $controller->setRequest($controller->getRequest()->withParsedBody([]));
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
                'id' => '123',
            ],
        ];

        $expected = [
            'id' => '123',
        ];
        $result = $this->callProtectedMethod('_convertJsonApiDocumentArray', [$jsonApiArray], $listener);
        $this->assertSame($expected, $result);

        // assert success (single entity, no relationships)
        $jsonApiFixture = new File($this->_JsonApiDecoderFixtures . DS . 'incoming-country-no-relationships.json');
        $jsonApiArray = json_decode($jsonApiFixture->read(), true);
        $expected = [
            'code' => 'NL',
            'name' => 'The Netherlands',
            'dummy_counter' => 11111,
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
            'currency_id' => '3',
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
            'dummy_counter' => 11111,
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
                    'Currencies' => ['Countries'],
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
                    'Currencies',
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
                    'Currencies',
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
                    'Cultures',
                ],
                [
                    'cultures',
                ],
            ],
            'multiple whitelists' => [
                'cultures,currencies.countries,cultures.language',
                ['blacklist' => false, 'whitelist' => ['cultures', 'currencies.countries']],
                [
                    'Cultures',
                    'Currencies' => [
                        'Countries',
                    ],
                ],
                [
                    'cultures',
                    'currency.countries',
                ],
            ],
            'whitelist wildcard' => [
                'cultures,currencies.countries,cultures.language',
                ['blacklist' => false, 'whitelist' => ['currencies.*']],
                [
                    'Currencies' => [
                        'Countries',
                    ],
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
                ['cultures', 'currency'],
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
                ['cultures', 'currency.countries'],
            ],
            'blacklist is more important' => [
                'cultures,currencies.countries',
                ['blacklist' => ['currencies.countries'], 'whitelist' => ['cultures', 'currencies.countries']],
                [
                    'Cultures',
                    'Currencies',
                ],
                ['cultures', 'currency'],
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
                [],
            ],
            'whitelist nothing' => [
                'cultures,currencies.countries',
                ['blacklist' => false, 'whitelist' => false],
                [],
                [],
            ],
        ];
    }

    /**
     * Ensure that the whiteList nothing or blackList everything do not accept any include parameter, and responds with
     * BadRequestException
     *
     * @return void
     * @dataProvider includeQueryBadRequestProvider
     */
    public function testIncludeQueryBadRequest($include, $options, $expectedContain, $expectedInclude)
    {
        $this->expectException('Cake\Http\Exception\BadRequestException');
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
            ->method('getRepository')
            ->willReturn(TableRegistry::get('Countries'));

        $sort = 'code,currency.code';
        $listener->setConfig('include', ['currency', 'national_capitals']);

        $this->callProtectedMethod('_sortParameter', [$sort, $subject, []], $listener);
    }
}
