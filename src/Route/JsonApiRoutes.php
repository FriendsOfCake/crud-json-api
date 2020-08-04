<?php
declare(strict_types=1);

namespace CrudJsonApi\Route;

use Cake\Core\App;
use Cake\Core\StaticConfigTrait;
use Cake\ORM\Association;
use Cake\ORM\TableRegistry;
use Cake\Routing\RouteBuilder;
use Cake\Utility\Hash;
use Cake\Utility\Inflector;

/**
 * Class RouteBuilder
 */
class JsonApiRoutes
{
    use StaticConfigTrait;

    /**
     * @param string $string String to inflect
     * @return string
     */
    private static function inflect(string $string): string
    {
        $inflect = static::getConfig('inflect') ?? 'variable';

        return Inflector::$inflect($string);
    }

    /**
     * @param \Cake\Routing\RouteBuilder $routeBuilder Routebuilder
     * @param \Cake\ORM\Association $association Association
     * @param array $options Options array
     * @return void
     */
    private static function buildRelationshipLink(RouteBuilder $routeBuilder, Association $association, array $options): void
    {
        $type = $association->getName();

        if (
            $options['relationshipLinks'] === false ||
            (is_array($options['relationshipLinks']) && !array_key_exists($type, $options['relationshipLinks']) && $options['relationshipLinks']['*'] === false)
        ) {
            return;
        }

        $methodMap = [
            'replace' => 'PATCH',
            'add' => 'POST',
            'delete' => 'DELETE',
        ];

        if (isset($options['relationshipLinks'][$type]) && is_array($options['relationshipLinks'][$type])) {
            $allowedMethods = $options['relationshipLinks'][$type];
        }

        if (empty($allowedMethods)) {
            $allowedMethods = array_keys($methodMap);
        }

        $path = static::inflect($association->getProperty());
        $from = $association->getSource()
            ->getRegistryAlias();
        [, $controller] = pluginSplit($from);

        $base = [
            'controller' => $controller,
            'action' => 'relationships',
            'type' => $type,
        ];
        $methodMap['read'] = 'GET';
        foreach ($methodMap as $action => $method) {
            if ($action !== 'read' && !in_array($action, $allowedMethods, true)) {
                continue;
            }

            $routeBuilder->connect(
                '/relationships/' . $path,
                $base + ['_method' => $method]
            );
        }
    }

    /**
     * @param \Cake\Routing\RouteBuilder $routeBuilder Route builder
     * @param \Cake\ORM\Association $association Association object
     * @param array $options Array of options
     * @return void
     */
    private static function buildAssociationLinks(RouteBuilder $routeBuilder, Association $association, array $options)
    {
        $name = $association->getAlias();

        if (in_array($name, $options['ignoredAssociations'], true)) {
            return;
        }

        if (is_array($options['allowedAssociations']) && !in_array($name, $options['allowedAssociations'], true)) {
            return;
        }

        $generateRelationshipLinks = $options['generateRelationshipLinks'] === true ||
            (is_array($options['generateRelationshipLinks']) &&
                in_array($name, $options['generateRelationshipLinks'], true));

        $from = $association->getSource()->getRegistryAlias();
        $plugin = $routeBuilder->params()['plugin'] ?? null;

        $isOne = \in_array(
            $association->type(),
            [Association::MANY_TO_ONE, Association::ONE_TO_ONE],
            true
        );

        $pathName = static::inflect($association->getProperty());

        $associationRepository = App::shortName(get_class($association->getTarget()), 'Model/Table', 'Table');
        [$associationPlugin, $controller] = pluginSplit($associationRepository);

        static::buildRelationshipLink($routeBuilder, $association, $options);

        if ($associationPlugin !== $plugin) {
            $routeBuilder->scope(
                '/',
                ['plugin' => $associationPlugin],
                static function (RouteBuilder $routeBuilder) use (
                    $pathName,
                    $name,
                    $isOne,
                    $from,
                    $controller,
                    $generateRelationshipLinks
                ) {
                    $routeBuilder->connect(
                        '/' . $pathName,
                        [
                            'controller' => $controller,
                            '_method' => 'GET',
                            'action' => $isOne ? 'view' : 'index',
                            'from' => $from,
                            'type' => $name,
                        ]
                    );
                }
            );

            return;
        }

        $routeBuilder->connect(
            '/' . static::inflect($pathName),
            [
                'controller' => $controller,
                '_method' => 'GET',
                'action' => $isOne ? 'view' : 'index',
                'from' => $from,
                'type' => $name,
            ]
        );
    }

    /**
     * @param array $models Array of models
     * @param \Cake\Routing\RouteBuilder $routeBuilder Route builder
     * @return void
     */
    public static function mapModels(array $models, RouteBuilder $routeBuilder): void
    {
        $models = Hash::normalize($models);
        $locator = TableRegistry::getTableLocator();

        foreach ($models as $model => $options) {
            $options = $options ?: [];
            $callback = null;
            if (isset($options[0])) {
                $options = [
                    'only' => $options,
                ];
            }

            $options += [
                'className' => null,
                'allowedAssociations' => true,
                'ignoredAssociations' => [],
                'relationshipLinks' => true,
                'inflect' => static::getConfig('inflect') ?? 'variable',
            ];

            $options['ignoredAssociations'] = array_merge(
                $options['ignoredAssociations'],
                [
                    'Users',
                    'Accounts',
                ]
            );
            if (is_array($options['relationshipLinks'])) {
                $options['relationshipLinks'] = Hash::normalize($options['relationshipLinks']) + ['*' => false];
            }

            $plugin = $routeBuilder->params()['plugin'] ?? null;
            $className = $options['className'] ?: implode('.', [$plugin, $model]);

            $tableObject = $locator->get($className);

            $associations = $tableObject->associations();

            if ($options['allowedAssociations'] !== false) {
                $callback = function (RouteBuilder $routeBuilder) use ($associations, $options) {
                    /** @var \Cake\ORM\Association $association */
                    foreach ($associations as $association) {
                        static::buildAssociationLinks($routeBuilder, $association, $options);
                    }
                };
            }

            unset($options['className'], $options['allowedAssociations'], $options['ignoredAssociations']);

            $routeBuilder->resources(
                $model,
                $options,
                $callback
            );
        }
    }
}
