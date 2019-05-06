<?php
namespace CrudJsonApi\Test\TestCase\Integration;

use Cake\Core\Configure;
use Cake\Core\Plugin;
use Cake\Filesystem\File;
use Cake\Routing\Router;
use Cake\TestSuite\IntegrationTestCase;
use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\StringCompareTrait;
use Cake\TestSuite\TestCase;
use CrudJsonApi\Error\JsonApiExceptionRenderer;

abstract class JsonApiBaseTestCase extends TestCase
{
    use IntegrationTestTrait;
    use StringCompareTrait;

    /**
     * Path to directory holding the JSON API request body fixtures.
     *
     * @var
     */
    protected $_JsonApiRequestBodyFixtures;

    /**
     * Path to directory holding the JSON API resonse body fixtures.
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
        'plugin.CrudJsonApi.Currencies',
        'plugin.CrudJsonApi.Cultures',
        'plugin.CrudJsonApi.NationalCapitals',
        'plugin.CrudJsonApi.NationalCities',
    ];

    /**
     * Set up required RESTful resource routes.
     */
    public function setUp()
    {
        parent::setUp();

        $this->deprecated(function () {
            \Cake\Core\Plugin::load('Crud', ['path' => ROOT . DS, 'autoload' => true]);
            \Cake\Core\Plugin::load('CrudJsonApi', ['path' => ROOT . DS, 'autoload' => true]);
        });

        // Enable PSR-7 integration testing
        $this->useHttpServer(true);

        Configure::write('Error.exceptionRenderer', JsonApiExceptionRenderer::class);

        Router::scope('/', function ($routes) {
            $routes->resources('Countries', [
                'inflect' => 'dasherize'
            ]);
            $routes->resources('Currencies', [ // single word belongsTo association
                'inflect' => 'dasherize'
            ]);
            $routes->resources('Cultures', [ // single word hasMany association
                'inflect' => 'dasherize'
            ]);
            $routes->resources('NationalCapitals', [ // multi-word belongsTo association
                'inflect' => 'dasherize'
            ]);
            $routes->resources('NationalCities', [ // multi-word hasMany association
                'inflect' => 'dasherize'
            ]);
        });

        $this->configRequest([
            'headers' => [
                'Accept' => 'application/vnd.api+json'
            ]
        ]);

        // set path to the JSON API fixtures
        $this->_JsonApiResponseBodyFixtures = Plugin::path('Crud') . 'tests' . DS . 'Fixture' . DS . 'JsonApiResponseBodies';
        $this->_JsonApiRequestBodyFixtures = Plugin::path('Crud') . 'tests' . DS . 'Fixture' . DS . 'JsonApiRequestBodies';
    }

    /**
     * Tear down test.
     */
    public function tearDown()
    {
        parent::tearDown();
    }

    /**
     * Helper function to ensure a JSON API response returns the mandatory headers.
     *
     * @return void
     */
    protected function _assertJsonApiResponseHeaders()
    {
        $this->assertHeader('Content-Type', 'application/vnd.api+json');
        $this->assertContentType('application/vnd.api+json');
    }

    /**
     * Helper function to remove content from the `debug` node in JSON API responses
     *
     */
    protected function _getResponseWithEmptyDebugNode($responseBody)
    {
        $pattern = '/("debug".+)}/s';
        $replacement = "\"debug\": {}\n}";
        $result = preg_replace($pattern, $replacement, $responseBody);

        return $result;
    }

    /**
     * Helper function to load `JsonApiRequestBodies` fixture from file for use as `ConfigRequest.input` in the assertions.
     *
     * return @void
     */
    protected function _getJsonApiRequestBody($file)
    {
        $file = $this->_JsonApiRequestBodyFixtures . DS . $file;

        return trim((new File($file))->read());
    }

    /**
     * Asserts that the response is the same as the supplied file.
     *
     * @param string $file Filename to check
     * @param string $response Override the response to check
     * @return void
     */
    public function assertResponseSameAsFile(string $file, string $response = null)
    {
        $this->assertSameAsFile($this->_JsonApiResponseBodyFixtures . DS . $file, $response ?: $this->_getBodyAsString());
    }

    /**
     * Helper function to load 'JsonApiResponseBodies` fixture from file for use as `expected` in the assertions.
     *
     * return @void
     */
    protected function _getExpectedResponseBody($file)
    {
        return trim((new File($this->_JsonApiResponseBodyFixtures . DS . $file))->read());
    }
}
