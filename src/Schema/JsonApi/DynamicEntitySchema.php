<?php
namespace CrudJsonApi\Schema\JsonApi;

use Cake\Core\App;
use Cake\Datasource\EntityInterface;
use Cake\ORM\Association;
use Cake\ORM\Table;
use Cake\Routing\Router;
use Cake\Utility\Inflector;
use Cake\View\View;
use Neomerx\JsonApi\Contracts\Factories\FactoryInterface;
use Neomerx\JsonApi\Contracts\Schema\LinkInterface;
use Neomerx\JsonApi\Schema\BaseSchema;
use Neomerx\JsonApi\Schema\Identifier;

/**
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 */
class DynamicEntitySchema extends BaseSchema
{
    /**
     * Holds the instance of Cake\View\View
     * @var \Cake\View\View
     */
    protected $view;
    /**
     * @var \Cake\ORM\Table
     */
    protected $repository;

    /**
     * Class constructor
     *
     * @param \Neomerx\JsonApi\Contracts\Factories\FactoryInterface $factory ContainerInterface
     * @param \Cake\View\View $view Instance of the cake view we are rendering this in
     * @param \Cake\ORM\Table $repository Repository to use
     */
    public function __construct(
        FactoryInterface $factory,
        View $view,
        Table $repository
    ) {
        $this->view = $view;
        $this->repository = $repository;

        parent::__construct($factory);
    }

    /**
     * @param \Cake\ORM\Table $repository The repository object
     *
     * @return mixed
     */
    private function getTypeFromRepository(Table $repository)
    {
        $repositoryName = App::shortName(get_class($repository), 'Model/Table', 'Table');
        [, $entityName] = pluginSplit($repositoryName);
        $method = $this->view->get('_inflect', 'dasherize');

        return Inflector::$method($entityName);
    }

    /**
     * @inheritDoc
     */
    public function getType(): string
    {
        return $this->getTypeFromRepository($this->getRepository());
    }

    /**
     * Get resource id.
     *
     * @param \Cake\ORM\Entity $entity Entity
     * @return string
     */
    public function getId($entity): ?string
    {
        return (string)$entity->get($this->repository->getPrimaryKey());
    }

    /**
     * @param \Cake\Datasource\EntityInterface $entity Entity
     * @return \Cake\ORM\Table
     */
    protected function getRepository($entity = null): Table
    {
        if (!$entity) {
            return $this->repository;
        }

        $repositoryName = $entity->getSource();

        return $this->view->get('_repositories')[$repositoryName];
    }

    /**
     * Returns an array with all the properties that have been set
     * to this entity
     *
     * This method will ignore any properties that are entities.
     *
     * @param \Cake\Datasource\EntityInterface $entity Entity
     * @return array
     */
    protected function entityToShallowArray(EntityInterface $entity)
    {
        $result = [];
        $properties = method_exists($entity, 'getVisible')
            ? $entity->getVisible()
            : $entity->visibleProperties();
        foreach ($properties as $property) {
            if ($property === '_joinData') {
                continue;
            }

            $value = $entity->get($property);
            if (is_array($value)) {
                $result[$property] = [];
                foreach ($value as $k => $innerValue) {
                    if (!$innerValue instanceof EntityInterface) {
                        $result[$property][$k] = $innerValue;
                    }
                }
            } else {
                $result[$property] = $value;
            }
        }

        return $result;
    }

    /**
     * NeoMerx override used to pass entity root properties to be shown
     * as JsonApi `attributes`.
     *
     * @param \Cake\Datasource\EntityInterface $entity Entity
     * @return array
     */
    public function getAttributes($entity): iterable
    {
        $entity->setHidden((array)$this->getRepository()->getPrimaryKey(), true);

        $attributes = $this->entityToShallowArray($entity);

        // remove associated data so it won't appear inside jsonapi `attributes`
        foreach ($this->getRepository()->associations() as $association) {
            $propertyName = $association->getProperty();

            if ($association->type() === Association::MANY_TO_ONE) {
                $foreignKey = (array)$association->getForeignKey();
                $attributes = array_diff_key($attributes, array_flip($foreignKey));
            }

            unset($attributes[$propertyName]);
        }

        // dasherize attribute keys (like `created_by`) if need be
        if ($this->view->get('_inflect', 'dasherize') === 'dasherize') {
            foreach ($attributes as $key => $value) {
                $dasherizedKey = Inflector::dasherize($key);

                if (!array_key_exists($dasherizedKey, $attributes)) {
                    $attributes[$dasherizedKey] = $value;
                    unset($attributes[$key]);
                }
            }
        }

        return $attributes;
    }

    /**
     * NeoMerx override used to pass associated entity names to be used for
     * generating JsonApi `relationships`.
     *
     * JSON API optional `related` links not implemented yet.
     *
     * @param \Cake\Datasource\EntityInterface $entity Entity object
     * @return array
     */
    public function getRelationships($entity): iterable
    {
        $relations = [];

        foreach ($this->getRepository()->associations() as $association) {
            $property = $association->getProperty();
            $foreignKey = $association->getForeignKey();

            $data = $entity->get($property);
            //If no data, and it's not a BelongsTo relationship, skip
            if (!$data && $association->type() !== Association::MANY_TO_ONE) {
                continue;
            }

            //If there is no data, and the foreignKey field is null, skip
            if (!$data && (is_array($foreignKey) || !$entity->get($foreignKey))) {
                continue;
            }

            // change related  data in entity to dasherized if need be
            if ($this->view->get('_inflect', 'dasherize') === 'dasherize') {
                $dasherizedProperty = Inflector::dasherize($property);

                if (empty($entity->$dasherizedProperty)) {
                    $entity->$dasherizedProperty = $entity->$property;
                    unset($entity->$property);
                    $property = $dasherizedProperty;
                }
            }

            if (!$data && !is_array($foreignKey)) {
                $data = new Identifier($entity->get($foreignKey), $this->getTypeFromRepository($association->getTarget()));
            }

            $isOne = \in_array($association->type(), [Association::MANY_TO_ONE, Association::ONE_TO_ONE]);
            $relations[$property] = [
                self::RELATIONSHIP_DATA => $data,
                self::RELATIONSHIP_LINKS_SELF => $isOne,
                self::RELATIONSHIP_LINKS_RELATED => !$isOne,
            ];
        }

        return $relations;
    }

    /**
     * NeoMerx override used to generate `self` links
     *
     * @param \Cake\ORM\Entity|null $entity Entity, null only to be compatible with the Neomerx method
     * @return string
     */
    public function getSelfSubUrl($entity = null): string
    {
        if ($entity === null) {
            return '';
        }

        $keys = array_values($entity->extract((array)$this->getRepository()->getPrimaryKey()));

        return Router::url($this->_getRepositoryRoutingParameters($this->repository) + $keys + [
            '_method' => 'GET',
            'action' => 'view',
        ], $this->view->get('_absoluteLinks'));
    }

    /**
     * @param string $name Relationship name in lowercase singular or plural
     *
     * @return \Cake\ORM\Association|null
     */
    protected function getAssociationByProperty(string $name): ?Association
    {
        if ($this->view->get('_inflect', 'dasherize') === 'dasherize') {
            $name = Inflector::underscore($name);
        }

        return $this->getRepository()
            ->associations()
            ->getByProperty($name);
    }

    /**
     * NeoMerx override to generate belongsTo and hasOne links
     * inside `relationships` node.
     *
     * Example: /cultures?country_id=1 (or /country/1/cultures if your routes are configured like this)
     *
     * @param \Cake\Datasource\EntityInterface $entity Entity
     * @param string $name Relationship name in lowercase singular or plural
     *
     * @return \Neomerx\JsonApi\Contracts\Schema\LinkInterface
     */
    public function getRelationshipSelfLink($entity, string $name): LinkInterface
    {
        $association = $this->getAssociationByProperty($name);
        if (!$association) {
            throw new \InvalidArgumentException('Invalid association ' . $name);
        }

        $relatedRepository = $association->getTarget();

        // generate link for belongsTo relationship
        if ($this->view->get('_jsonApiBelongsToLinks') === true) {
            [, $controllerName] = pluginSplit($this->getRepository()->getRegistryAlias());
            $sourceName = Inflector::underscore(Inflector::singularize($controllerName));

            $url = Router::url($this->_getRepositoryRoutingParameters($relatedRepository) + [
                '_method' => 'GET',
                'action' => 'view',
                $sourceName . '_id' => $entity->id,
                'from' => $this->getRepository()->getRegistryAlias(),
                'type' => $name,
            ], $this->view->get('_absoluteLinks'));
        } else {
            $name = Inflector::dasherize($name);
            $relatedEntity = $entity->get($name);

            if ($relatedEntity) {
                $keys = array_values($relatedEntity->extract((array)$relatedRepository->getPrimaryKey()));
            } else {
                $keys = array_values($entity->extract((array)$association->getForeignKey()));
            }

            $url = Router::url($this->_getRepositoryRoutingParameters($relatedRepository) + $keys + [
                '_method' => 'GET',
                'action' => 'view',
            ], $this->view->get('_absoluteLinks'));
        }

        return $this->getFactory()->createLink(false, $url, false);
    }

    /**
     * NeoMerx override to generate hasMany and belongsToMany links
     * inside `relationships` node.
     *
     * hasMany example"   /countries/1/currencies"
     *
     * @param \Cake\Datasource\EntityInterface $entity Entity
     * @param string $name Relationship name in lowercase singular or plural
     *
     * @return \Neomerx\JsonApi\Contracts\Schema\LinkInterface
     */
    public function getRelationshipRelatedLink($entity, string $name): LinkInterface
    {
        $association = $this->getAssociationByProperty($name);
        if (!$association) {
            throw new \InvalidArgumentException('Invalid association ' . $name);
        }

        $relatedRepository = $association->getTarget();

        // generate the link for hasMany relationship
        $foreignKeys = (array)$association->getForeignKey();
        $primaryKeys = $entity->extract((array)$this->getRepository()->getPrimaryKey());
        $keys = array_combine($foreignKeys, $primaryKeys);

        $url = Router::url($this->_getRepositoryRoutingParameters($relatedRepository) + $keys + [
                '_method' => 'GET',
                'action' => 'index',
            ], $this->view->get('_absoluteLinks'));

        return $this->getFactory()
            ->createLink(false, $url, false);
    }

    /**
     * Parses the name of an Entity class to build a lowercase plural
     * controller name to be used in links.
     *
     * @param \Cake\Datasource\RepositoryInterface $repository Repository
     * @return array Array holding lowercase controller name as the value
     */
    protected function _getRepositoryRoutingParameters($repository)
    {
        $repositoryName = App::shortName(get_class($repository), 'Model/Table', 'Table');
        [$pluginName, $controllerName] = pluginSplit($repositoryName);

        return [
            'controller' => $controllerName,
            'plugin' => $pluginName
        ];
    }
}
