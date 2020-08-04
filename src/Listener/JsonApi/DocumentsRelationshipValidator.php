<?php
declare(strict_types=1);

namespace CrudJsonApi\Listener\JsonApi;

use Cake\ORM\Entity;
use Cake\Utility\Hash;
use Cake\Utility\Inflector;
use Cake\Validation\Validation;
use Crud\Error\Exception\CrudException;
use Crud\Error\Exception\ValidationException;
use CrudJsonApi\Listener\JsonApi\DocumentValidator;
use Neomerx\JsonApi\Schema\Error;
use Neomerx\JsonApi\Schema\ErrorCollection;
use Neomerx\JsonApi\Schema\Link;

/**
 * Validates incoming JSON API documents against the specifications for
 * CRUD actions described at http://jsonapi.org/format/#crud.
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 */
class DocumentRelationshipValidator extends DocumentValidator
{
    /**
       * Validates a JSON API request data document used for updating
       * resources against the specification requirements described
       * at http://jsonapi.org/format/#crud-updating.
       *
       * @throws \Crud\Error\Exception\ValidationException
       * @return void
       */
    public function validateUpdateDocument(): void
    {
        $this->_documentMustHavePrimaryData();
        $this->_primaryDataMayBeNullEmptyArrayObjectOrArray();
        if ($this->_errorCollection->count() === 0) {
            return;
        }

        throw new ValidationException($this->_getErrorCollectionEntity());
    }

    public function _arrayObjectsMustHaveType()
    {
        $dataProperty = $this->_getProperty('data');
        if (is_array($dataProperty)) {
            foreach ($dataProperty as $key => $object) {
            }
        }
    }

    public function _primaryDataObjectArrayObjectsMustHaveType($arr)
    {
        if (!empty($arr)) {
            foreach ($var as $key => $obj) {
            }
        }
    }

    public function _primaryDataObjectArrayObjectsMustHaveId($arr)
    {
        $dataProperty = $this->_getProperty('data');
    }

    public function _primaryDataMayBeNullEmptyArrayObjectOrArray()
    {
        $dataProperty = $this->_getProperty('data');
        if ($this->_relationshipDataIsNull('') || empty((array) $dataProperty)) {
            return true;
        }

        if (is_array($dataProperty)) {
            $errors = false;
            foreach ($dataProperty as $key => $val) {
                if (is_array($val)) {
                    if (!array_key_exists('type', $val) || !array_key_exists('id', $val)) {
                        $errors = true;
                    }
                } else {
                    $errors = true;
                }
            }
            if (!$errors) {
                return true;
            }
            $about = '#crud-updating-to-many-relationships';
        }
        $this->_errorCollection->addDataAttributeError(
            $name = 'data',
            $title = '_required',
            $detail = "Related records are missing member 'type' or 'id'",
            $status = null,
            $idx = null,
            $aboutLink = $this->_getAboutLink('http://jsonapi.org/format/' . $about)
        );

        return false;
    }
}
