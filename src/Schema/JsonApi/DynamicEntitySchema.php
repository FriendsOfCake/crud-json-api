<?php
declare(strict_types=1);

namespace CrudJsonApi\Schema\JsonApi;

use Cake\Core\App;
use Cake\Datasource\EntityInterface;
use Cake\ORM\Association;
use Cake\ORM\Table;
use Cake\Routing\Exception\MissingRouteException;
use Cake\Routing\Router;
use Cake\Utility\Hash;
use Cake\Utility\Inflector;
use Cake\View\View;
use CrudJsonApi\InflectTrait;
use InvalidArgumentException;
use Neomerx\JsonApi\Contracts\Factories\FactoryInterface;
use Neomerx\JsonApi\Contracts\Schema\ContextInterface;
use Neomerx\JsonApi\Contracts\Schema\LinkInterface;
use Neomerx\JsonApi\Schema\BaseSchema;
use Neomerx\JsonApi\Schema\Identifier;

/**
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 */
class DynamicEntitySchema extends BaseSchema
{
    use InflectTrait;

    /**
     * Holds the instance of Cake\View\View
     *
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
     * @param \Neomerx\JsonApi\Contracts\Factories\FactoryInterface $factory    ContainerInterface
     * @param \Cake\View\View                                       $view       Instance of the cake view we are rendering this in
     * @param \Cake\ORM\Table                                       $repository Repository to use
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
     * @return mixed
     */
    protected function getTypeFromRepository(Table $repository)
    {
        $repositoryName = App::shortName(get_class($repository), 'Model/Table', 'Table');
        [, $entityName] = pluginSplit($repositoryName);

        return $this->inflect($this->view, $entityName);
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
     * @param  \Cake\ORM\Entity $entity Entity
     * @return string
     */
    public function getId($entity): ?string
    {
        $primaryKey = $this->repository->getPrimaryKey();

        if (is_array($primaryKey)) {
            throw new \RuntimeException('Crud-Json-Api does not support composite keys out of the box.');
        }

        return (string)$entity->get($primaryKey);
    }

    /**
     * @param  \Cake\Datasource\EntityInterface $entity Entity
     * @return \Cake\ORM\Table
     */
    protected function getRepository($entity = null): Table
    {
        if (!$entity) {
            return $this->repository;
        }

        $repositoryName = $entity->getSource();

        return $this->view->getConfig('repositories')[$repositoryName];
    }

    /**
     * Returns an array with all the properties that have been set
     * to this entity
     *
     * This method will ignore any properties that are entities.
     *
     * @param  \Cake\Datasource\EntityInterface $entity Entity
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
     * @param \Neomerx\JsonApi\Contracts\Schema\ContextInterface $context The Context
     * @return array
     */
    public function getAttributes($entity, ContextInterface $context): iterable
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

        // inflect attribute keys (like `created_by`)
        foreach ($attributes as $key => $value) {
            $inflectedKey = $this->inflect($this->view, $key);

            if (!array_key_exists($inflectedKey, $attributes)) {
                unset($attributes[$key]);
                $attributes[$inflectedKey] = $value;
            }
        }

        ksort($attributes);

        return $attributes;
    }

    /**
     * NeoMerx override used to pass associated entity names to be used for
     * generating JsonApi `relationships`.
     *
     * JSON API optional `related` links not implemented yet.
     *
     * @param  \Cake\Datasource\EntityInterface $entity Entity object
     * @param \Neomerx\JsonApi\Contracts\Schema\ContextInterface $context The Context
     * @return array
     */
    public function getRelationships($entity, ContextInterface $context): iterable
    {
        $relations = [];

        foreach ($this->getRepository()->associations() as $association) {
            $property = $association->getProperty();
            $foreignKey = $association->getForeignKey();

            $data = $entity->get($property);
            //If no data, and it's not a BelongsTo relationship, use no data
            if (!$data && $association->type() !== Association::MANY_TO_ONE) {
                $data = false;
            }

            //If there is no data, and the foreignKey field is null, skip
            if (!$data && (is_array($foreignKey) || !$entity->get($foreignKey))) {
                $data = false;
            }

            // inflect related data in entity if need be
            $inflectedProperty = $this->inflect($this->view, $property);

            if (empty($entity->$inflectedProperty)) {
                $entity->$inflectedProperty = $entity->$property;
                unset($entity->$property);
            }
            $property = $inflectedProperty;

            $hasSelfLink = false;
            try {
                $this->getRelationshipSelfLink($entity, $property);
                $hasSelfLink = true;
            } catch (MissingRouteException $e) {
            }
            $hasRelatedLink = false;
            try {
                $this->getRelationshipRelatedLink($entity, $property);
                $hasRelatedLink = true;
            } catch (MissingRouteException $e) {
            }

            //Include link elements for other relations
            if ($data === false && ($hasSelfLink || $hasRelatedLink)) {
                $relations[$property] = [
                    self::RELATIONSHIP_LINKS_SELF => $hasSelfLink,
                    self::RELATIONSHIP_LINKS_RELATED => $hasRelatedLink,
                ];

                continue;
            }

            if ($data === false && !$hasSelfLink && !$hasRelatedLink) {
                continue;
            }

            if (!$data && !is_array($foreignKey)) {
                $data = new Identifier(
                    (string)$entity->get($foreignKey),
                    $this->getTypeFromRepository($association->getTarget())
                );
            }

            $relations[$property] = [
                self::RELATIONSHIP_DATA => $data,
                self::RELATIONSHIP_LINKS_SELF => $hasSelfLink,
                self::RELATIONSHIP_LINKS_RELATED => $hasRelatedLink,
            ];
        }

        return $relations;
    }

    /**
     * NeoMerx override used to generate `self` links
     *
     * @param  \Cake\ORM\Entity|null $entity Entity, null only to be compatible with the Neomerx method
     * @return string
     */
    public function getSelfSubUrl($entity = null): string
    {
        if ($entity === null) {
            return '';
        }

        $keys = array_values($entity->extract((array)$this->getRepository()->getPrimaryKey()));

        return Router::url(
            $this->_getRepositoryRoutingParameters($this->repository) + $keys + [
            '_method' => 'GET',
            'action' => 'view',
            ],
            $this->view->getConfig('absoluteLinks', false)
        );
    }

    /**
     * @param string $name Relationship name in lowercase singular or plural
     * @return \Cake\ORM\Association|null
     */
    protected function getAssociationByProperty(string $name): ?Association
    {
        //If the property name has been inflected to something else, we need to undo that inflection to get the association
        if ($this->view->getConfig('inflect') !== 'underscore') {
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
     * @param string                           $name   Relationship name in lowercase singular or plural
     * @return \Neomerx\JsonApi\Contracts\Schema\LinkInterface
     */
    public function getRelationshipSelfLink($entity, string $name): LinkInterface
    {
        $association = $this->getAssociationByProperty($name);
        if (!$association) {
            throw new InvalidArgumentException('Invalid association ' . $name);
        }

        $from = $this->getRepository()
            ->getRegistryAlias();
        $type = $association->getName();
        [, $controllerName] = pluginSplit($from);
        $sourceName = Inflector::underscore(Inflector::singularize($controllerName));

        $url = Router::url(
            $this->_getRepositoryRoutingParameters($this->getRepository()) + [
                '_method' => 'GET',
                'action' => 'relationships',
                $sourceName . '_id' => $entity->id,
                'from' => $from,
                'type' => $type,
            ],
            $this->view->getConfig('absoluteLinks', false)
        );

        return $this->getFactory()->createLink(false, $url, false);
    }

    /**
     * NeoMerx override to generate hasMany and belongsToMany links
     * inside `relationships` node.
     *
     * hasMany example"   /countries/1/currencies"
     *
     * @param \Cake\Datasource\EntityInterface $entity Entity
     * @param string                           $name   Relationship name in lowercase singular or plural
     * @return \Neomerx\JsonApi\Contracts\Schema\LinkInterface
     */
    public function getRelationshipRelatedLink($entity, string $name): LinkInterface
    {
        $association = $this->getAssociationByProperty($name);
        if (!$association) {
            throw new InvalidArgumentException('Invalid association ' . $name);
        }

        $relatedRepository = $association->getTarget();

        ['controller' => $controllerName] = $this->_getRepositoryRoutingParameters($this->getRepository());
        $sourceName = Inflector::underscore(Inflector::singularize($controllerName));

        $isOne = \in_array(
            $association->type(),
            [Association::MANY_TO_ONE, Association::ONE_TO_ONE],
            true
        );

        $baseRoute = $this->_getRepositoryRoutingParameters($relatedRepository) + [
            '_method' => 'GET',
            'action' => $isOne ? 'view' : 'index',
        ];

        $from = $this->getRepository()
            ->getRegistryAlias();
        $type = $association->getName();
        $route = $baseRoute + [
            $sourceName . '_id' => $entity->id,
            'from' => $from,
            'type' => $type,
            '_name' => "CrudJsonApi.{$from}:{$type}",
        ];

        try {
            $url = Router::url(
                $route,
                $this->view->getConfig('absoluteLinks', false)
            );
        } catch (MissingRouteException $e) {
            //This means that the JSON:API recommended route is missing. We need to try something else.

            $relatedEntity = $entity->get($name);

            if ($relatedEntity instanceof EntityInterface) {
                $keys = array_values($relatedEntity->extract((array)$relatedRepository->getPrimaryKey()));
            } else {
                $keys = array_values($entity->extract((array)$association->getForeignKey()));
            }
            $keys = Hash::filter($keys);
            if (empty($keys)) {
                throw $e;
            }

            if (!$isOne) {
                $keys = ['?' => [
                    'id' => $keys,
                ]];
            }

            $url = Router::url(
                $baseRoute + $keys,
                $this->view->getConfig('absoluteLinks', false)
            );
        }

        return $this->getFactory()
            ->createLink(false, $url, false);
    }

    /**
     * Parses the name of an Entity class to build a lowercase plural
     * controller name to be used in links.
     *
     * @param  \Cake\Datasource\RepositoryInterface $repository Repository
     * @return array Array holding lowercase controller name as the value
     */
    protected function _getRepositoryRoutingParameters($repository)
    {
        $repositoryName = App::shortName(get_class($repository), 'Model/Table', 'Table');
        [$pluginName, $controllerName] = pluginSplit($repositoryName);

        return [
            'controller' => $controllerName,
            'plugin' => $pluginName,
        ];
    }
}
