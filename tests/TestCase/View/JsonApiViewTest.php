<?php
declare(strict_types=1);

namespace CrudJsonApi\Test\TestCase\View;

use Cake\Controller\Controller;
use Cake\Core\Configure;
use Cake\Core\Plugin;
use Cake\Event\Event;
use Cake\Http\Response;
use Cake\Http\ServerRequest;
use Cake\ORM\Entity;
use Cake\ORM\ResultSet;
use Cake\TestSuite\StringCompareTrait;
use Cake\View\View;
use Crud\Error\Exception\CrudException;
use Crud\Event\Subject;
use Crud\TestSuite\TestCase;
use CrudJsonApi\Listener\JsonApiListener;
use CrudJsonApi\View\JsonApiView;
use Neomerx\JsonApi\Schema\Link;
use StdClass;

/**
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 */
class JsonApiViewTest extends TestCase
{
    use StringCompareTrait;

    /**
     * Fixtures
     *
     * @var array
     */
    public $fixtures = [
        'plugin.CrudJsonApi.Countries',
        'plugin.CrudJsonApi.Currencies',
        'plugin.CrudJsonApi.Cultures',
    ];

    /**
     * Path to directory holding the JSON API response fixtures.
     *
     * @var
     */
    protected $_JsonApiResponseBodyFixtures;

    /**
     * Loaded with JsonApiListener default config settings on every setUp()
     *
     * @var array
     */
    protected $_defaultOptions;

    /**
     * setUp
     */
    public function setUp(): void
    {
        parent::setUp();

        require CONFIG . 'routes.php';

        $listener = new JsonApiListener(new Controller());

        $this->_defaultOptions = [
            'urlPrefix' => $listener->getConfig('urlPrefix'),
            'withJsonApiVersion' => $listener->getConfig('withJsonApiVersion'),
            'meta' => $listener->getConfig('meta'),
            'absoluteLinks' => $listener->getConfig('absoluteLinks'),
            'jsonApiBelongsToLinks' => $listener->getConfig('jsonApiBelongsToLinks'),
            'include' => $listener->getConfig('include'),
            'fieldSets' => $listener->getConfig('fieldSets'),
            'jsonOptions' => $listener->getConfig('jsonOptions'),
            'debugPrettyPrint' => $listener->getConfig('debugPrettyPrint'),
            'inflect' => $listener->getConfig('inflect'),
        ] + $listener->getConfig();

        // override some defaults to create more DRY tests
        $this->_defaultOptions['jsonOptions'] = [JSON_PRETTY_PRINT];
        $this->_defaultOptions['serialize'] = true;

        // set path the the JSON API response fixtures
        $this->_JsonApiResponseBodyFixtures = Plugin::path('CrudJsonApi') . 'tests' . DS . 'Fixture' . DS . 'JsonApiResponseBodies';
    }

    /**
     * Make sure we are testing with expected configuration settings.
     */
    public function testDefaultViewVars(): void
    {
        $expected = [
            'urlPrefix' => null,
            'withJsonApiVersion' => false,
            'meta' => [],
            'absoluteLinks' => false,
            'include' => [],
            'fieldSets' => [],
            'jsonOptions' => [
                JSON_PRETTY_PRINT,
            ],
            'debugPrettyPrint' => true,
            'inflect' => 'variable',
            'serialize' => true,
        ];
        foreach ($expected as $key => $value) {
            $this->assertArrayHasKey($key, $this->_defaultOptions);
            $this->assertSame($value, $this->_defaultOptions[$key]);
        }
    }

    /**
     * Helper function to easily create specific view for each test.
     *
     * @param string|null $tableName False to get view for resource-less response
     * @param array $viewVars
     * @param array $options
     * @return \Cake\View\View NeoMerx jsonapi encoded array
     */
    protected function _getView(?string $tableName, array $viewVars = [], array $options = []): View
    {
        // determine user configurable viewVars
        if (empty($options)) {
            $options = $this->_defaultOptions;
        } else {
            $options = array_replace_recursive($this->_defaultOptions, $options);
        }

        // create required (but non user configurable) viewVars next
        $request = new ServerRequest();
        $response = new Response();
        $controller = new Controller($request, $response, $tableName);

        $builder = $controller->viewBuilder();
        $builder
            ->setClassName(JsonApiView::class)
            ->setOptions($options);

        // create view with viewVars for resource-less response
        if (!$tableName) {
            $controller->set($viewVars);

            return $controller->createView();
        }

        // still here, create view with viewVars for response with resource(s)
        $controller->setName($tableName); // e.g. Countries
        $table = $controller->loadModel(); // table object

        // fetch data from test viewVar normally found in subject
        $subject = new Subject(['event' => new Event('Crud.beforeHandle')]);
        $findResult = $viewVars[$table->getTable()];
        if (is_a($findResult, ResultSet::class)) {
            $subject->entities = $findResult;
        } else {
            $subject->entity = $findResult;
        }
        $subject->query = $table->query();

        // create required '_entities' and '_associations' viewVars normally
        // produced and set by the JsonApiListener
        $listener = $this
            ->getMockBuilder(JsonApiListener::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->setReflectionClassInstance($listener);
        $associations = $this->callProtectedMethod('_getContainedAssociations', [$table, $subject->query->getContain()], $listener);
        $repositories = $this->callProtectedMethod('_getRepositoryList', [$table, $associations], $listener);

        // set viewVars before creating the view
        $controller->viewBuilder()
            ->setOption('repositories', $repositories)
            ->setOption('associations', $associations);

        $controller->set($viewVars);

        return $controller->createView();
    }

    /**
     * Make sure viewVars starting with an underscore are treated as specialVars.
     *
     * @return void
     */
    public function testGetSpecialVars(): void
    {
        $view = $this->_getView('Countries', [
            'countries' => 'dummy-value',
            'non_underscored_should_not_be_in_special_vars' => 'dummy-value',
            '_underscored_should_be_in_special_vars' => 'dummy-value',
        ]);

        $this->setReflectionClassInstance($view);
        $result = $this->callProtectedMethod('_getSpecialVars', [], $view);

        $this->assertNotContains('non_underscored_should_not_be_in_special_vars', $result);
        $this->assertContains('_underscored_should_be_in_special_vars', $result);
    }

    /**
     * Make sure that an exception is thrown for generic entity classes
     *
     * @return void
     */
    public function testEncodeWithGenericEntity(): void
    {
        $this->expectException(CrudException::class);
        $this->expectExceptionMessage('Entity classes must not be the generic "Cake\ORM\Entity" class for repository "Countries"');
        $this->getTableLocator()->get('Countries')->setEntityClass(Entity::class);

        // test collection of entities without relationships
        $countries = $this->getTableLocator()->get('Countries')
            ->find()
            ->all();
        $view = $this->_getView('Countries', [
            'countries' => $countries,
        ]);

        $view->render();
    }

    /**
     * Make sure expected JSON API strings are generated as expected when
     * using Crud's DynamicEntitySchema.
     *
     * Please note that we are deliberately using assertSame() instead of
     * assertJsonFileEqualsJsonFile() for all tests because the latter will
     * ignore formatting like e.g. JSON_PRETTY_PRINT.
     *
     * @return void
     */
    public function testEncodeWithDynamicSchemas(): void
    {
        // test collection of entities without relationships
        $countries = $this->getTableLocator()->get('Countries')->find()->all();
        $view = $this->_getView('Countries', [
            'countries' => $countries,
        ]);

        $this->assertSameAsFile(
            $this->_JsonApiResponseBodyFixtures . DS . 'FetchingCollections' . DS . 'get-countries-without-pagination.json',
            $view->render()
        );

        // test single entity without relationships
        $countries = $this->getTableLocator()->get('Countries')->find()->first();
        $view = $this->_getView('Countries', [
            'countries' => $countries,
        ]);

        $this->assertSameAsFile(
            $this->_JsonApiResponseBodyFixtures . DS . 'FetchingResources' . DS . 'get-country-no-relationships.json',
            $view->render()
        );
    }

    /**
     * Make sure resource-less JSON API strings are generated as expected.
     *
     * @return void
     */
    public function testEncodeWithoutSchemas(): void
    {
        // make sure empty body is rendered
        $view = $this->_getView(null, [], [
            'meta' => false,
        ]);
        $this->assertSame('', $view->render());

        // make sure body with only/just `meta` node is rendered
        $view = $this->_getView(null, [], [
            'meta' => [
                'author' => 'bravo-kernel',
            ],
        ]);

        $this->assertSameAsFile(
            $this->_JsonApiResponseBodyFixtures . DS . 'MetaInformation' . DS . 'meta-only.json',
            $view->render()
        );
    }

    /**
     * Make sure user option `withJsonVersion` produces expected json
     *
     * @return void
     */
    public function testOptionalWithJsonApiVersion(): void
    {
        // make sure top-level node is added when true
        $countries = $this->getTableLocator()->get('Countries')->find()->all();
        $view = $this->_getView('Countries', [
            'countries' => $countries,
        ], [
            'withJsonApiVersion' => true,
        ]);
        $expectedVersionArray = [
            'jsonapi' => [
                'version' => '1.1',
            ],
        ];
        $jsonApi = json_decode($view->render(), true);
        $this->assertArrayHasKey('jsonapi', $jsonApi);
        $this->assertSame(['version' => '1.1'], $jsonApi['jsonapi']);

        // make sure top-level node is added when passed an array
        $countries = $this->getTableLocator()->get('Countries')->find()->all();
        $view = $this->_getView('Countries', [
            'countries' => $countries,
        ], [
            'withJsonApiVersion' => [
                'meta-key-1' => 'meta-val-1',
                'meta-key-2' => 'meta-val-2',
            ],
        ]);
        $expectedVersionArray = [
            'jsonapi' => [
                'version' => '1.1',
                'meta' => [
                    'meta-key-1' => 'meta-val-1',
                    'meta-key-2' => 'meta-val-2',
                ],
            ],
        ];
        $this->assertArrayHasKey('jsonapi', json_decode($view->render(), true));
        $this->assertSame(['version' => '1.1', 'meta' => ['meta-key-1' => 'meta-val-1', 'meta-key-2' => 'meta-val-2']], json_decode($view->render(), true)['jsonapi']);

        // make sure top-level node is not added when false
        $view = $this->_getView('Countries', [
            'countries' => $countries,
            '_withJsonApiVersion' => false,
        ]);
        $this->assertArrayNotHasKey('jsonapi', json_decode($view->render(), true));
    }

    /**
     * Make sure user option `meta` produces expected json
     *
     * @return void
     */
    public function testOptionalMeta(): void
    {
        // make sure top-level node is added when passed an array
        $countries = $this->getTableLocator()->get('Countries')->find()->all();
        $view = $this->_getView('Countries', [
            'countries' => $countries,
            ], [
            'meta' => [
                'author' => 'bravo-kernel',
            ],
        ]);
        $expectedMetaArray = [
            'meta' => [
                'author' => 'bravo-kernel',
            ],
        ];
        $this->assertArrayHasKey('meta', json_decode($view->render(), true));
        $this->assertSame(['author' => 'bravo-kernel'], json_decode($view->render(), true)['meta']);

        // make sure top-level node is not added when false
        $view = $this->_getView('Countries', [
            'countries' => $countries,
            '_meta' => false,
        ]);
        $this->assertArrayNotHasKey('meta', json_decode($view->render(), true));

        // make sure a response with just/only a meta node is generated if
        // no corresponding entity data was retrieved from the viewVars
        // (as supported by the jsonapi spec)
        $view = $this->_getView('Countries', [
            'countries' => null,
            ], [
            'meta' => [
                'author' => 'bravo-kernel',
            ],
        ]);
        $expectedResponseArray = [
            'meta' => [
                'author' => 'bravo-kernel',
            ],
        ];

        $this->assertSame($expectedResponseArray, json_decode($view->render(), true));
    }

    /**
     * Make sure user option `debugPrettyPrint` behaves produces expected json
     *
     * @return void
     */
    public function testOptionalDebugPrettyPrint(): void
    {
        // make sure pretty json is generated when true AND in debug mode
        $countries = $this->getTableLocator()->get('Countries')
            ->find()
            ->first();
        $this->assertTrue(Configure::read('debug'));
        $view = $this->_getView('Countries', [
            'countries' => $countries,
            '_debugPrettyPrint' => true,
        ]);

        $this->assertSameAsFile(
            $this->_JsonApiResponseBodyFixtures . DS . 'FetchingResources' . DS . 'get-country-no-relationships.json',
            $view->render()
        );

        // make sure we can produce non-pretty in debug mode as well
        $countries = $this->getTableLocator()->get('Countries')
            ->find()
            ->first();
        $this->assertTrue(Configure::read('debug'));
        $view = $this->_getView('Countries', [
            'countries' => $countries,
        ], [
            'debugPrettyPrint' => false,
            'jsonOptions' => 0,
        ]);

        $this->assertSame(
            '{"data":{"type":"countries","id":"1","attributes":{"code":"NL","name":"The Netherlands","dummyCounter":11111},"relationships":{"currency":{"links":{"self":"\/countries\/1\/relationships\/currency","related":"\/countries\/1\/currency"},"data":{"type":"currencies","id":"1"}},"nationalCapital":{"links":{"self":"\/countries\/1\/relationships\/nationalCapital","related":"\/countries\/1\/nationalCapital"},"data":{"type":"nationalCapitals","id":"1"}},"cultures":{"links":{"self":"\/countries\/1\/relationships\/cultures","related":"\/countries\/1\/cultures"}},"nationalCities":{"links":{"self":"\/countries\/1\/relationships\/nationalCities","related":"\/countries\/1\/nationalCities"}},"subcountries":{"links":{"self":"\/countries\/1\/relationships\/subcountries","related":"\/countries\/1\/subcountries"}},"supercountry":{"links":{"self":"\/countries\/1\/relationships\/supercountry","related":"\/countries\/1\/supercountry"}}},"links":{"self":"\/countries\/1"}}}',
            $view->render()
        );
    }

    /**
     * Make sure the resource type is only the entity name
     *
     * @return void
     */
    public function testResourceTypes(): void
    {
        $countries = $this->getTableLocator()->get('Countries')
            ->find()
            ->first();
        $view = $this->_getView('Countries', [
            'countries' => $countries,
        ]);

        $this->assertEquals('countries', json_decode($view->render())->data->type);
    }

    /**
     * Make sure correct $data to be encoded is fetched from set viewVars
     *
     * @return void
     */
    public function testGetDataToSerializeFromViewVarsSuccess(): void
    {
        $view = $this
            ->getMockBuilder(JsonApiView::class)
            ->disableOriginalConstructor()
            ->setMethods(null)
            ->getMock();

        $this->setReflectionClassInstance($view);

        // make sure set expected data is returned when _serialize is true
        $view->set([
            'countries' => 'dummy-would-normally-be-an-entity-or-resultset',
        ]);

        $this->assertSame(
            'dummy-would-normally-be-an-entity-or-resultset',
            $this->callProtectedMethod('_getDataToSerializeFromViewVars', [], $view)
        );

        // make sure null is returned when no data is found (which would mean
        // only _specialVars were set and since these are all flipped it leads
        // to a null result)
        $view->setConfig('meta', false);
        $view->set('countries', null);
        $this->assertNull($this->callProtectedMethod('_getDataToSerializeFromViewVars', [], $view));

        // When passing an array as _serialize ONLY the first entity in that
        // array list will be used to return the corresponding viewar as $data.
        $view->set([
            'countries' => 'dummy-country-would-normally-be-an-entity-or-resultset',
            'currencies' => 'dummy-currency-would-normally-be-an-entity-or-resultset',
        ]);

        $parameters = [
            'countries',
            'currencies',
        ];

        $this->assertSame(
            'dummy-country-would-normally-be-an-entity-or-resultset',
            $this->callProtectedMethod('_getDataToSerializeFromViewVars', [$parameters], $view)
        );

        // In this case the first entity in the _serialize array does not have
        // a corresponding viewVar so null will be returned as data.
        $view->set([
            'currencies' => 'dummy-currency-would-normally-be-an-entity-or-resultset',
            'countries' => null,
        ]);

        $parameters = [
            'countries',
            'currencies',
        ];

        $this->assertNull($this->callProtectedMethod('_getDataToSerializeFromViewVars', [$parameters], $view));
    }

    /**
     * Make sure `_serialize` will not accept an object
     */
    public function testGetDataToSerializeFromViewVarsObjectExcecption(): void
    {
        $this->expectException(CrudException::class);
        $this->expectExceptionMessage(
            'Assigning an object to JsonApiListener "serialize" is deprecated, ' .
            'assign the object to its own variable and assign "serialize" = true instead.'
        );
        $view = $this
            ->getMockBuilder(JsonApiView::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->setReflectionClassInstance($view);
        $this->callProtectedMethod('_getDataToSerializeFromViewVars', [new StdClass()], $view);
    }

    /**
     * Make sure we are producing the right jsonOptions
     *
     * @return void
     */
    public function testJsonOptions(): void
    {
        $view = $this
            ->getMockBuilder(JsonApiView::class)
            ->disableOriginalConstructor()
            ->setMethods(null)
            ->getMock();

        $this->setReflectionClassInstance($view);

        // test debug mode with `debugPrettyPrint` option disabled
        $this->assertTrue(Configure::read('debug'));
        $view
            ->setConfig('debugPrettyPrint', false)
            ->setConfig(
                'jsonOptions',
                [
                        JSON_HEX_AMP, // 2
                        JSON_HEX_QUOT, // 8
                ]
            );
        $this->assertEquals(10, $this->callProtectedMethod('_jsonOptions', [], $view));

        // test debug mode with `debugPrettyPrint` option enabled
        $this->assertTrue(Configure::read('debug'));
        $view
            ->setConfig('debugPrettyPrint', true)
            ->setConfig(
                'jsonOptions',
                [
                    JSON_HEX_AMP, // 2
                    JSON_HEX_QUOT, // 8
                ]
            );
        $this->assertEquals(138, $this->callProtectedMethod('_jsonOptions', [], $view));

        // test production mode with `debugPrettyPrint` option disabled
        Configure::write('debug', false);
        $this->assertFalse(Configure::read('debug'));
        $view
            ->setConfig('debugPrettyPrint', false)
            ->setConfig(
                'jsonOptions',
                [
                    JSON_HEX_AMP, // 2
                    JSON_HEX_QUOT, // 8
                ]
            );
        $this->assertEquals(10, $this->callProtectedMethod('_jsonOptions', [], $view));

        // test production mode with `debugPrettyPrint` option enabled
        $this->assertFalse(Configure::read('debug'));
        $view
            ->setConfig('debugPrettyPrint', true)
            ->setConfig(
                'jsonOptions',
                [
                    JSON_HEX_AMP, // 2
                    JSON_HEX_QUOT, // 8
                ]
            );
        $this->assertEquals(10, $this->callProtectedMethod('_jsonOptions', [], $view));
    }

    /**
     * Make sure ApiPaginationListener information is added to the
     * expected `links` and `meta` nodes in the response.
     *
     * @return void
     */
    public function testApiPaginationListener(): void
    {
        $countries = $this->getTableLocator()->get('Countries')->find()->first();

        $view = $this->_getView('Countries', [
            'countries' => $countries,
        ], [
            'pagination' => [
                'self' => '/countries?page=2',
                'first' => '/countries?page=1',
                'last' => '/countries?page=3',
                'prev' => '/countries?page=1',
                'next' => '/countries?page=3',
                'record_count' => 28,
                'page_count' => 3,
                'page_limit' => 10,
            ],
        ]);

        $jsonArray = json_decode($view->render(), true);

        // assert `links` node is filled as expected
        $this->assertArrayHasKey('links', $jsonArray);
        $links = $jsonArray['links'];

        $this->assertEquals('/countries?page=2', $links['self']);
        $this->assertEquals('/countries?page=1', $links['first']);
        $this->assertEquals('/countries?page=3', $links['last']);
        $this->assertEquals('/countries?page=1', $links['prev']);
        $this->assertEquals('/countries?page=3', $links['next']);

        // assert `meta` node is filled as expected
        $this->assertArrayHasKey('meta', $jsonArray);
        $meta = $jsonArray['meta'];

        $this->assertEquals($meta['record_count'], 28);
        $this->assertEquals($meta['page_count'], 3);
        $this->assertEquals($meta['page_limit'], 10);
    }

    /**
     * Make sure NeoMerx pagination Links are generated from `_pagination`
     * viewVar set by ApiPaginationListener.
     *
     * @return void
     */
    public function testGetPaginationLinks(): void
    {
        $pagination = [
            'self' => 'http://api.app/v0/countries?page=2',
            'first' => 'http://api.app/v0/countries?page=1',
            'last' => 'http://api.app/v0/countries?page=3',
            'prev' => 'http://api.app/v0/countries?page=1',
            'next' => 'http://api.app/v0/countries?page=3',
            'record_count' => 42, // should be skipped
            'page_count' => 3, // should be skipped
            'page_limit' => 10, // should be skipped
        ];

        $view = $this
            ->getMockBuilder(JsonApiView::class)
            ->disableOriginalConstructor()
            ->setMethods(null)
            ->getMock();

        $this->setReflectionClassInstance($view);
        $links = $this->callProtectedMethod('_getPaginationLinks', [$pagination], $view);

        // assert all 5 elements in the array are NeoMerx Link objects
        $this->assertCount(5, $links);

        foreach ($links as $link) {
            $this->assertInstanceOf(Link::class, $link);
        }
    }

    /**
     * Make sure ApiQueryLogListener information is added to the `query` node.
     *
     * @return void
     */
    public function testApiQueryLogListener(): void
    {
        $countries = $this->getTableLocator()->get('Countries')->find()->first();

        $view = $this->_getView('Countries', [
            'countries' => $countries,
            'queryLog' => 'viewVar only set by ApiQueryLogListener',
        ]);

        $jsonArray = json_decode($view->render(), true);

        // assert `links` node is filled as expected
        $this->assertArrayHasKey('query', $jsonArray);
        $this->assertEquals($jsonArray['query'], 'viewVar only set by ApiQueryLogListener');
    }
}
