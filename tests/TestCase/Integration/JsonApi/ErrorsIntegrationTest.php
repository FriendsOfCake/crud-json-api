<?php
namespace CrudJsonApi\Test\TestCase\Integration\JsonApi;

use Cake\Core\Configure;
use CrudJsonApi\Test\TestCase\Integration\JsonApiBaseTestCase;

class ErrorsIntegrationTest extends JsonApiBaseTestCase
{
    /**
     * Make sure 404 errors for Collections respond properly in debug-mode.
     */
    public function test404ForCollectionInDebugMode()
    {
        Configure::write('debug', true);

        $this->get('/nonexistents');
        $this->assertResponseCode(404);
        $this->_assertJsonApiResponseHeaders();

        $actualResponseBody = $this->_getResponseWithEmptyDebugNode($this->_getBodyAsString());
        $this->assertResponseSameAsFile('Errors' . DS . '404-error-for-collection-in-debug-mode.json', $actualResponseBody);
    }

    /**
     * Make sure 404 errors for Collections respond properly in production mode.
     */
    public function test404ForCollectionInProductionMode()
    {
        Configure::write('debug', false);

        $this->get('/nonexistents');
        $this->assertResponseCode(404);
        $this->_assertJsonApiResponseHeaders();

        $this->assertResponseSameAsFile('Errors' . DS . '404-error-for-collection-in-production-mode.json');
    }

    /**
     * Make sure 404 errors for Resources respond properly in debug-mode.
     */
    public function test404ForResourceInDebugMode()
    {
        Configure::write('debug', true);

        $this->get('/countries/666');
        $this->assertResponseCode(404);
        $this->_assertJsonApiResponseHeaders();

        $actualResponseBody = $this->_getResponseWithEmptyDebugNode($this->_getBodyAsString());
        $this->assertResponseSameAsFile('Errors' . DS . '404-error-for-resource-in-debug-mode.json', $actualResponseBody);
    }

    /**
     * Make sure 404 errors for Resources respond properly in production-mode.
     */
    public function test404ForResourceInProductionMode()
    {
        Configure::write('debug', false);

        $this->get('/countries/666');
        $this->assertResponseCode(404);
        $this->_assertJsonApiResponseHeaders();
        $this->assertResponseSameAsFile('Errors' . DS . '404-error-for-resource-in-production-mode.json');
    }
}
