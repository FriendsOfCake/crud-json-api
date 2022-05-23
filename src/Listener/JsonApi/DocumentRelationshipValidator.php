<?php
declare(strict_types=1);

namespace CrudJsonApi\Listener\JsonApi;

use Crud\Error\Exception\ValidationException;

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

    /**
     * @return bool
     */
    protected function _primaryDataMayBeNullEmptyArrayObjectOrArray(): bool
    {
        $dataProperty = $this->_getProperty('data');
        if ($this->_relationshipDataIsNull('') || empty((array)$dataProperty)) {
            return true;
        }

        $about = '';
        if (is_array($dataProperty)) {
            if (array_key_exists('type', $dataProperty) && array_key_exists('id', $dataProperty)) {
                return false;
            }

            $errors = false;
            foreach ($dataProperty as $val) {
                if (!is_array($val) || !array_key_exists('type', $val) || !array_key_exists('id', $val)) {
                    $errors = true;
                    break;
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
