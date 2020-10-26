<?php
declare(strict_types=1);

namespace CrudJsonApi\View;

use Cake\Core\App;
use Cake\Core\Configure;
use Cake\Event\EventManager;
use Cake\Http\Response;
use Cake\Http\ServerRequest;
use Cake\ORM\Entity;
use Cake\Utility\Inflector;
use Cake\View\View;
use Crud\Error\Exception\CrudException;
use CrudJsonApi\InflectTrait;
use Neomerx\JsonApi\Contracts\Encoder\EncoderInterface;
use Neomerx\JsonApi\Contracts\Schema\LinkInterface;
use Neomerx\JsonApi\Encoder\Encoder;
use Neomerx\JsonApi\Schema\Link;

class JsonApiView extends View
{
    use InflectTrait;

    /**
     * Constructor
     *
     * @param \Cake\Http\ServerRequest $request      ServerRequest
     * @param \Cake\Http\Response      $response     Response
     * @param \Cake\Event\EventManager $eventManager EventManager
     * @param array                    $viewOptions  An array of view options
     */
    public function __construct(
        ?ServerRequest $request = null,
        ?Response $response = null,
        ?EventManager $eventManager = null,
        array $viewOptions = []
    ) {
        parent::__construct($request, $response, $eventManager, $viewOptions);

        if ($response && $response instanceof Response) {
            $this->response = $response->withType('jsonapi');
        }
    }

    /**
     * Returns an array of special viewVars (names starting with an underscore).
     *
     * We need to dynamically generate this array to prevent special vars
     * from the app or other plugins being passed to NeoMerx for processing
     * as data (and thus effectively breaking this view).
     *
     * @return array
     */
    protected function _getSpecialVars(): array
    {
        $result = [];

        $viewVarKeys = $this->getVars();
        foreach ($viewVarKeys as $viewVarKey) {
            if ($viewVarKey[0] === '_') {
                $result[] = $viewVarKey;
            }
        }

        return $result;
    }

    /**
     * Renders one of the three supported JSON API responses:
     *
     * - with body containing an entity based resource (data)
     * - with empty body
     * - with body containing only the meta node
     *
     * @param  string|null $template   Name of view file to use
     * @param  string|null $layout Layout to use.
     * @return string
     */
    public function render(?string $template = null, $layout = null): string
    {
        if ($this->getConfig('association')) {
            $json = $this->_encodeWithIdentifiers();
        } elseif ($this->getConfig('repositories')) {
            $json = $this->_encodeWithSchemas();
        } else {
            $json = $this->_encodeWithoutSchemas();
        }

        // Add query logs node if ApiQueryLogListener is loaded
        if (Configure::read('debug') && $this->get('queryLog')) {
            $json = json_decode((string)$json, true);
            $json['query'] = $this->get('queryLog');
            $json = json_encode($json, $this->_jsonOptions());
        }

        return $json ?: '';
    }

    /**
     * @param array $schemas Array of schemas
     * @return \Neomerx\JsonApi\Contracts\Encoder\EncoderInterface
     */
    protected function _getEncoder(array $schemas = []): EncoderInterface
    {
        // Please note that a third NeoMerx EncoderOptions argument `depth`
        // exists but has not been implemented in this plugin.
        $encoder = Encoder::instance($schemas)
            ->withEncodeOptions($this->_jsonOptions());

        // Add optional top-level `version` node to the response if enabled
        // by user using listener config option.
        if ($this->getConfig('withJsonApiVersion')) {
            $encoder->withJsonApiVersion('1.1');
            if (!is_bool($this->getConfig('withJsonApiVersion'))) {
                $encoder->withJsonApiMeta($this->getConfig('withJsonApiVersion'));
            }
        }

        // Add optional top-level `link` node to the response if enabled by
        // user using listener config option.
        if ($this->getConfig('links')) {
            $links = $this->getConfig('links');
            $encoder->withLinks(
                array_map(
                    function ($link) {
                        if ($link instanceof Link) {
                            return $link;
                        }

                        return new Link(false, $link, false);
                    },
                    $links
                )
            );
        }

        return $encoder;
    }

    /**
     * Generates a JSON API string without resource(s).
     *
     * @return null|string
     */
    protected function _encodeWithoutSchemas(): ?string
    {
        if (empty($this->getConfig('meta'))) {
            return null;
        }

        $encoder = $this->_getEncoder();

        return $encoder->encodeMeta($this->getConfig('meta'));
    }

    /**
     * @return string
     */
    protected function _encodeWithIdentifiers(): string
    {
        /** @var \Cake\ORM\Association $association */
        $association = $this->getConfig('association');
        $sourceRepository = $association->getSource();
        $targetRepository = $association->getTarget();
        $associationName = $this->inflect($this, $association->getProperty());

        $this->setConfig('repositories', [
            $targetRepository->getRegistryAlias() => $targetRepository,
            $sourceRepository->getRegistryAlias() => $sourceRepository,
        ]);
        $schemas = $this->_entitiesToNeoMerxSchema($this->getConfig('repositories'));
        $encoder = $this->_getEncoder($schemas);

        $viewVar = $this->get('viewVar');
        $entity = $this->get($viewVar);
        $encoder
            ->withRelationshipSelfLink($entity, $associationName)
            ->withRelationshipRelatedLink($entity, $associationName);

        return $encoder->encodeIdentifiers($entity->get($association->getProperty()));
    }

    /**
     * Generates a JSON API string with resource(s).
     *
     * @return string
     */
    protected function _encodeWithSchemas(): string
    {
        if ($this->getConfig('inflect')) {
            $this->_inflectIncludesViewVar();
        }

        // Add top-level `links` node with pagination information (requires
        // ApiPaginationListener which will have set/filled viewVar).
        if ($this->getConfig('pagination')) {
            $pagination = $this->getConfig('pagination');

            $links = $this->getConfig('links', []);
            $paginationLinks = $this->_getPaginationLinks($pagination);
            $this->setConfig('links', $links + $paginationLinks);

            // Additional pagination information has to be in top-level node `meta`
            $meta = $this->getConfig('meta');
            $meta['record_count'] = $pagination['record_count'];
            $meta['page_count'] = $pagination['page_count'];
            $meta['page_limit'] = $pagination['page_limit'];
            $this->setConfig('meta', $meta);
        }

        // All "Schema is not registered for a resource at path 'xyz'" errors
        // originate from the line below and are caused by the mentioned Cake Table
        // object not being present in the  `repositories` option.
        $schemas = $this->_entitiesToNeoMerxSchema($this->getConfig('repositories'));

        $encoder = $this->_getEncoder($schemas);

        $serialize = $this->getConfig('serialize');

        if ($serialize !== false) {
            $serialize = $this->_getDataToSerializeFromViewVars($serialize);
        }

        // By default the listener will automatically add all associated data
        // (as produced by containable and thus present in the entity) to the
        // '_include' viewVar which is used to produce the top-level `included`
        // node UNLESS user specified an array with associations using the
        // listener config option `include`.
        //
        // Please be aware that `include` will take precedence over the
        //`getIncludePaths()` method that MIGHT exist in custom NeoMerx schemas.
        //
        // Lastly, listener config option `fieldSets` may be used to limit
        // the fields shown in the result.
        $include = $this->getConfig('include');
        $fieldSets = $this->getConfig('fieldSets');

        $encoder
            ->withIncludedPaths($include)
            ->withFieldSets($fieldSets);

        // Add optional top-level `meta` node to the response if enabled by
        // user using listener config option.
        if ($this->getConfig('meta')) {
            if (empty($serialize)) {
                return $encoder->encodeMeta($this->getConfig('meta'));
            }

            $encoder->withMeta($this->getConfig('meta'));
        }

        // JSON API as generated by NeoMerx. When things look off,  start debugging here
        return $encoder->encodeData($serialize);
    }

    /**
     * Maps each entity to the first schema match in this order:
     * 1. custom entity schema
     * 2. custom dynamic schema
     * 3. Crud's dynamic schema
     *
     * @param  \Cake\ORM\Table[] $repositories List holding repositories used to map entities to schema classes
     * @throws \Crud\Error\Exception\CrudException
     * @return array A list with Entity class names as key holding NeoMerx Closure object
     */
    protected function _entitiesToNeoMerxSchema(array $repositories): array
    {
        $schemas = [];
        foreach ($repositories as $repositoryName => $repository) {
            $entityClass = $repository->getEntityClass();

            if (isset($schemas[$entityClass])) {
                continue;
            }

            if ($entityClass === Entity::class) {
                throw new CrudException(sprintf(
                    'Entity classes must not be the generic "%s" class for repository "%s"',
                    $entityClass,
                    $repositoryName
                ));
            }

            // Turn full class name back into plugin split format
            // Not including /Entity in the type makes sure its compatible with other types
            $entityName = App::shortName($entityClass, 'Model');

            // Take plugin name and entity name off
            [$pluginName, $entityName] = pluginSplit($entityName, true);

            // Find the first namespace separator to take everything after the entity type.
            $firstNamespaceSeparator = strpos($entityName, '/');
            if ($firstNamespaceSeparator === false) {
                throw new CrudException('Invalid entity name specified');
            }
            $entityName = substr($entityName, $firstNamespaceSeparator + 1);

            $entityName = $pluginName . $entityName;

            // If user created a custom entity schema... use it
            $schemaClass = App::className($entityName, 'Schema\JsonApi', 'Schema');

            // If user created a custom dynamic schema... use it
            if (!$schemaClass) {
                $schemaClass = App::className('DynamicEntity', 'Schema\JsonApi', 'Schema');
            }

            // Otherwise use the dynamic schema provided by Crud
            if (!$schemaClass) {
                $schemaClass = App::className('CrudJsonApi.DynamicEntity', 'Schema\JsonApi', 'Schema');
            }

            //Otherwise something is horribly wrong
            if (!$schemaClass) {
                throw new \RuntimeException('No valid schema classes found');
            }

            // Uses NeoMerx createSchemaFromClosure()` to generate Closure
            // object with schema information.
            $schema = function ($factory) use ($schemaClass, $repository) {
                return new $schemaClass($factory, $this, $repository);
            };

            // Add generated schema to the collection before processing next
            $schemas[$repository->getEntityClass()] = $schema;
        }

        return $schemas;
    }

    /**
     * Returns an array with NeoMerx Link objects to be used for pagination.
     *
     * @param  array $pagination ApiPaginationListener pagination response
     * @return array
     */
    protected function _getPaginationLinks($pagination): array
    {
        $links = [];

        if (isset($pagination['self'])) {
            $links[LinkInterface::SELF] = new Link(false, $pagination['self'], false);
        }

        if (isset($pagination['first'])) {
            $links[LinkInterface::FIRST] = new Link(false, $pagination['first'], false);
        }

        if (isset($pagination['last'])) {
            $links[LinkInterface::LAST] = new Link(false, $pagination['last'], false);
        }

        if (isset($pagination['prev'])) {
            $links[LinkInterface::PREV] = new Link(false, $pagination['prev'], false);
        }

        if (isset($pagination['next'])) {
            $links[LinkInterface::NEXT] = new Link(false, $pagination['next'], false);
        }

        return $links;
    }

    /**
     * Returns data to be serialized.
     *
     * @param  array|string|bool|object $serialize The name(s) of the view variable(s) that
     *                                             need(s) to be serialized. If true all available view variables will be used.
     * @return mixed The data to serialize.
     */
    protected function _getDataToSerializeFromViewVars($serialize = true)
    {
        if (is_object($serialize)) {
            throw new CrudException(
                'Assigning an object to JsonApiListener "serialize" is deprecated, ' .
                'assign the object to its own variable and assign "serialize" = true instead.'
            );
        }

        if ($serialize === true) {
            $viewVars = array_diff(
                $this->getVars(),
                $this->_getSpecialVars()
            );

            if (empty($viewVars)) {
                return null;
            }

            $serialize = current($viewVars);
        }

        if (is_array($serialize)) {
            $serialize = current($serialize);
        }

        return $this->get($serialize);
    }

    /**
     * Returns an integer flag holding any combination of php predefined json
     * option constants as found at http://php.net/manual/en/json.constants.php.
     *
     * @return int Flag holding json options
     */
    protected function _jsonOptions()
    {
        $jsonOptions = 0;

        if (!empty($this->getConfig('jsonOptions'))) {
            foreach ($this->getConfig('jsonOptions') as $jsonOption) {
                $jsonOptions |= $jsonOption;
            }

            if (isset($jsonOption)) {
                $jsonOptions |= $jsonOption;
            }
        }

        if (Configure::read('debug') === false) {
            return $jsonOptions;
        }

        if ($this->getConfig('debugPrettyPrint')) {
            $jsonOptions |= JSON_PRETTY_PRINT;
        }

        return $jsonOptions;
    }

    /**
     * Dasherizes all values in the 'includes` config.
     *
     * @return void
     */
    protected function _inflectIncludesViewVar()
    {
        $inflect = $this->getConfig('inflect');
        $include = $this->getConfig('include');
        foreach ($include as $key => $value) {
            $include[$key] = Inflector::$inflect($value);
        }

        $this->setConfig('include', $include);
    }
}
