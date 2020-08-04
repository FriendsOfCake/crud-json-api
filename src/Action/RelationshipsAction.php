<?php
declare(strict_types=1);

namespace CrudJsonApi\Action;

use Cake\Datasource\ResultSetDecorator;
use Cake\Http\Exception\NotFoundException;
use Cake\ORM\Association;
use Cake\ORM\Locator\LocatorAwareTrait;
use Cake\Utility\Inflector;
use Crud\Action\BaseAction;
use Crud\Event\Subject;
use Crud\Traits\FindMethodTrait;
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
        'serialize' => [],
    ];

    /**
     * @param \Crud\Event\Subject $subject Subject
     * @param \Cake\ORM\Association\BelongsToMany $association Association
     * @return void
     */
    protected function _findManyToMany(Subject $subject, Association\BelongsToMany $association)
    {
        $foreignKey = $association->getForeignKey();
        $foreignKeyParam = $this->_request()
            ->getParam($foreignKey);

        $repository = $association->getTarget();
        $reverseAssociation = $repository->associations()->get;
        [$finder, $options] = $this->_extractFinder();
        $query = $repository->find($finder, $options)
            ->matching();

        $subject->set(
            [
                'association' => $association,
                'entities' => $query->all(),
                'success' => true,
            ]
        );
    }

    /**
     * @param \Crud\Event\Subject $subject Subject object
     * @return void
     * @throws \Exception
     */
    protected function _findRelations(Subject $subject)
    {
        $from = $this->_request()->getParam('from');
        $relationType = $this->_request()->getParam('type');
        $table = $this->getTableLocator()->get($from);
        $association = $table->getAssociation($relationType);

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
                $relationType => [
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
     * @param string|null $id Record id
     * @return void
     */
    protected function _get(): void
    {
        $subject = $this->_subject();

        $this->_findRelations($subject);
        $this->_trigger('beforeRender', $subject);
    }
}
