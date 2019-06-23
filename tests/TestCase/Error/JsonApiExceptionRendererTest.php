<?php
namespace CrudJsonApi\Test\TestCase\Error;

use Cake\Core\Configure;
use Cake\Core\Exception\Exception;
use Cake\Core\Plugin;
use Cake\Filesystem\File;
use Cake\Http\Response;
use Cake\Http\ServerRequest;
use Cake\ORM\TableRegistry;
use Crud\Error\Exception\ValidationException;
use Crud\TestSuite\TestCase;
use Neomerx\JsonApi\Schema\ErrorCollection;

class JsonApiExceptionRendererTest extends TestCase
{
    /**
     * Path to directory holding the JSON API response fixtures
     *
     * @var
     */
    protected $_JsonApiResponseBodyFixtures;

    /**
     * fixtures property
     *
     * @var array
     */
    public $fixtures = [
        'plugin.CrudJsonApi.Countries',
    ];

    /**
     * setUp
     */
    public function setUp()
    {
        parent::setUp();
        Configure::write('debug', true);

        $this->deprecated(function () {
            \Cake\Core\Plugin::load('Crud', ['path' => ROOT . DS, 'autoload' => true]);
            \Cake\Core\Plugin::load('CrudJsonApi', ['path' => ROOT . DS, 'autoload' => true]);
        });

        // set path to the JSON API response fixtures
        $this->_JsonApiResponseBodyFixtures = Plugin::path('Crud') . 'tests' . DS . 'Fixture' . DS . 'JsonApiResponseBodies';
    }

    /**
     * Make sure non-validation errors are rendered.
     *
     * @return void
     */
    public function testRenderWithNonValidationError()
    {
        $exception = new Exception('Hello World');

        $controller = $this->getMockBuilder('Cake\Controller\Controller')
            ->setMethods(['render'])
            ->getMock();
        $controller->request = new ServerRequest([
            'environment' => [
                'HTTP_ACCEPT' => 'application/vnd.api+json'
            ]
        ]);
        $controller->response = new Response();

        $renderer = $this->getMockBuilder('CrudJsonApi\Error\JsonApiExceptionRenderer')
            ->setMethods(['_getController'])
            ->disableOriginalConstructor()
            ->getMock();
        $renderer
            ->expects($this->once())
            ->method('_getController')
            ->with()
            ->will($this->returnValue($controller));

        $renderer->__construct($exception);
        $renderer->render();

        $viewVars = $controller->viewVars;

        // assert viewVars required to generate JSON API error are present
        $this->assertTrue(!empty($viewVars['_serialize']));

        $expected = ['message', 'url', 'code'];
        $actual = $viewVars['_serialize'];
        $actual = array_flip($actual);
        unset($actual['file'], $actual['line']);
        $actual = array_flip($actual);
        $this->assertEquals($expected, $actual);

        $this->assertEquals($viewVars['message'], 'Hello World');
        $this->assertEquals($viewVars['code'], 500);
        $this->assertEquals($viewVars['url'], '/');
    }

    /**
     * Make sure validation errors are rendered.
     *
     * @return void
     */
    public function testRenderWithValidationError()
    {
        $countries = TableRegistry::get('Countries');

        $invalidCountry = $countries->newEntity([
            'code' => 'not-all-uppercase'
        ]);

        $exception = new ValidationException($invalidCountry);

        $controller = $this->getMockBuilder('Cake\Controller\Controller')
            ->setMethods(['render'])
            ->getMock();
        $controller->request = new ServerRequest([
            'environment' => [
                'HTTP_ACCEPT' => 'application/vnd.api+json'
            ]
        ]);
        $controller->response = new Response();

        $renderer = $this->getMockBuilder('CrudJsonApi\Error\JsonApiExceptionRenderer')
            ->setMethods(['_getController'])
            ->disableOriginalConstructor()
            ->getMock();
        $renderer
            ->expects($this->once())
            ->method('_getController')
            ->with()
            ->will($this->returnValue($controller));

        $renderer->__construct($exception);
        $result = $renderer->render();

        // assert expected exception is generated
        $jsonApiFixture = new File($this->_JsonApiResponseBodyFixtures . DS . 'Errors' . DS . 'validation-error-multiple-reasons.json');
        $jsonApiArray = json_decode($jsonApiFixture->read(), true);

        $result = json_decode($result->getBody(), true);
        unset($result['query']);

        $this->assertSame($jsonApiArray, $result);
    }

    /**
     * Make sure validation status code is set to 422 if fetching status
     * code from the controller causes an exception.
     *
     * @return void
     */
    public function testValidationExceptionsFallBackToStatusCode422()
    {
        $countries = TableRegistry::get('Countries');

        $invalidCountry = $countries->newEntity([]);

        $exception = new ValidationException($invalidCountry);

        $controller = $this->getMockBuilder('Cake\Controller\Controller')
            ->setMethods(['render'])
            ->getMock();

        $controller->request = new ServerRequest([
            'environment' => [
                'HTTP_ACCEPT' => 'application/vnd.api+json'
            ]
        ]);

        $res = new Response();

        $response = $this->getMockBuilder('Cake\Http\Response')
            ->setMethods(['withStatus'])
            ->getMock();
        $response
            ->expects($this->at(0))
            ->method('withStatus')
            ->will($this->throwException(new Exception('woot')));
        $response
            ->expects($this->at(1))
            ->method('withStatus')
            ->will($this->returnCallback(function ($input) use ($res) {
                return $res->withStatus($input);
            }));

        $controller->response = $response;

        $renderer = $this->getMockBuilder('CrudJsonApi\Error\JsonApiExceptionRenderer')
            ->setMethods(['_getController'])
            ->disableOriginalConstructor()
            ->getMock();
        $renderer
            ->expects($this->once())
            ->method('_getController')
            ->with()
            ->will($this->returnValue($controller));

        $renderer->__construct($exception);
        $result = $renderer->render();
        $this->assertEquals(422, $result->getStatusCode());
    }

    /**
     * Make sure both built-in and user-defined validation errors get
     * converted to similar array format used to generate JSON API errors.
     *
     * @return void
     */
    public function testStandardizeValidationErrors()
    {
        $errors = [
            'name' => [
                '_empty' => 'This is a single word built-in rule'
            ],
            'dummy_counter' => [
                'integer' => 'This is a two word built-in rule'
            ],
            'code' => [
                0 => [
                    'fields' => [
                        0 => 'code'
                    ],
                    'name' => 'EXACT_LENGTH',
                    'message' => 'This is a user-defined rule'
                ]
            ],
        ];

        $renderer = $this->getMockBuilder('CrudJsonApi\Error\JsonApiExceptionRenderer')
            ->setMethods(null)
            ->disableOriginalConstructor()
            ->getMock();

        $this->setReflectionClassInstance($renderer);

        $expected = [
            0 => [
                'fields' => [
                    'name'
                ],
                'name' => '_empty',
                'message' => 'This is a single word built-in rule'
            ],
            1 => [
                'fields' => [
                    'dummy-counter'
                ],
                'name' => 'integer',
                'message' => 'This is a two word built-in rule'
            ],
            2 => [
                'fields' => [
                    'code'
                ],
                'name' => 'EXACT_LENGTH',
                'message' => 'This is a user-defined rule'
            ],
        ];

        $result = $this->callProtectedMethod('_standardizeValidationErrors', [$errors], $renderer);
        $this->assertEquals($expected, $result);
    }

    /**
     * Make sure a NeoMerx ErrorCollection is either created from validation
     * errors OR a cloaked existing collection is retrieved as-is from the
     * JsonApiListener property.
     *
     * @return void
     */
    public function testGetNeoMerxErrorCollection()
    {
        $renderer = $this->getMockBuilder('CrudJsonApi\Error\JsonApiExceptionRenderer')
            ->setMethods(null)
            ->disableOriginalConstructor()
            ->getMock();

        $this->setReflectionClassInstance($renderer);

        // assert that cloaked error collection passed to the exception as
        // a CrudJsonApiListener validation error returned as-is
        $collection = new ErrorCollection();
        $collection->addDataError('My manual test error');
        $validationErrors = [
            'CrudJsonApiListener' => [
                'NeoMerxErrorCollection' => $collection
            ]
        ];

        $result = $this->callProtectedMethod('_getNeoMerxErrorCollection', [$validationErrors], $renderer);
        $this->assertSame($collection, $result);

        // assert that the cloaking key is not returned as-is if it does not
        // contain an error collection and thus a new collection is created.
        $validationErrors = [
            'CrudJsonApiListener' => [
                'NeoMerxErrorCollection' => 'not-a-neomerx-error-collection'
            ]
        ];

        $result = $this->callProtectedMethod('_getNeoMerxErrorCollection', [$validationErrors], $renderer);
        $errors = $result->getArrayCopy();
        $error = $errors[0];
        $this->setReflectionClassInstance($error);

        $this->assertSame('not-a-neomerx-error-collection', $this->getProtectedProperty('detail', $error));

        // assert basic collections are created as well
        $nonStandardizedValidationErrors = [
            'name' => [
                '_required' => 'This is a built-in rule'
            ],
            'code' => [
                '_required' => 'This is a built-in rule'
            ],
        ];

        $result = $this->callProtectedMethod('_getNeoMerxErrorCollection', [$nonStandardizedValidationErrors], $renderer);

        $this->assertInstanceOf(ErrorCollection::class, $result);
        $this->assertSame(2, $result->count());
    }

    /**
     * Make sure top-level `query` node is only added to the json if
     * ApiQueryLogListener is loaded.
     *
     * @return void
     */
    public function testAddQueryLogs()
    {
        $apiQueryLogListener = $this->getMockBuilder('Crud\Listener\ApiQueryLogListener')
            ->setMethods(['getQueryLogs'])
            ->disableOriginalConstructor()
            ->getMock();
        $apiQueryLogListener
            ->expects($this->at(0))
            ->method('getQueryLogs')
            ->with()
            ->will($this->returnValue(null));
        $apiQueryLogListener
            ->expects($this->at(1))
            ->method('getQueryLogs')
            ->with()
            ->will($this->returnValue([
                'dummy' => 'log-entry'
            ]));

        $renderer = $this->getMockBuilder('CrudJsonApi\Error\JsonApiExceptionRenderer')
            ->setMethods(['_getApiQueryLogListenerObject'])
            ->disableOriginalConstructor()
            ->getMock();
        $renderer
            ->expects($this->exactly(2))
            ->method('_getApiQueryLogListenerObject')
            ->with()
            ->will($this->returnValue($apiQueryLogListener));

        $this->setReflectionClassInstance($renderer);

        $json = '{"data": {"dummy": "data"}}';

        // assert query node is not added when listener is not loaded/does not provide logs
        $result = $this->callProtectedMethod('_addQueryLogsNode', [$json], $renderer);
        $this->assertJsonStringEqualsJsonString($json, $result);

        // assert query node is added when listener is loaded
        $result = $this->callProtectedMethod('_addQueryLogsNode', [$json], $renderer);
        $resultArray = json_decode($result, true);
        $this->assertArrayHasKey('query', $resultArray);
    }
}
