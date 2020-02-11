<?php
declare(strict_types=1);

namespace CrudJsonApi\Test\TestCase\Schema\JsonApi;

use Cake\Controller\Controller;
use Cake\ORM\TableRegistry;
use Cake\View\View;
use Crud\TestSuite\TestCase;
use CrudJsonApi\Listener\JsonApiListener;
use CrudJsonApi\Schema\JsonApi\DynamicEntitySchema;
use Neomerx\JsonApi\Contracts\Factories\FactoryInterface;
use Neomerx\JsonApi\Contracts\Schema\ContextInterface;
use Neomerx\JsonApi\Contracts\Schema\SchemaInterface;
use Neomerx\JsonApi\Factories\Factory;
use Neomerx\JsonApi\Schema\Identifier;

/**
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 */
class DynamicEntitySchemaTest extends TestCase
{
    /**
     * fixtures property
     *
     * @var array
     */
    public $fixtures = [
        'plugin.CrudJsonApi.Countries',
        'plugin.CrudJsonApi.Cultures',
        'plugin.CrudJsonApi.Currencies',
    ];

    /**
     * @return void
     */
    public function setUp(): void
    {
        parent::setUp();

        require CONFIG . 'routes.php';
    }


    /**
     * Test NeoMerx override getAttributes().
     *
     * @return void
     */
    public function testGetAttributes()
    {
        // fetch data to test against
        $table = TableRegistry::get('Countries');
        $query = $table->find()
            ->where([
                'Countries.id' => 2,
            ])
            ->contain([
                'Cultures',
                'Currencies',
            ]);

        $entity = $query->first();

        // make sure we are testing against expected baseline
        $expectedCurrencyId = 1;
        $expectedFirstCultureId = 2;
        $expectedSecondCultureId = 3;

        $this->assertArrayHasKey('currency', $entity);
        $this->assertSame($expectedCurrencyId, $entity['currency']['id']);

        $this->assertArrayHasKey('cultures', $entity);
        $this->assertCount(2, $entity['cultures']);
        $this->assertSame($expectedFirstCultureId, $entity['cultures'][0]['id']);
        $this->assertSame($expectedSecondCultureId, $entity['cultures'][1]['id']);

        // get required AssociationsCollection
        $listener = new JsonApiListener(new Controller());
        $this->setReflectionClassInstance($listener);
        $associations = $this->callProtectedMethod('_getContainedAssociations', [$table, $query->getContain()], $listener);
        $repositories = $this->callProtectedMethod('_getRepositoryList', [$table, $associations], $listener);

        // make view return associations on get('_associations') call
        $view = $this
            ->getMockBuilder(View::class)
            ->onlyMethods(['get'])
            ->disableOriginalConstructor()
            ->getMock();

        $view->setConfig('repositories', $repositories);
        $view->setConfig('inflect', 'dasherize');

        // setup the schema
        $schemaFactoryInterface = $this
            ->getMockBuilder(FactoryInterface::class)
            ->disableOriginalConstructor()
            ->getMock();

        $schema = $this
            ->getMockBuilder(DynamicEntitySchema::class)
            ->setConstructorArgs([$schemaFactoryInterface, $view, $table])
            ->onlyMethods([])
            ->getMock();

        $this->setReflectionClassInstance($schema, DynamicEntitySchema::class);
        $this->setReflectionClassInstance($schema);

        $this->setProtectedProperty('view', $view, DynamicEntitySchema::class);

        $context = $this->getMockBuilder(ContextInterface::class)
            ->getMock();

        // assert method
        $result = $this->callProtectedMethod('getAttributes', [$entity, $context], $schema);

        $this->assertSame('BG', $result['code']);
        $this->assertArrayNotHasKey('id', $result);
        $this->assertArrayNotHasKey('currency', $result); // relationships should be removed
        $this->assertArrayNotHasKey('cultures', $result);
    }

    /**
     * Test both NeoMerx override methods getRelationships() and
     * getRelationshipSelfLinks() responsible for generating the
     * JSON API 'relationships` node with matching `self` links.
     *
     * @return void
     */
    public function testRelationships()
    {
        // fetch associated data to test against
        $table = TableRegistry::get('Countries');
        $query = $table->find()
            ->where([
                'Countries.id' => 2,
            ])
            ->contain([
                'Cultures',
                'Currencies',
            ]);

        $entity = $query->first();

        // make sure we are testing against expected baseline
        $expectedCurrencyId = 1;
        $expectedFirstCultureId = 2;
        $expectedSecondCultureId = 3;

        $this->assertArrayHasKey('currency', $entity);
        $this->assertSame($expectedCurrencyId, $entity['currency']['id']);

        $this->assertArrayHasKey('cultures', $entity);
        $this->assertCount(2, $entity['cultures']);
        $this->assertSame($expectedFirstCultureId, $entity['cultures'][0]['id']);
        $this->assertSame($expectedSecondCultureId, $entity['cultures'][1]['id']);

        // get required AssociationsCollection
        $listener = new JsonApiListener(new Controller());
        $this->setReflectionClassInstance($listener);
        $associations = $this->callProtectedMethod('_getContainedAssociations', [$table, $query->getContain()], $listener);
        $repositories = $this->callProtectedMethod('_getRepositoryList', [$table, $associations], $listener);

        // make view return associations on get('_associations') call
        $view = new View();

        $view->setConfig('repositories', $repositories);
        $view->setConfig('absoluteLinks', false); // test relative links (listener default)
        $view->setConfig('inflect', 'dasherize');


        // setup the schema
        $schema = new DynamicEntitySchema(new Factory(), $view, $table);

        // assert getRelationships()
        $context = $this->getMockBuilder(ContextInterface::class)
            ->getMock();
        $relationships = $schema->getRelationships($entity, $context);

        $this->assertArrayHasKey('currency', $relationships);
        $this->assertSame($expectedCurrencyId, $relationships['currency'][SchemaInterface::RELATIONSHIP_DATA]['id']);

        $this->assertArrayHasKey('cultures', $relationships);
        $this->assertCount(2, $relationships['cultures'][SchemaInterface::RELATIONSHIP_DATA]);
        $this->assertSame($expectedFirstCultureId, $relationships['cultures'][SchemaInterface::RELATIONSHIP_DATA][0]['id']);
        $this->assertSame($expectedSecondCultureId, $relationships['cultures'][SchemaInterface::RELATIONSHIP_DATA][1]['id']);

        // assert generated belongsToLink using listener default (direct link)
        $view->set('_jsonApiBelongsToLinks', false);
        $expected = '/currencies/1';
        $result = $schema->getRelationshipSelfLink($entity, 'currency');
        $this->setReflectionClassInstance($result);
        $this->assertSame($expected, $this->getProtectedProperty('value', get_class($result)));

        // assert generated belongsToLink using JsonApi (indirect link, requires custom JsonApiRoute)
        $view->set('_jsonApiBelongsToLinks', true);
        $expected = '/countries/2/relationships/currency';
        $result = $schema->getRelationshipSelfLink($entity, 'currency');
        $this->setReflectionClassInstance($result);
        $this->assertSame($expected, $this->getProtectedProperty('value', get_class($result)));

        // assert _ getRelationshipSelfLinks() for plural (hasMany)
        $expected = '/cultures?country_id=2';

        $result = $schema->getRelationshipRelatedLink($entity, 'cultures');
        $this->setReflectionClassInstance($result);
        $this->assertSame($expected, $this->getProtectedProperty('value', get_class($result)));

        // assert N-1 (i.e. belongs-to)  relationships are always included as a relationship
        unset($entity['currency']);
        $this->assertArrayNotHasKey('currency', $entity);

        $result = $schema->getRelationships($entity, $context);
        $this->assertArrayHasKey('currency', $result);
        $this->assertArrayHasKey('cultures', $result);
        $this->assertInstanceOf(Identifier::class, $result['currency'][DynamicEntitySchema::RELATIONSHIP_DATA]);

        // assert other valid relations are not included if no data is loaded
        // fetch associated data to test against
        $table = TableRegistry::get('Countries');
        $query = $table->find()
            ->where([
                'Countries.id' => 2,
            ])
            ->contain([
                'Cultures',
                'Currencies',
            ]);

        $entity = $query->first();
        unset($entity['cultures']);
        $this->assertArrayNotHasKey('cultures', $entity);

        $result = $schema->getRelationships($entity, $context);
        $this->assertArrayNotHasKey('cultures', $result);
        $this->assertArrayHasKey('currency', $result);

        // test custom foreign key
        $query = $table->find()
            ->where([
                'Countries.id' => 4,
            ])
            ->contain([
                'SuperCountries',
            ]);

        $entity = $query->first();
        $this->assertArrayHasKey('supercountry_id', $entity);
        $this->assertArrayHasKey('supercountry', $entity);

        // test custom propertyName
        $query = $table->find()
            ->where([
                'Countries.id' => 3,
            ])
            ->contain([
                'SubCountries',
            ]);

        $entity = $query->first();
        $this->assertArrayHasKey('subcountries', $entity);
    }
}
