<?php
declare(strict_types=1);

namespace CrudJsonApi\Listener\JsonApi;

use Cake\ORM\Entity;
use Cake\Utility\Hash;
use Cake\Utility\Inflector;
use Cake\Validation\Validation;
use Crud\Error\Exception\CrudException;
use Crud\Error\Exception\ValidationException;
use Neomerx\JsonApi\Schema\Error;
use Neomerx\JsonApi\Schema\ErrorCollection;
use Neomerx\JsonApi\Schema\Link;
use stdClass;

/**
 * Validates incoming JSON API documents against the specifications for
 * CRUD actions described at http://jsonapi.org/format/#crud.
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 */
class DocumentValidator extends stdClass
{
    /**
     * RequestHandler decoded JSON API document array.
     *
     * @var array $_document
     */
    protected $_document;

    /**
     * @var \Neomerx\JsonApi\Schema\ErrorCollection
     */
    protected $_errorCollection;

    /**
     * JsonApiListener config() options
     *
     * @var array
     */
    protected $_config;

    /**
     * Constructor
     *
     * @param  array $documentArray  Decoded JSON API document
     * @param  array $listenerConfig JsonApiListener config() options
     * @return void
     */
    public function __construct(array $documentArray, array $listenerConfig)
    {
        $this->_document = $documentArray;

        $this->_config = $listenerConfig;

        $this->_errorCollection = new ErrorCollection();
    }

    /**
     * Validates a JSON API request data document used for creating
     * resources against the specification requirements described
     * at http://jsonapi.org/format/#crud-creating.
     *
     * @throws \Crud\Error\Exception\ValidationException
     * @return void
     */
    public function validateCreateDocument(): void
    {
        $this->_documentMustHavePrimaryData();
        $this->_primaryDataMustHaveType();
        $this->_primaryDataMayHaveUuid();
        $this->_primaryDataMayHaveRelationships();

        if ($this->_errorCollection->count() === 0) {
            return;
        }

        throw new ValidationException($this->_getErrorCollectionEntity());
    }

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
        $this->_primaryDataMustHaveType();
        $this->_primaryDataMustHaveId();
        $this->_primaryDataMayHaveRelationships();

        if ($this->_errorCollection->count() === 0) {
            return;
        }

        throw new ValidationException($this->_getErrorCollectionEntity());
    }

    /**
     * Document MUST have the top-level member `data`. If not, throw the
     * correct custom validation error with a pointer to '' as described at
     * http://jsonapi.org/examples/#error-objects-source-usage.
     *
     * @throws \Crud\Error\Exception\ValidationException
     * @return bool
     */
    protected function _documentMustHavePrimaryData(): bool
    {
        if ($this->_hasProperty('data')) {
            return true;
        }

        $this->_errorCollection->add(
            new Error(
                $idx = null,
                $aboutLink = $this->_getAboutLink('http://jsonapi.org/format/#document-top-level'),
                $typeLinks = null,
                $status = null,
                $code = null,
                $title = null,
                $detail = "Document does not contain top-level member 'data'",
                $source = [
                'pointer' => '',
                ]
            )
        );

        throw new ValidationException($this->_getErrorCollectionEntity());
    }

    /**
     * Ensures primary data has member 'type' which MUST be a string.
     *
     * @return bool
     */
    protected function _primaryDataMustHaveType()
    {
        $path = $this->_getPathObject('data.type');

        if (!$this->_hasProperty($path)) {
            $this->_errorCollection->addDataError(
                $title = '_required',
                $detail = "Primary data does not contain member 'type'",
                $status = null,
                $idx = null,
                $aboutLink = $this->_getAboutLink('http://jsonapi.org/format/#crud-creating')
            );

            return false;
        }

        $value = $this->_getProperty($path->dotted);

        if (is_string($value)) {
            return true;
        }

        $this->_errorCollection->addDataTypeError(
            $title = '_notString',
            $details = "Primary data member 'type' is not a string",
            $status = null,
            $idx = null,
            $aboutLink = $this->_getAboutLink('http://jsonapi.org/format/#document-resource-object-identification')
        );

        return false;
    }

    /**
     * Ensures primary data has member 'id' which MUST be a string.
     *
     * @return bool
     */
    protected function _primaryDataMustHaveId(): bool
    {
        $path = $this->_getPathObject('data.id');

        if (!$this->_hasProperty($path)) {
            $this->_errorCollection->addDataError(
                $title = '_required',
                $detail = "Primary data does not contain member 'id'",
                $status = null,
                $idx = null,
                $aboutLink = $this->_getAboutLink('http://jsonapi.org/format/#crud-updating')
            );

            return false;
        }

        $value = $this->_getProperty($path->dotted);

        if (is_string($value)) {
            return true;
        }

        $this->_errorCollection->addDataIdError(
            $title = '_notString',
            $details = "Primary data member 'id' is not a string",
            $status = null,
            $idx = null,
            $aboutLink = $this->_getAboutLink('http://jsonapi.org/format/#document-resource-object-identification')
        );

        return false;
    }

    /**
     * Ensures that primary data 'id' member is valid IF it exists.
     *
     * @return bool
     */
    protected function _primaryDataMayHaveUuid(): bool
    {
        $path = $this->_getPathObject('data.id');

        if (!$this->_hasProperty($path)) {
            return true;
        }

        $id = $this->_getProperty($path->dotted);

        if (Validation::uuid($id)) {
            return true;
        }

        $this->_errorCollection->addDataIdError(
            $title = '_notUuid',
            $details = "Primary data member 'id' is not a valid UUID",
            $status = null,
            $idx = null,
            $aboutLink = $this->_getAboutLink('http://jsonapi.org/format/#crud-creating-client-ids')
        );

        return false;
    }

    /**
     * Ensures that primary data 'relationships' member contains valid data
     * if it exists.
     *
     * @return bool
     */
    protected function _primaryDataMayHaveRelationships(): bool
    {
        $path = $this->_getPathObject('data.relationships');

        if (!$this->_hasProperty($path)) {
            return true;
        }

        $relationships = $this->_getProperty($path->dotted);

        if (empty($relationships)) {
            $this->_errorCollection->addRelationshipsError(
                $title = '_required',
                $detail = 'Relationships object does not contain any members',
                $status = null,
                $idx = null,
                $aboutLink = $this->_getAboutLink('http://jsonapi.org/format/#crud-creating')
            );

            return false;
        }

        foreach ($relationships as $relationship => $data) {
            $relationshipPathObject = $this->_getPathObject($path->dotted . '.' . $relationship);

            if (!$this->_relationshipMustHaveData($relationshipPathObject)) {
                continue;
            }

            if ($this->_relationshipDataIsNull($relationshipPathObject)) {
                continue;
            }

            // single belongsTo relationship
            if ($this->_stringIsSingular($relationshipPathObject->key)) {
                $this->_relationshipDataMustHaveType($relationship, $relationshipPathObject);
                $this->_relationshipDataMustHaveId($relationship, $relationshipPathObject);

                continue;
            }

            // multiple hasMany relationships
            $hasManys = $this->_getProperty($relationshipPathObject->dotted . '.data');

            $i = 0;
            foreach ($hasManys as $hasMany) {
                $pathObject = $this->_getPathObject($relationshipPathObject->dotted . '.data.' . $i);

                $this->_relationshipDataMustHaveType($relationship, $pathObject);
                $this->_relationshipDataMustHaveId($relationship, $pathObject);

                $i++;
            }
        }

        return true;
    }

    /**
     * Ensures a relationship object has a 'data' member.
     *
     * @param string|\stdClass $path Dot separated path of relationship object or path object
     * @return bool
     */
    protected function _relationshipMustHaveData($path): bool
    {
        $path = $this->_getPathObject($path);

        if ($this->_hasProperty($path->dotted . '.data')) {
            return true;
        }

        $this->_errorCollection->addRelationshipError(
            $name = $path->key,
            $title = '_required',
            $detail = "Relationships object does not contain member 'data'",
            $status = null,
            $idx = null,
            $aboutLink = $this->_getAboutLink('http://jsonapi.org/format/#crud-creating')
        );

        return false;
    }

    /**
     * Checks if relationship object has 'data' member set to null which is
     * allowed by the JSON API spec.
     *
     * @param string|\stdClass $path Dot separated path of relationship object or path object
     * @return bool
     */
    protected function _relationshipDataIsNull($path): bool
    {
        $path = $this->_getPathObject($path);

        return $this->_getProperty($path->dotted . '.data') === null;
    }

    /**
     * Ensures a relationship data has a 'type' member.
     *
     * @param  string          $relationship Singular or plural relationship name
     * @param string|\stdClass $path Dot separated path of relationship object or path object
     * @return bool
     */
    protected function _relationshipDataMustHaveType($relationship, $path): bool
    {
        $path = $this->_getPathObject($path);

        // generate correct feedback and path for hasMany and belongsTo relationships
        $array = $this->_getProperty($path);
        $arrayDepth = Hash::dimensions($array);

        if ($arrayDepth === 1) {
            $searchPath = $path->dotted . '.type'; // hasMany
            $pointer = $relationship . '/data/' . $path->key;
        } else {
            $searchPath = $path->dotted . '.data.type'; // belongsTo
            $pointer = $relationship . '/data';
        }

        // make sure the relationship data has the `type` key
        if (!$this->_hasProperty($searchPath)) {
            $this->_errorCollection->addRelationshipError(
                $name = $pointer,
                $title = '_required',
                $detail = "Relationship data does not contain member 'type'",
                $status = null,
                $idx = null,
                $aboutLink = $this->_getAboutLink('http://jsonapi.org/format/#crud-creating')
            );

            return false;
        }

        // key exists so update the pointer before checking if value is a string
        $pointer .= '/type';

        if (!$this->_isString($searchPath)) {
            $this->_errorCollection->addRelationshipError(
                $name = $pointer,
                $title = '_required',
                $detail = "Relationship data member 'type' is not a string",
                $status = null,
                $idx = null,
                $aboutLink = $this->_getAboutLink('http://jsonapi.org/format/#crud-creating')
            );

            return false;
        }

        return true;
    }

    /**
     * Ensures relationship data has an 'id' member.
     *
     * @param  string          $relationship Singular or plural relationship name
     * @param string|\stdClass $path Dot separated path of relationship object or path object
     * @return bool
     */
    protected function _relationshipDataMustHaveId(string $relationship, $path): bool
    {
        $path = $this->_getPathObject($path);

        // generate correct feedback and path for hasMany and belongsTo relationships
        $array = $this->_getProperty($path);
        $arrayDepth = Hash::dimensions($array);

        if ($arrayDepth === 1) {
            $searchPath = $path->dotted . '.id'; // hasMany
            $pointer = $relationship . '/data/' . $path->key;
        } else {
            $searchPath = $path->dotted . '.data.id'; // belongsTo
            $pointer = $relationship . '/data';
        }

        // make sure the relationship data has the `type` key
        if (!$this->_hasProperty($searchPath)) {
            $this->_errorCollection->addRelationshipError(
                $name = $pointer,
                $title = '_required',
                $detail = "Relationship data does not contain member 'id'",
                $status = null,
                $idx = null,
                $aboutLink = $this->_getAboutLink('http://jsonapi.org/format/#crud-creating')
            );

            return false;
        }

        // key exists so update the pointer before checking if value is a string
        $pointer = $pointer . '/id';

        if (!$this->_isString($searchPath)) {
            $this->_errorCollection->addRelationshipError(
                $name = $pointer,
                $title = '_required',
                $detail = "Relationship data member 'type' is not a string",
                $status = null,
                $idx = null,
                $aboutLink = $this->_getAboutLink('http://jsonapi.org/format/#crud-creating')
            );

            return false;
        }

        return true;
    }

    /**
     * Checks if a document property is a string.
     *
     * @param  string|\stdClass $path Dot separated path of the property
     * @return bool
     */
    protected function _isString($path): bool
    {
        $path = $this->_getPathObject($path);

        if (!$this->_hasProperty($path)) {
            throw new CrudException("Document member '$path->dotted' does not exist");
        }

        $value = $this->_getProperty($path->dotted);

        if (is_string($value)) {
            return true;
        }

        return false;
    }

    /**
     * Checks if a document property is a valid UUID.
     *
     * @param  string|\stdClass $path Dot separated path of the property
     * @return bool
     * @throws \Crud\Error\Exception\CrudException
     */
    protected function _isUuid($path): bool
    {
        $path = $this->_getPathObject($path);

        if (!$this->_hasProperty($path)) {
            throw new CrudException("Document member '$path->dotted' does not exist");
        }

        $id = $this->_getProperty($path->dotted);

        if (Validation::uuid($id)) {
            return true;
        }

        return false;
    }

    /**
     * Checks if document contains a given property (even when value
     * is `false` or `null`).
     *
     * @param string|\stdClass $path Dot separated path of the property or a path object
     * @return bool
     */
    protected function _hasProperty($path): bool
    {
        if (is_a($path, 'stdClass')) {
            $path = $path->dotted;
        }

        $current = $this->_document;
        $parts = strtok($path, '.');

        while ($parts !== false) {
            if (!array_key_exists($parts, $current)) {
                return false;
            }
            $current = $current[$parts];
            $parts = strtok('.');
        }

        return true;
    }

    /**
     * Returns the value for a given document property.
     *
     * @param string|\stdClass $path Dot separated path of the property or path object
     * @throws \Crud\Error\Exception\CrudException
     * @return mixed
     */
    protected function _getProperty($path)
    {
        if (is_a($path, 'stdClass')) {
            $path = $path->dotted;
        }

        $current = $this->_document;

        $pathClone = $path;
        $parts = strtok($pathClone, '.');

        while ($parts !== false) {
            if (!array_key_exists($parts, $current)) {
                throw new CrudException("Error retrieving a value for non-existing JSON API document property '$path'");
            }
            $current = $current[$parts];
            $parts = strtok('.');
        }

        return $current;
    }

    /**
     * Helper method to create an object with consistent path strings from
     * given dot separated path.
     *
     * @param string|\stdClass $path Dot separated path or stdClass $path object
     * @return \stdClass
     */
    protected function _getPathObject($path): \stdClass
    {
        // return as-is if parameter is
        if (is_a($path, 'stdClass')) {
            return $path;
        }

        // create path object from given string
        $obj = new stdClass();
        $obj->dotted = $path;

        $parts = explode('.', $path);

        if (count($parts) === 1) {
            $obj->toKey = null;
            $obj->key = $path;

            return $obj;
        }

        $key = end($parts);
        array_pop($parts);
        $obj->toKey = implode('.', $parts);
        $obj->key = $key;

        return $obj;
    }

    /**
     * Helper method that displays aboutLink only if enabled in Listener config.
     *
     * @param  string $url URL
     * @return \Neomerx\JsonApi\Schema\Link|null
     */
    protected function _getAboutLink(string $url): ?Link
    {
        if ($this->_config['docValidatorAboutLinks'] === false) {
            return null;
        }

        return new Link(false, $url, false);
    }

    /**
     * Helper method to make the ErrorCollection object available inside the
     * JsonApiExceptionRenderer validation() method by cloaking it as a
     * default CakePHP validation error.
     *
     * @throws \Crud\Error\Exception\ValidationException
     * @return \Cake\ORM\Entity
     */
    protected function _getErrorCollectionEntity(): Entity
    {
        $entity = new Entity();

        $entity->setErrors(
            [
            'CrudJsonApiListener' => [
                'NeoMerxErrorCollection' => $this->_errorCollection,
            ],
            ]
        );

        return $entity;
    }

    /**
     * Helper function to determine if string is singular or plural.
     *
     * @param  string $string Preferably a CakePHP generated name.
     * @return bool
     */
    protected function _stringIsSingular($string): bool
    {
        return Inflector::singularize($string) === $string;
    }
}
