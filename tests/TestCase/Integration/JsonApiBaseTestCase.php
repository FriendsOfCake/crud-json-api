<?php
declare(strict_types=1);

namespace CrudJsonApi\Test\TestCase\Integration;

use Cake\Core\Configure;
use Cake\Core\Plugin;
use Cake\Filesystem\File;
use Cake\Routing\Router;
use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\StringCompareTrait;
use Cake\TestSuite\TestCase;
use CrudJsonApi\Error\JsonApiExceptionRenderer;
use CrudJsonApi\Route\JsonApiRoutes;

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
        'plugin.CrudJsonApi.Languages',
        'plugin.CrudJsonApi.CountriesLanguages',
    ];

    /**
     * Set up required RESTful resource routes.
     */
    public function setUp(): void
    {
        parent::setUp();

        Configure::write('Error.exceptionRenderer', JsonApiExceptionRenderer::class);

        Router::createRouteBuilder('/')->scope('/', function ($routes) {
            JsonApiRoutes::mapModels([
                'Countries',
                'Currencies',
                'Cultures',
                'NationalCapitals',
                'NationalCities',
                'Languages',
            ], $routes);
        });

        $this->configRequest([
            'headers' => [
                'Accept' => 'application/vnd.api+json',
            ],
        ]);

        // set path to the JSON API fixtures
        $this->_JsonApiResponseBodyFixtures = Plugin::path('CrudJsonApi') . 'tests' . DS . 'Fixture' . DS . 'JsonApiResponseBodies';
        $this->_JsonApiRequestBodyFixtures = Plugin::path('CrudJsonApi') . 'tests' . DS . 'Fixture' . DS . 'JsonApiRequestBodies';
    }

    /**
     * Tear down test.
     */
    public function tearDown(): void
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

        return trim(file_get_contents($file));
    }

    /**
     * Asserts that the response is the same as the supplied file.
     *
     * @param string $file Filename to check
     * @param string $response Override the response to check
     * @return void
     */
    public function assertResponseSameAsFile(string $file, ?string $response = null)
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
