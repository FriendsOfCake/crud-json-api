<?php
namespace CrudJsonApi\Test\TestCase\Integration\JsonApi;

use Cake\Core\Configure;
use CrudJsonApi\Test\TestCase\Integration\JsonApiBaseTestCase;

class ErrorsIntegrationTest extends JsonApiBaseTestCase
{
    /**
     * Make sure 404 Not Found is properly handled for Resources in non-debug mode
     */
    public function test404ForResourcesInProductionMode()
    {
        Configure::write('debug', false);

        $this->get('/countries/666');
        $this->assertResponseCode(404);
        $this->_assertJsonApiResponseHeaders();
        $this->assertResponseEquals($this->_getJsonApiResponseBody('Errors' . DS . '404-error-not-found-resource-production-mode.json'));
    }

    /**
     * Make sure 404 Not Found is properly handled for Resources in non-debug mode
     */
    public function test404ForCollectionsInProductionMode()
    {
        Configure::write('debug', false);

        $this->get('/nonexistents');
        $this->assertResponseCode(404);
        $this->_assertJsonApiResponseHeaders();
        $this->assertResponseEquals($this->_getJsonApiResponseBody('Errors' . DS . '404-error-not-found-collection-production-mode.json'));
    }
}
