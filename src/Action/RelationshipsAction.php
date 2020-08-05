<?php
declare(strict_types=1);

namespace CrudJsonApi\Action;

use Cake\Datasource\ResultSetDecorator;
use Cake\Event\EventInterface;
use Cake\Http\Exception\ForbiddenException;
use Cake\Http\Exception\NotFoundException;
use Cake\Http\Response;
use Cake\ORM\Association;
use Cake\ORM\Locator\LocatorAwareTrait;
use Cake\Utility\Hash;
use Cake\Utility\Inflector;
use Crud\Action\BaseAction;
use Crud\Error\Exception\ValidationException;
use Crud\Event\Subject;
use Crud\Traits\FindMethodTrait;
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

        $events['Crud.beforeHandle'] = ['callable' => [$this, 'isAllowed']];

        return $events;
    }

    /**
     * @return void
     */
    public function isAllowed(): void
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
        $allowed = array_key_exists($allowedRelationships, $relation) ? $allowedRelationships[$relation] : $allowedRelationships['*'];

        if ($allowed === false || !in_array($mappedMethod, $allowed, true)) {
            throw new ForbiddenException();
        }
    }

    /**
     * @param \Crud\Event\Subject $subject Subject object
     * @return void
     * @throws \Exception
     */
    protected function _findRelations(Subject $subject)
    {
        $relationName = $this->_request()->getParam('type');
        $table = $this->_table();
        $association = $table->getAssociation($relationName);

        [, $controllerName] = pluginSplit($table->getRegistryAlias());
        $sourceName = Inflector::underscore(Inflector::singularize($controllerName));
        $foreignKeyParam = $this->_request()->getParam($sourceName . '_id');

        if (!$foreignKeyParam) {
            throw new NotFoundException();
        }

        $primaryKey = $table->getPrimaryKey();
        $primaryQuery = $table->find()
            ->select([
                $primaryKey,
            ])
            ->where(
                [
                    $primaryKey => $foreignKeyParam,
                ]
            )
            ->contain([
                $relationName => [
                    'fields' => [$association->getTarget()->getPrimaryKey()],
                ],
            ]);

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

            return;
        }

        $subject->set([
            'entity' => $relatedEntities,
        ]);
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
     *
     * @return \Cake\Http\Response|null
     */
    protected function _delete()
    {
        $subject = $this->_subject();
        $request = $this->_request();
        $id = $request->getParam('id');
        $relationName = $request->getParam('type');
        $data = $request->getData();
        $requestMethod = $request->getMethod();
        $subject->set(['id' => $id]);

        $this->_findRelations($subject);
        dd($subject);

        $this->_crud('Crud.Crud')
            ->on(
                'beforeFind',
                function (EventInterface $event) use ($data, $foreignTableName, $requestMethod) {
                    $query = $event->getSubject()->query;
                    $query->contain(['Dishes']);
                }
            );
        $entity = $this->_table()
            ->patchEntity(
                $this->_findRecord($id, $subject),
                [],
                $this->saveOptions()
            );
        if (empty((array)$data)) {
            $entity->$foreignTableName = [];
        } else {
            $currentIds = Hash::extract($entity->$foreignTableName, '{n}.id');
            $idsToDelete = Hash::extract($data, '{n}.id');
            $entity->$foreignTableName = [];
            $foreignTable = $this->getTableLocator()
                ->get(Inflector::camelize($foreignTableName));
            $foreignKey = $foreignTableName . '.id';
            foreach ($currentIds as $key => $id) {
                if (!in_array($id, $idsToDelete)) {
                    // get the record to add
                    $query = $foreignTable->findAllById($id);
                    $foreignRecord = $query->first();
                    if (!empty($foreignRecord)) {
                        array_push($entity->$foreignTableName, $foreignRecord);
                    }
                }
            }
        }

        $this->_trigger('beforeSave', $subject);

        if (call_user_func([$this->_table(), $this->saveMethod()], $entity, $this->saveOptions())) {
            return $this->_success($subject);
        }

        $this->_error($subject);
    }

    /**
     * HTTP POST handler
     *
     * @return \Cake\Http\Response|null
     */
    protected function _post()
    {
        $request = $this->_request();
        $subject = $this->_subject();
        $id = $request->getParam('id');
        $foreignTableName = $request->getParam('type');
        $data = $request->getData();
        $requestMethod = $request->getMethod();

        $this->_crud('Crud.Crud')
            ->on(
                'beforeFind',
                function (\Cake\Event\Event $event) use ($data, $foreignTableName, $requestMethod) {
                    $query = $event->getSubject()->query;
                    $query->contain(['Dishes']);
                }
            );

        $entity = $this->_table()
            ->patchEntity(
                $this->_findRecord($id, $subject),
                [],
                $this->saveOptions()
            );

        // ensure that only adds new relationships, doesn't destroy old ones
        $entity->$foreignTableName = empty($entity->$foreignTableName) ? [] : $entity->$foreignTableName;

        $ids = Hash::extract($entity->$foreignTableName, '{n}.id');
        $foreignTable = $this->getTableLocator()
            ->get(Inflector::camelize($foreignTableName));
        $foreignKey = $foreignTableName . '.id';
        foreach ($data as $key => $recordAttributes) {
            // get the related record
            $query = $foreignTable->findAllById($recordAttributes['id']);
            $foreignRecord = $query->first();
            // push it onto the relationsships array
            if (!empty($foreignRecord)) {
                if (in_array($foreignRecord->id, $ids)) {
                    // don't add id's that are already added
                } else {
                    array_push($entity->$foreignTableName, $foreignRecord);
                }
            }
        }

        $this->_trigger('beforeSave', $subject);

        if (call_user_func([$this->_table(), $this->saveMethod()], $entity, $this->saveOptions())) {
            return $this->_success($subject);
        }

        $this->_error($subject);
    }

    /**
     * HTTP PATCH handler
     *
     * @return \Cake\Http\Response|null
     */
    protected function _patch()
    {
        $subject = $this->_subject();
        $request = $this->_request();
        $id = $request->getParam('id');
        $foreignTableName = $request->getParam('foreignTableName');
        $data = $request->getData();

        $subject->set(['id' => $id]);
        $entity = $this->_table()
            ->patchEntity(
                $this->_findRecord($id, $subject),
                [],
                $this->saveOptions()
            );
        if (empty((array)$data)) {
            $entity->$foreignTableName = [];
        } else {
            $entity->$foreignTableName = [];

            $foreignTable = $this->getTableLocator()
                ->get(Inflector::camelize($foreignTableName));
            $foreignKey = $foreignTableName . '.id';
            foreach ($data as $key => $recordAttributes) {
                // get the related record
                $query = $foreignTable->findAllById($recordAttributes['id']);
                $foreignRecord = $query->first();
                // push it onto the relationsships array if it exists
                if (!empty($foreignRecord)) {
                    array_push($entity->$foreignTableName, $foreignRecord);
                }
            }
        }

        $this->_trigger('beforeSave', $subject);

        if (call_user_func([$this->_table(), $this->saveMethod()], $entity, $this->saveOptions())) {
            return $this->_success($subject);
        }

        $this->_error($subject);
    }

    /**
     * Success callback
     *
     * @param \Crud\Event\Subject $subject Event subject
     * @return \Cake\Http\Response|null
     */
    protected function _success(Subject $subject): ?Response
    {
        $subject->set(['success' => true, 'created' => false]);

        $this->setFlash('success', $subject);

        return $this->_redirect($subject, ['action' => 'index']);
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
}