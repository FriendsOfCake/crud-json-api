<?php
declare(strict_types=1);

namespace CrudJsonApi\Action;

use Cake\Datasource\EntityInterface;
use Cake\Datasource\Exception\RecordNotFoundException;
use Cake\Datasource\ResultSetDecorator;
use Cake\Http\Exception\BadRequestException;
use Cake\Http\Exception\ForbiddenException;
use Cake\Http\Exception\NotFoundException;
use Cake\ORM\Association;
use Cake\ORM\Locator\LocatorAwareTrait;
use Cake\Utility\Hash;
use Cake\Utility\Inflector;
use Crud\Action\BaseAction;
use Crud\Error\Exception\ValidationException;
use Crud\Event\Subject;
use Crud\Traits\FindMethodTrait;
use Crud\Traits\RedirectTrait;
use Crud\Traits\SaveMethodTrait;
use Crud\Traits\SerializeTrait;
use Crud\Traits\ViewTrait;
use Crud\Traits\ViewVarTrait;

/**
 * Class RelationshipViewAction
 */
class RelationshipsAction extends BaseAction
{
    use LocatorAwareTrait;
    use FindMethodTrait;
    use SaveMethodTrait;
    use SerializeTrait;
    use ViewTrait;
    use ViewVarTrait;
    use RedirectTrait;

    /**
     * Default settings for 'view' actions
     *
     * `enabled` Is this crud action enabled or disabled
     *
     * `findMethod` The default `Model::find()` method for reading data
     *
     * `view` A map of the controller action and the view to render
     * If `NULL` (the default) the controller action name will be used
     *
     * @var array
     */
    protected $_defaultConfig = [
        'enabled' => true,
        'scope' => 'table',
        'findMethod' => 'all',
        'view' => null,
        'viewVar' => null,
        'saveMethod' => 'save',
        'saveOptions' => [],
        'messages' => [
            'success' => [
                'text' => 'Successfully updated {name}',
            ],
            'error' => [
                'text' => 'Could not update {name}',
            ],
        ],
        'redirect' => [
            'post_add' => [
                'reader' => 'request.data',
                'key' => '_add',
                'url' => ['action' => 'add'],
            ],
            'post_edit' => [
                'reader' => 'request.data',
                'key' => '_edit',
                'url' => ['action' => 'edit', ['subject.key', 'id']],
            ],
        ],
        'api' => [
            'methods' => ['put', 'post', 'patch'],
            'success' => [
                'code' => 200,
            ],
            'error' => [
                'exception' => [
                    'type' => 'validate',
                    'class' => ValidationException::class,
                ],
            ],
        ],
        'serialize' => [],
        'allowedRelationships' => true,
    ];

    /**
     * @inheritDoc
     */
    public function implementedEvents(): array
    {
        $events = parent::implementedEvents();

        $events['Crud.beforeHandle'] = ['callable' => [$this, 'checkAllowed']];

        return $events;
    }

    /**
     * @return void
     */
    public function checkAllowed(): void
    {
        $request = $this->_request();
        $method = $request->getMethod();
        $allowedRelationships = $this->getConfig('allowedRelationships');
        $methodMap = [
            'GET' => 'read',
            'PATCH' => 'replace',
            'POST' => 'add',
            'DELETE' => 'delete',
        ];

        $mappedMethod = $methodMap[$method] ?? 'read';

        if ($allowedRelationships === true || $mappedMethod === 'read') {
            return;
        }

        if ($allowedRelationships === false) {
            throw new ForbiddenException();
        }

        if (is_array($allowedRelationships)) {
            $allowedRelationships = Hash::normalize($allowedRelationships) + ['*' => false];
        }

        $relation = $this->_request()->getParam('type');
        $allowed = array_key_exists($allowedRelationships, $relation) ?
            $allowedRelationships[$relation] :
            $allowedRelationships['*'];

        if ($allowed === false || !in_array($mappedMethod, $allowed, true)) {
            throw new ForbiddenException();
        }
    }

    /**
     * @param \Crud\Event\Subject $subject Subject object
     * @return \Cake\Datasource\EntityInterface
     * @throws \Exception
     */
    protected function _findRelations(Subject $subject): EntityInterface
    {
        $relationName = $this->_request()->getParam('type');
        $table = $this->_table();
        $association = $table->getAssociation($relationName);
        $targetTable = $association->getTarget();

        [, $controllerName] = pluginSplit($table->getRegistryAlias());
        $sourceName = Inflector::underscore(Inflector::singularize($controllerName));
        $foreignKeyParam = $this->_request()->getParam($sourceName . '_id');

        if (!$foreignKeyParam) {
            throw new NotFoundException();
        }

        $primaryKey = $table->getPrimaryKey();
        $foreignPrimaryKey = $association->getTarget()->getPrimaryKey();
        $foreignKey = $association->getForeignKey();

        //Does not support composite keys
        if (is_array($primaryKey) || is_array($foreignPrimaryKey) || is_array($foreignKey)) {
            throw new BadRequestException('Composite keys are currently not supported.');
        }

        $fields = [$table->aliasField($primaryKey)];
        $associationFields = [
            $targetTable->aliasField($foreignPrimaryKey),
        ];
        $associationType = $association->type();

        if (in_array($associationType, [Association::ONE_TO_MANY, Association::ONE_TO_ONE], true)) {
            $associationFields[] = $targetTable->aliasField($foreignKey);
        } elseif ($associationType === Association::MANY_TO_ONE) {
            $fields[] = $table->aliasField($foreignKey);
        }

        $primaryQuery = $table->find()
            ->select($fields)
            ->where(
                [
                    $table->aliasField($primaryKey) => $foreignKeyParam,
                ]
            )
            ->contain([
                $relationName => [
                    'fields' => $associationFields,
                ],
            ]);
        $primaryQuery->getEagerLoader()->enableAutoFields();

        $subject->set(
            [
                'association' => $association,
                'repository' => $table,
                'query' => $primaryQuery,
            ]
        );
        $this->_trigger('beforeFind', $subject);
        $entity = $subject->query->first();

        if (!$entity) {
            $this->_notFound($foreignKeyParam, $subject);
        }

        $subject->set(['success' => true]);

        $relatedEntities = $entity->get($association->getProperty());

        if (is_array($relatedEntities)) {
            $subject->set(['entities' => new ResultSetDecorator($relatedEntities)]);

            return $entity;
        }

        $this->setConfig('scope', 'entity');
        $subject->set([
            'entity' => $relatedEntities,
        ]);

        return $entity;
    }

    /**
     * Generic HTTP handler
     *
     * @return void
     */
    protected function _get(): void
    {
        $subject = $this->_subject();

        $this->_findRelations($subject);
        $this->_trigger('beforeRender', $subject);
    }

    /**
     * HTTP DELETE handler
     *
     * @return void
     */
    protected function _delete()
    {
        $subject = $this->_subject();
        $request = $this->_request();
        $data = $request->getData();

        $entity = $this->_findRelations($subject);
        $subject->set(['entity' => $entity, 'entities' => null]);

        /** @var \Cake\ORM\Association $association */
        $association = $subject->association;
        $property = $association->getProperty();

        if (in_array($association->type(), [Association::MANY_TO_ONE, Association::ONE_TO_ONE], true)) {
            throw new ForbiddenException('DELETE requests not allowed for to-one relationships');
        }

        if (empty((array)$data)) {
            $this->_success($subject);

            return;
        }

        $foreignTable = $association->getTarget();
        $foreignPrimaryKey = $foreignTable->getPrimaryKey();

        if (is_array($foreignPrimaryKey)) {
            throw new BadRequestException('Composite keys are not supported.');
        }

        $idsToDelete = (array)Hash::extract($data, '{n}.id');
        $foreignRecords = $entity->$property;
        $entity->$property = [];
        foreach ($foreignRecords as $key => $foreignRecord) {
            if (!in_array($foreignRecord->id, $idsToDelete, false)) {
                $entity->{$property}[] = $foreignRecord;
            }
        }

        $this->_trigger('beforeSave', $subject);

        if (method_exists($association, 'setSaveStrategy')) {
            $association->setSaveStrategy('replace');
        }
        $saveMethod = $this->saveMethod();
        if ($this->_table()->$saveMethod($entity, $this->saveOptions())) {
            $this->_success($subject);

            return;
        }

        $this->_error($subject);
    }

    /**
     * HTTP POST handler
     *
     * @return void
     */
    protected function _post()
    {
        $subject = $this->_subject();
        $request = $this->_request();
        $data = $request->getData();

        $entity = $this->_findRelations($subject);
        $subject->set(['entity' => $entity, 'entities' => null]);

        /** @var \Cake\ORM\Association $association */
        $association = $subject->association;
        $property = $association->getProperty();

        if (in_array($association->type(), [Association::MANY_TO_ONE, Association::ONE_TO_ONE], true)) {
            throw new ForbiddenException('POST requests not allowed for to-one relationships');
        }

        // ensure that only adds new relationships, doesn't destroy old ones
        $entity->$property = empty($entity->$property) ? [] : $entity->$property;

        $ids = (array)Hash::extract($entity->$property, '{n}.id');
        $foreignRecords = $this->getForeignRecords($data, $association);

        foreach ($foreignRecords as $foreignRecord) {
            // push it onto the relationships array if it's not already related
            if (!in_array($foreignRecord->id, $ids, false)) {
                $entity->{$property}[] = $foreignRecord;
            }
        }

        $entity->setDirty($property);

        $this->_trigger('beforeSave', $subject);

        $saveMethod = $this->saveMethod();
        if ($this->_table()->$saveMethod($entity, $this->saveOptions())) {
            $this->_success($subject);

            return;
        }

        $this->_error($subject);
    }

    /**
     * HTTP PATCH handler
     *
     * @return void
     */
    protected function _patch()
    {
        $subject = $this->_subject();
        $request = $this->_request();
        $data = $request->getData();

        $entity = $this->_findRelations($subject);
        $subject->set(['entity' => $entity, 'entities' => null]);

        /** @var \Cake\ORM\Association $association */
        $association = $subject->association;
        $property = $association->getProperty();
        $foreignTable = $association->getTarget();

        if (in_array($association->type(), [Association::MANY_TO_ONE, Association::ONE_TO_ONE], true)) {
            //Set the relationship to the corresponding entity
            if (array_key_exists('id', $data)) {
                $entity->{$property} = $foreignTable->get($data['id']);
            } elseif ($data === null) {
                $entity->{$property} = null;
            }
        } else {
            $entity->{$property} = $this->getForeignRecords($data, $association);
        }

        $entity->setDirty($property);

        $this->_trigger('beforeSave', $subject);

        //Set saveStrategy to replace to remove relationships that should no longer exist
        if (method_exists($association, 'setSaveStrategy')) {
            $association->setSaveStrategy('replace');
        }
        $saveMethod = $this->saveMethod();
        if ($this->_table()->$saveMethod($entity, $this->saveOptions())) {
            $this->_success($subject);

            return;
        }

        $this->_error($subject);
    }

    /**
     * Success callback
     *
     * @param \Crud\Event\Subject $subject Event subject
     * @return void
     */
    protected function _success(Subject $subject): void
    {
        //A successful change should return the new representation of the resource
        $this->_get();
    }

    /**
     * Error callback
     *
     * @param \Crud\Event\Subject $subject Event subject
     * @return void
     */
    protected function _error(Subject $subject): void
    {
        $subject->set(['success' => false, 'created' => false]);
        $this->_trigger('afterSave', $subject);

        $this->setFlash('error', $subject);

        $this->_trigger('beforeRender', $subject);
    }

    /**
     * @param array $data Array of data
     * @param \Cake\ORM\Association $association The association
     * @return array
     */
    protected function getForeignRecords(array $data, Association $association): array
    {
        if (empty($data)) {
            return [];
        }

        $idsToAdd = (array)Hash::extract($data, '{n}.id');
        $associationPrimaryKey = $association->getPrimaryKey();

        if (is_array($associationPrimaryKey)) {
            throw new BadRequestException('Composite keys are currently not supported.');
        }

        //Get the foreign records
        $foreignRecords = $association->find()
            ->where(
                [
                    $association->aliasField($associationPrimaryKey) . ' in' => $idsToAdd,
                ]
            )
            ->all();

        if (count($idsToAdd) !== count($foreignRecords)) {
            $foundIds = $foreignRecords->extract(
                static function ($record) {
                    return $record->id;
                }
            )
                ->toArray();
            $missingIds = array_diff($idsToAdd, $foundIds);

            throw new RecordNotFoundException(
                __('Not all requested records could be found. Missing IDs are {0}', implode(', ', $missingIds))
            );
        }

        return $foreignRecords->toArray();
    }
}
