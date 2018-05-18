<?php
namespace CrudJsonApi\Test\TestCase\Integration\JsonApi;

use CrudJsonApi\Test\TestCase\Integration\JsonApiBaseTestCase;

class GetResourceRequestIntegrationTest extends JsonApiBaseTestCase
{
    /**
     * Test most basic `index` action
     *
     * @return void
     */
    public function testGet()
    {
        $this->get('/countries/1');

        $this->assertResponseOk();
        $this->_assertJsonApiResponseHeaders();
        $this->assertResponseCode(200);
        $this->assertResponseNotEmpty();
        $this->assertResponseEquals($this->_getExpected('GetResourceRequests' . DS . 'got-single-word-resource.json'));
    }
}
