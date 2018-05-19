<?php
namespace CrudJsonApi\Test\TestCase\Integration\JsonApi;

use CrudJsonApi\Test\TestCase\Integration\JsonApiBaseTestCase;

class ErrorsIntegrationTest extends JsonApiBaseTestCase
{
    /**
     * Make sure 404 Not Found is properly handled for Resources
     */
    public function test404ForResources()
    {
        $this->get('/countries/666');
        $this->assertResponseCode(404);
        $this->_assertJsonApiResponseHeaders();
        #$this->assertResponseEquals($this->_getJsonApiResponseBody('Errors' . DS . '404-error-not-found-resource.json'));
    }

    /**
     * Make sure 404 Not Found is properly handled for Resources
     */
    public function test404ForCollections()
    {
        $this->get('/nonexistents');
        $this->assertResponseCode(404);
        $this->_assertJsonApiResponseHeaders();
        #$this->assertResponseEquals($this->_getJsonApiResponseBody('Errors' . DS . '404-error-not-found-collection.json'));
    }
}
