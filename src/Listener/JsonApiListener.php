<?php
declare(strict_types=1);

namespace CrudJsonApi\Listener;

use Cake\Collection\CollectionInterface;
use Cake\Datasource\EntityInterface;
use Cake\Datasource\RepositoryInterface;
use Cake\Datasource\ResultSetDecorator;
use Cake\Datasource\ResultSetInterface;
use Cake\Event\EventInterface;
use Cake\Http\Exception\BadRequestException;
use Cake\Http\Exception\MethodNotAllowedException;
use Cake\Http\Exception\NotFoundException;
use Cake\Http\Response;
use Cake\ORM\Association;
use Cake\ORM\Locator\LocatorAwareTrait;
use Cake\ORM\Query;
use Cake\ORM\ResultSet;
use Cake\ORM\Table;
use Cake\Utility\Hash;
use Cake\Utility\Inflector;
use Crud\Error\Exception\CrudException;
use Crud\Event\Subject;
use Crud\Listener\ApiListener;
use CrudJsonApi\InflectTrait;
use CrudJsonApi\Listener\JsonApi\DocumentRelationshipValidator;
use CrudJsonApi\Listener\JsonApi\DocumentValidator;
use InvalidArgumentException;

/**
 * Extends Crud ApiListener to respond in JSON API format.
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 */
class JsonApiListener extends ApiListener
{
    use InflectTrait;
    use LocatorAwareTrait;

    public const MIME_TYPE = 'application/vnd.api+json';

    /**
     * Default configuration
     *
     * @var array
     */
    protected $_defaultConfig = [
        'detectors' => [
            'jsonapi' => ['ext' => false, 'accept' => [self::MIME_TYPE]],
        ],
        'exception' => [
            'type' => 'default',
            'class' => 'Cake\Http\Exception\BadRequestException',
            'message' => 'Unknown error',
            'code' => 0,
        ],
        'exceptionRenderer' => 'CrudJsonApi\Error\JsonApiExceptionRenderer',
        'setFlash' => false,
        'withJsonApiVersion' => false, // true or array/hash with additional meta information (will add top-level member `jsonapi` to the response)
        'meta' => [], // array or hash with meta information (will add top-level node `meta` to the response)
        'links' => [], // array or hash with link information (will add top-level node `links` to the response)
        'absoluteLinks' => false, // false to generate relative links, true will generate fully qualified URL prefixed with http://domain.name
        'jsonOptions' => [], // array with predefined JSON constants as described at http://php.net/manual/en/json.constants.php
        'debugPrettyPrint' => true, // true to use JSON_PRETTY_PRINT for generated debug-mode response
        'include' => [],
        'fieldSets' => [], // hash to limit fields shown (can be used for both `data` and `included` members)
        'docValidatorAboutLinks' => false, // true to show links to JSON API specification clarifying the document validation error
        'queryParameters' => [
            'include' => [
                'allowList' => true,
                'denyList' => false,
            ],
        ], //Array of query parameters and associated transformers
        'inflect' => 'variable', //Default to camelBacked as per https://jsonapi.org/recommendations/#naming
    ];

    /**
     * True if the Controller has set a contain statement.
     *
     * @var bool
     */
    protected $_ControllerHasSetContain;

    /**
     * Returns a list of all events that will fire in the controller during its lifecycle.
     * You can override this function to add you own listener callbacks
     *
     * We attach at priority 10 so normal bound events can run before us
     *
     * @return array
     */
    public function implementedEvents(): array
    {
        $this->setupDetectors();

        // make sure the listener does absolutely nothing unless
        // the application/vnd.api+json Accept header is used.
        if (!$this->_checkRequestType('jsonapi')) {
            return [];
        }

        return [
            'Crud.beforeHandle' => ['callable' => [$this, 'beforeHandle'], 'priority' => 10],
            'Crud.setFlash' => ['callable' => [$this, 'setFlash'], 'priority' => 5],
            'Crud.beforeSave' => ['callable' => [$this, 'beforeSave'], 'priority' => 20],
            'Crud.afterSave' => ['callable' => [$this, 'afterSave'], 'priority' => 90],
            'Crud.afterDelete' => ['callable' => [$this, 'afterDelete'], 'priority' => 90],
            'Crud.beforeRender' => ['callable' => [$this, 'respond'], 'priority' => 100],
            'Crud.beforeRedirect' => ['callable' => [$this, 'beforeRedirect'], 'priority' => 100],
            'Crud.beforePaginate' => ['callable' => [$this, 'beforeFind'], 'priority' => 10],
            'Crud.beforeFind' => ['callable' => [$this, 'beforeFind'], 'priority' => 10],
            'Crud.afterFind' => ['callable' => [$this, 'afterFind'], 'priority' => 50],
            'Crud.afterPaginate' => ['callable' => [$this, 'afterFind'], 'priority' => 50],
        ];
    }

    /**
     * beforeHandle
     *
     * Called before the crud action is executed.
     *
     * @param  \Cake\Event\EventInterface $event Event
     * @return void
     */
    public function beforeHandle(EventInterface $event): void
    {
        $this->_checkRequestMethods();
        $this->_validateConfigOptions();
        $this->_checkRequestData();
    }

    /**
     * afterFind() event used to make sure belongsTo relationships are shown when requesting
     * a single primary resource. Does NOT execute when either a Controller has set `contain` or the
     * `?include=` query parameter was passed because that would override/break previously generated data.
     *
     * @param  \Cake\Event\EventInterface $event Event
     * @return null
     */
    public function afterFind(EventInterface $event)
    {
        if (!$this->_request()->is('get')) {
            return null;
        }

        // set property so we can check inside `_renderWithResources()`
        if (!empty($event->getSubject()->query->getContain())) {
            $this->_ControllerHasSetContain = true;

            return null;
        }

        if ($this->getConfig('include')) {
            return null;
        }

        return null;
    }

    /**
     * beforeSave() event used to prevent users from sending `hasMany` relationships when POSTing and
     * to prevent them from sending `hasMany` relationships not belonging to this primary resource
     * when PATCHing.
     *
     * @param  \Cake\Event\EventInterface $event Event
     * @return void
     * @throws \Cake\Http\Exception\BadRequestException
     */
    public function beforeSave(EventInterface $event): void
    {
        if ($this->_checkIsRelationshipsRequest()) {
            return;
        }

        // generate a flat list of hasMany relationships for the current model
        $entity = $event->getSubject()->entity;
        $hasManyAssociations = $this->_getAssociationsList(null, [Association::ONE_TO_MANY]);

        if (empty($hasManyAssociations)) {
            return;
        }

        // must be PATCH so verify hasMany relationships before saving
        foreach ($hasManyAssociations as $associationName) {
            $key = Inflector::tableize($associationName);

            // do nothing if association is not hasMany
            if (!isset($entity->$key)) {
                continue;
            }

            // prevent clients attempting to side-post/create related hasMany records for non-relationship requests
            if ($this->_request()->getMethod() === 'POST') {
                throw new BadRequestException(
                    'JSON API 1.1 does not support sideposting ' .
                    '(hasMany relationships detected in the request body)'
                );
            }

            // hasMany found in the entity, extract ids from the request data
            $primaryResourceId = $this->_controller()->getRequest()->getData('id');

            /**
 * @var array $hasManyIds
*/
            $hasManyIds = Hash::extract($this->_controller()->getRequest()->getData($key), '{n}.id');
            $hasManyTable = $this->getTableLocator()->get($associationName);

            // query database only for hasMany that match both passed id and the id of the primary resource
            /**
 * @var string $entityForeignKey
*/
            $entityForeignKey = $hasManyTable->getAssociation($entity->getSource())->getForeignKey();
            $primaryKey = current((array)$hasManyTable->getPrimaryKey());
            $query = $hasManyTable->find()
                ->select([$primaryKey])
                ->where(
                    [
                        $entityForeignKey => $primaryResourceId,
                        $primaryKey . ' IN' => $hasManyIds,
                    ]
                );

            // throw an exception if number of database records does not exactly matches passed ids
            if (count($hasManyIds) !== $query->count()) {
                throw new BadRequestException(
                    "One or more of the provided relationship ids for
                    $associationName do not exist in the database"
                );
            }

            // all good, replace entity data with fetched entities before saving
            $entity->$key = $query->toArray();

            // lastly, set the `saveStrategy` for this hasMany to `replace` so non-matching existing records will be removed
            $repository = $event->getSubject()->query->getRepository();
            $repository->getAssociation($associationName)->setSaveStrategy('replace');
        }
    }

    /**
     * afterSave() event.
     *
     * @param  \Cake\Event\EventInterface $event Event
     * @return bool|null
     */
    public function afterSave(EventInterface $event): ?bool
    {
        if (!$event->getSubject()->success) {
            return false;
        }

        // `created` will be set for add actions, `id` for edit actions
        if (!$event->getSubject()->created && !$event->getSubject()->id) {
            return false;
        }

        // The `add`action (new Resource) MUST respond with HTTP Status Code 201,
        // see http://jsonapi.org/format/#crud-creating-responses-201
        if ($event->getSubject()->created) {
            $this->_controller()->setResponse($this->_controller()->getResponse()->withStatus(201));
        }

        /**
 * @var \Crud\Event\Subject $subject
*/
        $subject = $event->getSubject();
        $this->render($subject);

        return null;
    }

    /**
     * afterDelete() event used to respond with 402 code and empty body.
     *
     * Please note that the JSON API spec allows for a 200 response with
     * only meta node after a successful delete as well but this has not
     * been implemented here yet. http://jsonapi.org/format/#crud-deleting
     *
     * @param  \Cake\Event\EventInterface $event Event
     * @return bool|null
     */
    public function afterDelete(EventInterface $event)
    {
        if (!$event->getSubject()->success) {
            return false;
        }

        $this->_controller()->setResponse($this->_controller()->getResponse()->withStatus(204));

        return null;
    }

    /**
     * beforeRedirect() event used to stop the event and thus redirection.
     *
     * @param  \Cake\Event\EventInterface $event Event
     * @return void
     */
    public function beforeRedirect(EventInterface $event)
    {
        $event->stopPropagation();
    }

    /**
     * Used to test for relationships URLs
     * In aid of supporting:
     * https://jsonapi.org/format/#crud-updating-to-many-relationships
     * https://jsonapi.org/format/#crud-updating-to-one-relationships
     *
     * @return bool
     */
    protected function _checkIsRelationshipsRequest(): bool
    {
        return $this->_request()->getParam('action') === 'relationships';
    }

    /**
     * @param  \Cake\ORM\Table $repository Repository
     * @param  string          $include    The association include path
     * @return \Cake\ORM\Association|null
     */
    protected function _getAssociation(Table $repository, $include): ?Association
    {
        //We refer to associations by their property names, so we try that first
        $propertyName = Inflector::underscore($include);
        $association = $repository->associations()->getByProperty($propertyName);

        if ($association) {
            return $association;
        }

        $delimiter = '-';
        if (strpos($include, '_') !== false) {
            $delimiter = '_';
        }
        $associationName = Inflector::camelize($include, $delimiter);

        if ($repository->hasAssociation($associationName)) {//First check base name
            return $repository->getAssociation($associationName);
        }

        //If base name doesn't work, try to pluralize it
        $associationName = Inflector::pluralize($associationName);

        if ($repository->hasAssociation($associationName)) {
            return $repository->getAssociation($associationName);
        }

        return null;
    }

    /**
     * Takes a "include" string and converts it into a correct CakePHP ORM association alias
     *
     * @param  array                $includes   The relationships to include
     * @param  array|bool           $denyList  Denied includes
     * @param  array|bool           $allowList  Allowed includes
     * @param  \Cake\ORM\Table|null $repository The repository
     * @param  array                $path       Include path
     * @return array
     * @throws \Cake\Http\Exception\BadRequestException
     */
    protected function _parseIncludes($includes, $denyList, $allowList, ?Table $repository = null, $path = []): array
    {
        $wildcard = implode('.', array_merge($path, ['*']));
        $wildcardAllowList = Hash::get((array)$allowList, $wildcard);
        $wildcardDenyList = Hash::get((array)$denyList, $wildcard);
        $contains = [];

        foreach ($includes as $include => $nestedIncludes) {
            $nestedContains = [];
            $includePath = array_merge($path, [$include]);
            $includeDotPath = implode('.', $includePath);

            if (
                $denyList === true || ($denyList !== false &&
                ($wildcardDenyList === true || Hash::get($denyList, $includeDotPath) === true))
            ) {
                continue;
            }

            if (
                $allowList === false || ($allowList !== true
                && !$wildcardAllowList
                && Hash::get($allowList, $includeDotPath) === null)
            ) {
                continue;
            }

            $association = null;

            if ($repository !== null) {
                $association = $this->_getAssociation($repository, $include);
                if ($association === null) {
                    throw new BadRequestException(
                        "Invalid relationship path '{$includeDotPath}' supplied in include parameter"
                    );
                }
            }

            if (!empty($nestedIncludes)) {
                $nestedContains = $this->_parseIncludes(
                    $nestedIncludes,
                    $denyList,
                    $allowList,
                    $association ? $association->getTarget() : null,
                    $includePath
                );
            }

            if (!empty($nestedContains)) {
                if ($association !== null) {
                    $contains[$association->getAlias()] = $nestedContains;
                }
            } elseif ($association !== null) {
                $contains[] = $association->getAlias();
            }
        }

        return $contains;
    }

    /**
     * Parses out include query parameter into a containable array, and contains the query.
     *
     * Supported options is "allowList" and "Blacklist"
     *
     * @param  string|array        $includes The query data
     * @param  \Crud\Event\Subject $subject  The subject
     * @param  array               $options  Array of options for includes.
     * @return void
     */
    protected function _includeParameter($includes, Subject $subject, $options): void
    {
        if (is_string($includes)) {
            $includes = explode(',', $includes);
        }
        $includes = Hash::filter((array)$includes);

        if (empty($includes)) {
            return;
        }

        if ($options['denyList'] === true || $options['allowList'] === false) {
            throw new BadRequestException('The include parameter is not supported');
        }

        $this->setConfig('include', []);

        $includes = Hash::expand(Hash::normalize($includes));
        $denyList = is_array($options['denyList'])
            ? Hash::expand(Hash::normalize(array_fill_keys($options['denyList'], true)))
            : $options['denyList'];
        $allowList = is_array($options['allowList'])
            ? Hash::expand(Hash::normalize(array_fill_keys($options['allowList'], true)))
            : $options['allowList'];
        $contains = $this->_parseIncludes($includes, $denyList, $allowList, $subject->query->getRepository());

        $subject->query->contain($contains);

        $this->setConfig('include', []);
        $associations = $this->_getContainedAssociations($subject->query->getRepository(), $contains);
        $include = $this->_getIncludeList($associations);

        $this->setConfig('include', $include);
    }

    /**
     * Parses out fields query parameter and apply it to the query
     *
     * @param  string|array|null   $fieldSets The query data
     * @param  \Crud\Event\Subject $subject   The subject
     * @param  array               $options   Array of options for includes.
     * @return void
     */
    protected function _fieldSetsParameter($fieldSets, Subject $subject, $options): void
    {
        // could be null for e.g. using integration tests
        if ($fieldSets === null) {
            return;
        }

        // format $fieldSets to array acceptable by listener config()

        $fieldSets = array_map(
            static function ($val) {
                return explode(',', $val);
            },
            (array)$fieldSets
        );

        $repository = $subject->query->getRepository();

        $nodeName = $this->inflect($this, $repository->getAlias());
        $selectFields = [];
        if (!empty($fieldSets[$nodeName])) {
            $selectFields[] = [$repository->aliasField($repository->getPrimaryKey())];
        }
        $columns = $repository->getSchema()->columns();
        $contains = [];
        foreach ($fieldSets as $include => $fields) {
            $fields = array_map(function ($field) {
                if ($this->getConfig('inflect') === 'underscore') {
                    return $field;
                }

                return Inflector::underscore($field);
            }, $fields);
            if ($include === $nodeName) {
                $aliasFields = array_map(
                    static function ($val) use ($repository, $columns) {
                        if (!in_array($val, $columns, true)) {
                            return null;
                        }

                        return $repository->aliasField($val);
                    },
                    $fields
                );
                $selectFields[] = array_filter($aliasFields);
            }

            $association = $this->_getAssociation($repository, $include);
            if ($association) {
                $contains[$association->getAlias()] = [
                    'fields' => $fields,
                ];
            }
        }
        $selectFields = array_merge([], ...$selectFields);

        $subject->query->select($selectFields);
        if (!empty($contains)) {
            $subject->query->contain($contains);
        }

        $this->setConfig('fieldSets', $fieldSets);
    }

    /**
     * BeforeFind event listener to parse any supplied query parameters
     *
     * @param  \Cake\Event\EventInterface $event Event
     * @return void
     */
    public function beforeFind(EventInterface $event): void
    {
        //Inject default query handlers
        $queryParameters = Hash::merge(
            $this->getConfig('queryParameters'),
            [
            'sort' => [
                'callable' => [$this, '_sortParameter'],
            ],
            'include' => [
                'callable' => [$this, '_includeParameter'],
            ],
            'fields' => [
                'callable' => [$this, '_fieldSetsParameter'],
            ],
            ]
        );

        /** @var \Crud\Event\Subject $subject */
        $subject = $event->getSubject();

        foreach ($queryParameters as $parameter => $options) {
            if (is_callable($options)) {
                $options = [
                    'callable' => $options,
                ];
            }

            if (!is_callable($options['callable'])) {
                throw new InvalidArgumentException('Invalid callable supplied for query parameter ' . $parameter);
            }

            $options['callable']($this->_request()->getQuery($parameter), $subject, $options);
        }

        $this->_fetchRelated($subject);
    }

    /**
     * @param \Cake\ORM\Association $forwardAssociation The forward association
     * @param \Cake\ORM\Association $reverseAssociation The reverse association
     * @return bool
     */
    protected function checkValidReverseAssociation(
        Association $forwardAssociation,
        Association $reverseAssociation
    ): bool {
        $reverseAssociationTarget = $reverseAssociation->getTarget();
        $forwardAssociationSource = $forwardAssociation->getSource();

        if (get_class($forwardAssociationSource) !== get_class($reverseAssociationTarget)) {
            return false;
        }

        $forwardForeignKey = $forwardAssociation->getForeignKey();
        $reverseForeignKey = $reverseAssociation instanceof Association\BelongsToMany ?
            $reverseAssociation->getTargetForeignKey() :
            $reverseAssociation->getForeignKey();

        if ($forwardForeignKey !== $reverseForeignKey) {
            return false;
        }

        /**
         * Strip table aliases from the association conditions to prevent cases where a different alias causes
         * associations
         */
        $stripAliasedConditions = function ($conditions) {
            if (!is_array($conditions)) {
                return $conditions;
            }

            $strippedConditions = [];
            foreach ($conditions as $field => $condition) {
                $field = preg_replace('/.*\.(.*)/', '$1', $field);
                $strippedConditions[$field] = $condition;
            }

            return $strippedConditions;
        };

        $reverseConditions = $stripAliasedConditions($reverseAssociation->getConditions());
        $forwardConditions = $stripAliasedConditions($forwardAssociation->getConditions());

        if ($forwardConditions !== $reverseConditions) {
            return false;
        }

        if (
            !$reverseAssociation instanceof Association\BelongsToMany ||
            !$forwardAssociation instanceof Association\BelongsToMany
        ) {
            return true;
        }

        $reverseThrough = $reverseAssociation->getThrough();
        $forwardThrough = $forwardAssociation->getThrough();

        return $forwardThrough === $reverseThrough;
    }

    /**
     * @param \Crud\Event\Subject $subject Event subject
     * @return void
     */
    protected function _fetchRelated(Subject $subject): void
    {
        if ($this->_checkIsRelationshipsRequest()) {
            return;
        }

        $request = $this->_request();
        $from = $request->getParam('from');
        $relationName = $request->getParam('type');
        if (!$from || !$relationName) {
            return;
        }

        /** @var \Cake\ORM\Table $repository */
        $repository = $subject->query->getRepository();
        $fromRepository = $this->getTableLocator()->get($from);
        $association = $fromRepository->getAssociation($relationName);
        $associationType = $association->type();
        $reverseAssociationTypes = [
            Association::ONE_TO_ONE => [Association::MANY_TO_ONE], // hasOne -> belongsTo
            Association::MANY_TO_ONE => [Association::ONE_TO_ONE, Association::ONE_TO_MANY], // belongsTo -> hasOne or hasMany
            Association::ONE_TO_MANY => [Association::MANY_TO_ONE], // hasMany -> belongsTo
            Association::MANY_TO_MANY => [Association::MANY_TO_MANY],
        ];
        $reverseAssociations = $this->_getAssociationsList($repository, $reverseAssociationTypes[$associationType]);
        $reverseAssociation = null;

        //There are no valid reverse associations that we could use. Bail with a 404
        if (empty($reverseAssociations)) {
            throw new NotFoundException('No valid relationship found.');
        }

        //Search for a valid reverse association
        $reverseAssociation = null;
        foreach ($reverseAssociations as $reverseAssociationName) {
            $checkReverseAssociation = $repository->getAssociation($reverseAssociationName);

            if ($this->checkValidReverseAssociation($association, $checkReverseAssociation)) {
                $reverseAssociation = $checkReverseAssociation;
                break;
            }
        }

        if (!$reverseAssociation) {
            throw new NotFoundException('No valid relationship found.');
        }

        [, $controllerName] = pluginSplit($from);
        $sourceName = Inflector::underscore(Inflector::singularize($controllerName));
        $foreignKeyValue = $request->getParam($sourceName . '_id');

        $associationKeys = $repository->associations()->keys();
        $subject->query
            ->matching($reverseAssociation->getName(), static function (Query $query) use (
                $reverseAssociation,
                $foreignKeyValue
            ) {
                return $query
                    ->where([
                        $reverseAssociation->aliasField(
                            current((array)$reverseAssociation->getPrimaryKey())
                        ) => $foreignKeyValue,
                    ]);
            })
            ->formatResults(function (CollectionInterface $results) use ($repository, $associationKeys) {
                //Matching on a belongsToMany association adds an extra join association that we need to cleanup here.
                $afterAssociationKeys = $repository->associations()->keys();
                $extraAssociations = array_diff($afterAssociationKeys, $associationKeys);
                foreach ($extraAssociations as $extraAssociation) {
                    $repository->associations()->remove($extraAssociation);
                }

                return $results;
            });
    }

    /**
     * Add 'sort' capability
     *
     * @see    http://jsonapi.org/format/#fetching-sorting
     * @param  string|array        $sortFields Field sort request
     * @param  \Crud\Event\Subject $subject    The subject
     * @param  array               $options    Array of options for includes.
     * @return void
     */
    protected function _sortParameter($sortFields, Subject $subject, $options): void
    {
        if (is_string($sortFields)) {
            $sortFields = explode(',', $sortFields);
        }
        $sortFields = array_filter((array)$sortFields);

        $order = [];
        $includes = $this->getConfig('include');
        $repository = $subject->query->getRepository();
        foreach ($sortFields as $sortField) {
            $direction = 'ASC';
            if ($sortField[0] == '-') {
                $direction = 'DESC';
                $sortField = substr($sortField, 1);
            }

            if ($this->getConfig('inflect') !== 'underscore') {
                $sortField = Inflector::underscore($sortField); // e.g. currency, national-capitals
            }

            if (strpos($sortField, '.') !== false) {
                [$include, $field] = explode('.', $sortField);
                $include = Inflector::underscore($include);

                if ($include === Inflector::tableize($repository->getAlias())) {
                    $order[$repository->aliasField($field)] = $direction;
                    continue;
                }

                if (!in_array($include, $includes)) {
                    continue;
                }

                $associations = $repository->associations();
                foreach ($associations as $association) {
                    if ($association->getProperty() !== $include) {
                        continue;
                    }
                    $subject->query->contain(
                        [
                        $association->getAlias() => [
                            'sort' => [
                                $association->aliasField($field) => $direction,
                            ],
                            'strategy' => 'select',
                        ],
                        ]
                    );
                    $subject->query->leftJoinWith($association->getAlias());

                    $order[$association->aliasField($field)] = $direction;
                }
                continue;
            } else {
                $order[$repository->aliasField($sortField)] = $direction;
            }
        }
        $subject->query->order($order);
    }

    /**
     * Set required viewVars before rendering the JsonApiView.
     *
     * @param  \Crud\Event\Subject $subject Subject
     * @return \Cake\Http\Response
     */
    public function render(Subject $subject): Response
    {
        $controller = $this->_controller();
        $controller->viewBuilder()->setClassName('CrudJsonApi.JsonApi');

        // render a JSON API response with resource(s) if data is found
        if (isset($subject->association) && isset($subject->entity)) {
            return $this->_renderWithIdentifiers($subject);
        }

        if (isset($subject->entity) || isset($subject->entities)) {
            return $this->_renderWithResources($subject);
        }

        return $this->_renderWithoutResources();
    }

    /**
     * Renders a resource-less JSON API response.
     *
     * @return \Cake\Http\Response
     */
    protected function _renderWithoutResources(): Response
    {
        $this->_controller()->viewBuilder()->setOptions([
            'withJsonApiVersion' => $this->getConfig('withJsonApiVersion'),
            'meta' => $this->getConfig('meta'),
            'links' => $this->getConfig('links'),
            'absoluteLinks' => $this->getConfig('absoluteLinks'),
            'jsonOptions' => $this->getConfig('jsonOptions'),
            'debugPrettyPrint' => $this->getConfig('debugPrettyPrint'),
            'serialize' => true,
        ]);

        return $this->_controller()->render();
    }

    /**
     * Renders a JSON API response with top-level data node holding identifiers.
     *
     * Primarly used for relationship end-points as described in https://jsonapi.org/format/1.1/#fetching-relationships
     *
     * @param \Crud\Event\Subject $subject Subject
     * @return \Cake\Http\Response
     */
    protected function _renderWithIdentifiers(Subject $subject): Response
    {
        /** @var \Cake\ORM\Association $association */
        $association = $subject->association;

        $this->_controller()
            ->viewBuilder()
            ->setOptions(
                [
                    'withJsonApiVersion' => $this->getConfig('withJsonApiVersion'),
                    'meta' => $this->getConfig('meta'),
                    'links' => $this->getConfig('links'),
                    'absoluteLinks' => $this->getConfig('absoluteLinks'),
                    'jsonOptions' => $this->getConfig('jsonOptions'),
                    'debugPrettyPrint' => $this->getConfig('debugPrettyPrint'),
                    'serialize' => true,
                    'association' => $association,
                    'inflect' => $this->getConfig('inflect'),
                ]
            );

        return $this->_controller()
            ->render();
    }

    /**
     * Renders a JSON API response with top-level data node holding resource(s).
     *
     * @param  \Crud\Event\Subject $subject Subject
     * @return \Cake\Http\Response
     */
    protected function _renderWithResources(Subject $subject): Response
    {
        $repository = $this->_controller()->loadModel(); // Default model class

        $usedAssociations = [];
        if (isset($subject->query)) {
            $usedAssociations += $this->_getContainedAssociations($repository, $subject->query->getContain());
        }

        if (isset($subject->entities)) {
            $entity = $this->_getSingleEntity($subject);
            if ($entity) {
                $usedAssociations += $this->_extractEntityAssociations($repository, $entity);
            }
        }

        if (isset($subject->entity)) {
            $usedAssociations += $this->_extractEntityAssociations($repository, $subject->entity);
        }

        // only generate the `included` node if the option is set by query parameter or config
        // (which will not be the case when viewing a single Resource without parameters).
        if ($this->getConfig('include') || $this->_ControllerHasSetContain === true) {
            $include = $this->_getIncludeList($usedAssociations);
        } else {
            $include = [];
        }

        // Set data before rendering the view
        $this->_controller()->viewBuilder()->setOptions([
            'withJsonApiVersion' => $this->getConfig('withJsonApiVersion'),
            'meta' => $this->getConfig('meta'),
            'links' => $this->getConfig('links'),
            'absoluteLinks' => $this->getConfig('absoluteLinks'),
            'jsonOptions' => $this->getConfig('jsonOptions'),
            'debugPrettyPrint' => $this->getConfig('debugPrettyPrint'),
            'fieldSets' => $this->getConfig('fieldSets'),
            'serialize' => true,
            'inflect' => $this->getConfig('inflect'),
            'include' => $include,
            'repositories' => $this->_getRepositoryList(
                $repository,
                $usedAssociations
            ),
        ]);
        $this->_controller()->set([
            Inflector::tableize($repository->getAlias()) => $this->_getFindResult($subject),
        ]);

        return $this->_controller()->render();
    }

    /**
     * Make sure all configuration options are valid.
     *
     * @throws \Crud\Error\Exception\CrudException
     * @return void
     */
    protected function _validateConfigOptions(): void
    {
        if ($this->getConfig('withJsonApiVersion')) {
            if (
                !is_bool($this->getConfig('withJsonApiVersion')) &&
                !is_array($this->getConfig('withJsonApiVersion'))
            ) {
                throw new CrudException(
                    'JsonApiListener configuration option `withJsonApiVersion` only accepts a boolean or an array'
                );
            }
        }

        if (!is_array($this->getConfig('meta'))) {
            throw new CrudException('JsonApiListener configuration option `meta` only accepts an array');
        }

        if (!is_bool($this->getConfig('absoluteLinks'))) {
            throw new CrudException(
                'JsonApiListener configuration option `absoluteLinks` only accepts a boolean'
            );
        }

        if (!is_array($this->getConfig('include'))) {
            throw new CrudException('JsonApiListener configuration option `include` only accepts an array');
        }

        if (!is_array($this->getConfig('fieldSets'))) {
            throw new CrudException('JsonApiListener configuration option `fieldSets` only accepts an array');
        }

        if (!is_array($this->getConfig('jsonOptions'))) {
            throw new CrudException('JsonApiListener configuration option `jsonOptions` only accepts an array');
        }

        if (!is_bool($this->getConfig('debugPrettyPrint'))) {
            throw new CrudException('JsonApiListener configuration option `debugPrettyPrint` only accepts a boolean');
        }

        if (!is_array($this->getConfig('queryParameters'))) {
            throw new CrudException('JsonApiListener configuration option `queryParameters` only accepts an array');
        }
    }

    /**
     * Override ApiListener method to enforce required JSON API request methods.
     *
     * @throws \Cake\Http\Exception\BadRequestException
     * @return void
     */
    protected function _checkRequestMethods(): void
    {
        if ($this->_request()->is('put')) {
            throw new MethodNotAllowedException('JSON API does not support the PUT method, use PATCH instead');
        }

        if (!$this->_request()->contentType()) {
            return;
        }

        if ($this->_request()->contentType() !== self::MIME_TYPE) {
            throw new BadRequestException(
                'JSON API requests with data require the "' . self::MIME_TYPE . '" Content-Type header'
            );
        }
    }

    /**
     * Deduplicate resultset from rows that might have come from joins
     *
     * @param  \Crud\Event\Subject $subject Subject
     * @return \Cake\Datasource\ResultSetInterface
     */
    protected function _deduplicateResultSet($subject): ResultSetInterface
    {
        $ids = [];
        $entities = [];
        $keys = (array)$subject->query->getRepository()->getPrimaryKey();
        foreach ($subject->entities as $entity) {
            $id = $entity->extract($keys);
            if (!in_array($id, $ids)) {
                $entities[] = $entity;
                $ids[] = $id;
            }
        }

        if ($subject->entities instanceof ResultSet) {
            $resultSet = clone $subject->entities;
            $resultSet->unserialize(serialize($entities));
        } else {
            $resultSet = new ResultSetDecorator($entities);
        }

        return $resultSet;
    }

    /**
     * Helper function to easily retrieve `find()` result from Crud subject
     * regardless of current action.
     *
     * @param  \Crud\Event\Subject $subject Subject
     * @return \Cake\Datasource\EntityInterface|\Cake\Datasource\EntityInterface[]|\Cake\ORM\ResultSet Single Entity or ORM\ResultSet
     */
    protected function _getFindResult(Subject $subject)
    {
        if (!empty($subject->entities)) {
            if (isset($subject->query)) {
                $subject->entities = $this->_deduplicateResultSet($subject);
            }

            return $subject->entities;
        }

        return $subject->entity;
    }

    /**
     * Helper function to easily retrieve a single entity from Crud subject
     * find result regardless of current action.
     *
     * @param  \Crud\Event\Subject $subject Subject
     * @return \Cake\Datasource\EntityInterface|null
     */
    protected function _getSingleEntity(Subject $subject): ?EntityInterface
    {
        if (!empty($subject->entities) && $subject->entities instanceof Query) {
            // @phpstan-ignore-next-line
            return (clone $subject->entities)->first();
        }

        if (!empty($subject->entities) && $subject->entities instanceof ResultSetInterface) {
            if ($subject->entities->first() === null) {
                $repository = $subject->query->getRepository();
                $entity = $repository->getEntityClass();

                return new $entity();
            }

            return $subject->entities->first();
        }

        return $subject->entity;
    }

    /**
     * Creates a nested array of all associations used in the query
     *
     * @param  \Cake\Datasource\RepositoryInterface $repository Repository
     * @param  array           $contains   Array of contained associations
     * @return array Array with \Cake\ORM\AssociationCollection
     */
    protected function _getContainedAssociations(RepositoryInterface $repository, array $contains): array
    {
        if (!$repository instanceof Table) {
            return [];
        }

        $associationCollection = $repository->associations();
        $associations = [];

        foreach ($contains as $contain => $nestedContains) {
            if (is_string($nestedContains)) {
                $contain = $nestedContains;
                $nestedContains = [];
            }

            $association = $associationCollection->get($contain);
            if ($association === null) {
                continue;
            }

            $associationKey = strtolower($association->getName());

            $associations[$associationKey] = [
                'association' => $association,
                'children' => [],
            ];

            if (!empty($nestedContains)) {
                $associations[$associationKey]['children'] = $this->_getContainedAssociations(
                    $association->getTarget(),
                    $nestedContains
                );
            }
        }

        return $associations;
    }

    /**
     * Removes all associated models not detected (as the result of a contain
     * query) in the find result from the entity's AssociationCollection to
     * prevent `null` entries appearing in the json api `relationships` node.
     *
     * @param  \Cake\Datasource\RepositoryInterface                  $repository Repository
     * @param  \Cake\Datasource\EntityInterface $entity     Entity
     * @return array
     */
    protected function _extractEntityAssociations(RepositoryInterface $repository, EntityInterface $entity): array
    {
        if (!$repository instanceof Table) {
            return [];
        }

        $associationCollection = $repository->associations();
        $associations = [];
        foreach ($associationCollection as $association) {
            $associationKey = strtolower($association->getName());
            $entityKey = $association->getProperty();
            if (!empty($entity->$entityKey)) {
                $associationEntity = $entity->$entityKey;
                $associationEntity = is_array($associationEntity) ? current($associationEntity) : $associationEntity;
                $associations[$associationKey] = [
                    'association' => $association,
                    'children' => $this->_extractEntityAssociations($association->getTarget(), $associationEntity),
                ];
            }
        }

        return $associations;
    }

    /**
     * Get a flat list of all repositories indexed by their registry alias.
     *
     * @param \Cake\Datasource\RepositoryInterface $repository Current repository
     * @param    array               $associations Nested associations to get repository from
     * @return   array Used repositories indexed by registry alias
     * @internal
     */
    protected function _getRepositoryList(RepositoryInterface $repository, array $associations): array
    {
        $repositories = [
            $repository->getRegistryAlias() => $repository,
        ];

        foreach ($associations as $association) {
            $association += [
                'association' => null,
                'children' => [],
            ];

            if ($association['association'] === null) {
                throw new InvalidArgumentException('Association does not have an association object set');
            }

            $associationRepository = $association['association']->getTarget();

            $repositories += $this->_getRepositoryList($associationRepository, $association['children'] ?: []);
        }

        return $repositories;
    }

    /**
     * Generates a list for with all associated data (as produced by Containable
     * and thus) present in the entity to be used for filling the top-level
     * `included` node in the json response UNLESS user has specified listener
     * config option 'include'.
     *
     * @param  array $associations Array with \Cake\ORM\AssociationCollection(s)
     * @param  bool  $last         Is this the "top-level"/entry point for the recursive function
     * @return array
     * @throws \InvalidArgumentException
     */
    protected function _getIncludeList(array $associations, bool $last = true): array
    {
        if (!empty($this->getConfig('include'))) {
            return $this->getConfig('include');
        }

        $result = [];
        foreach ($associations as $name => $association) {
            $association += [
                'association' => null,
                'children' => [],
            ];

            if ($association['association'] === null) {
                throw new InvalidArgumentException("Association {$name} does not have an association object set");
            }

            $property = $association['association']->getProperty();
            $inflect = $this->getConfig('inflect');
            if ($inflect) {
                $property = Inflector::$inflect($property); // e.g. currency, national-capitals
            }

            $result[$property] = $this->_getIncludeList($association['children'], false);
        }

        return $last ? array_keys(Hash::flatten($result)) : $result;
    }

    /**
     * Checks if data was posted to the Listener. If so then checks if the
     * array (already converted from json) matches the expected JSON API
     * structure for resources and if so, converts that array to CakePHP
     * compatible format so it can be processed as usual from there.
     *
     * @return void
     */
    protected function _checkRequestData(): void
    {
        $requestMethod = $this->_controller()->getRequest()->getMethod();
        $isRelationshipAction = $this->_checkIsRelationshipsRequest();

        if (
            ($requestMethod !== 'POST' && $requestMethod !== 'PATCH' && $requestMethod !== 'DELETE') ||
            ($requestMethod === 'DELETE' && !$isRelationshipAction)
        ) {
            return;
        }

        $requestData = $this->_controller()->getRequest()->getData();

        if (empty($requestData)) {
            throw new BadRequestException(
                'Missing request data required for POST and PATCH methods, ' .
                'as well as DELETE methods to relationship endpoints. ' .
                'Make sure that you are sending a request body and that it is valid JSON.'
            );
        }

        $validator = new DocumentValidator($requestData, $this->getConfig());

        if ($isRelationshipAction) {
            $relationshipValidator = new DocumentRelationshipValidator($requestData, $this->getConfig());
            $relationshipValidator->validateUpdateDocument();
        } else {
            if ($requestMethod === 'POST') {
                $validator->validateCreateDocument();
            }

            if ($requestMethod === 'PATCH' || $requestMethod === 'DELETE') {
                $validator->validateUpdateDocument();
            }
        }

        // decode JSON API to CakePHP array format, then call the action as usual
        $decodedJsonApi = $this->_convertJsonApiDocumentArray($requestData);

        $exception = null;
        if ($requestMethod === 'PATCH' && !$isRelationshipAction) {
            // For normal PATCH operations the `id` field in the request data MUST match the URL id
            // because JSON API considers it immutable. https://github.com/json-api/json-api/issues/481
            $exception = $this->_request()->getParam('id') !== $decodedJsonApi['id'];
        }

        if ($exception) {
            throw new BadRequestException(
                'URL id does not match request data id as required for JSON API PATCH actions'
            );
        }

        $this->_controller()->setRequest($this->_controller()->getRequest()->withParsedBody($decodedJsonApi));
    }

    /**
     * Returns a flat array list with the names of all associations for the given
     * repository (Or the default for the controller), optionally limited to only matching associationTypes.
     *
     * @param  \Cake\ORM\Table|null $table           Table
     * @param  array                            $associationTypes Array with any combination of Cake\ORM\Association types
     * @return array
     */
    protected function _getAssociationsList(?Table $table, array $associationTypes = []): array
    {
        $table = $table ?: $this->_table();
        if (!$table instanceof Table) {
            return [];
        }

        $associations = $table->associations();

        $result = [];
        foreach ($associations as $association) {
            if (empty($associationTypes)) {
                $result[] = $association->getName();
                continue;
            }

            if (in_array($association->type(), $associationTypes, true)) {
                $result[] = $association->getName();
            }
        }

        return $result;
    }

    /**
     * Converts (already json_decoded) request data array in JSON API document
     * format to CakePHP format so it be processed as usual. Should only be
     * used with already validated data/document or things will break.
     *
     * Please note that decoding hasMany relationships has not yet been implemented.
     *
     * @param  array $document Request data document array
     * @return array
     */
    protected function _convertJsonApiDocumentArray(array $document): array
    {
        $result = [];

        // convert primary resource
        if (array_key_exists('id', $document['data'])) {
            $result['id'] = $document['data']['id'];
        }

        if (array_key_exists('attributes', $document['data'])) {
            $result = array_merge_recursive($result, $document['data']['attributes']);

            // Un-inflect all attribute keys directly below the primary resource if need be
            if ($this->getConfig('inflect') !== 'underscore') {
                foreach ($result as $key => $value) {
                    $underscoredKey = Inflector::underscore($key);

                    if (!array_key_exists($underscoredKey, $result)) {
                        $result[$underscoredKey] = $value;
                        unset($result[$key]);
                    }
                }
            }
        }

        // handle relationships URL
        if ($this->_checkIsRelationshipsRequest()) {
            $result = $document['data'];
        }

        // no further action if there are no relationships
        if (!array_key_exists('relationships', $document['data'])) {
            return $result;
        }

        // translate relationships into CakePHP array format
        foreach ($document['data']['relationships'] as $key => $details) {
            if ($this->getConfig('inflect') !== 'underscore') {
                $key = Inflector::underscore($key); // e.g. currency, national_capitals
            }

            // allow empty/null data node as per the JSON API specification
            if (empty($details['data'])) {
                continue;
            }

            // handle belongsTo relationships
            if (!isset($details['data'][0])) {
                $belongsToForeignKey = $key . '_id';
                $belongsToId = $details['data']['id'];
                $result[$belongsToForeignKey] = $belongsToId;

                continue;
            }

            // handle hasMany relationships
            if (isset($details['data'][0])) {
                $relationResults = [];
                foreach ($details['data'] as $relationData) {
                    $relationResult = [];
                    if (array_key_exists('id', $relationData)) {
                        $relationResult['id'] = $relationData['id'];
                    }

                    if (array_key_exists('attributes', $relationData)) {
                        $relationResult = array_merge_recursive($relationResult, $relationData['attributes']);

                        // dasherize attribute keys if need be
                        if ($this->getConfig('inflect') !== 'underscore') {
                            foreach ($relationResult as $resultKey => $value) {
                                $underscoredKey = Inflector::underscore($resultKey);
                                if (!array_key_exists($underscoredKey, $relationResult)) {
                                    $relationResult[$underscoredKey] = $value;
                                    unset($relationResult[$resultKey]);
                                }
                            }
                        }
                    }

                    $relationResults[] = $relationResult;
                }

                $result[$key] = $relationResults;
            }
        }

        return $result;
    }
}
