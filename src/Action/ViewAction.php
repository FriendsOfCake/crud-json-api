<?php

namespace CrudJsonApi\Action;

use Cake\Datasource\EntityInterface;
use Crud\Action\ViewAction as BaseViewAction;
use Crud\Event\Subject;

/**
 * Class IndexAction
 */
class ViewAction extends BaseViewAction
{
    protected function _handle(?string $id = null): void
    {
        $request = $this->_request();
        $from = $request->getParam('from');
        $relationName = $request->getParam('type');

        //For non-related links treat as always
        if (!$from || !$relationName) {
            parent::_handle($id);

            return;
        }

        $subject = $this->_subject();
        $this->_findRecordViaRelated($subject);
        $this->_trigger('beforeRender', $subject);
    }

    /**
     * Find a record via related linkage
     *
     * @param \Crud\Event\Subject $subject Event subject
     * @return \Cake\Datasource\EntityInterface
     * @throws \Exception
     */
    protected function _findRecordViaRelated(Subject $subject): EntityInterface
    {
        $repository = $this->_table();

        [$finder, $options] = $this->_extractFinder();
        $query = $repository->find($finder, $options);

        $subject->set(
            [
                'repository' => $repository,
                'query' => $query,
            ]
        );

        $this->_trigger('beforeFind', $subject);
        $entity = $subject->query->first();

        if (!$entity) {
            $this->_notFound(null, $subject);
        }

        $subject->set(['entity' => $entity, 'success' => true]);
        $this->_trigger('afterFind', $subject);

        return $entity;
    }
}
