<?php

declare(strict_types=1);

namespace CrudJsonApi\Route;

use Cake\Core\StaticConfigTrait;
use Cake\Datasource\FactoryLocator;
use Cake\ORM\Association;
use Cake\Routing\RouteBuilder;
use Cake\Utility\Hash;
use Cake\Utility\Inflector;

/**
 * Class RouteBuilder
 */
class JsonApiRoutes
{
    use StaticConfigTrait;

    protected static $_config = [
        'inflect' => 'variable'
    ];

    private static function inflect(string $string): string
    {
        $inflect = static::getConfig('inflect', 'variable');

        return Inflector::$inflect($string);
    }

    /**
     * @param \Cake\Routing\RouteBuilder $routeBuilder Routebuilder
     * @param \Cake\ORM\Association $association
     * @return void
     */
    private static function buildRelationshipLink(RouteBuilder $routeBuilder, Association $association): void
    {
        $controller = $association->getTarget()
            ->getAlias();
        $type = static::inflect($association->getProperty());
        $from = $association->getSource()
            ->getRegistryAlias();

        $base = [
            'controller' => $controller,
            'action' => 'relationships',
            'from' => $from,
            'type' => $type,
        ];
        $methods = ['GET', 'PATCH', 'POST', 'DELETE'];
        foreach ($methods as $method) {
            $routeBuilder->connect(
                '/relationships/' . $type,
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

        $from = $association->getSource()
            ->getRegistryAlias();
        $plugin = $routeBuilder->params()['plugin'] ?? null;

        $isOne = \in_array(
            $association->type(),
            [Association::MANY_TO_ONE, Association::ONE_TO_ONE],
            true
        );

        $pathName = static::inflect($association->getProperty());

        [$associationPlugin,] = pluginSplit($association->getClassName());
        if ($associationPlugin !== $plugin) {
            $routeBuilder->scope(
                '/',
                ['plugin' => $associationPlugin],
                function (RouteBuilder $routeBuilder) use (
                    $association,
                    $pathName,
                    $name,
                    $isOne,
                    $from
                ) {
                    $routeBuilder->connect(
                        '/' . static::inflect($pathName),
                        [
                            'controller' => $name,
                            '_method' => 'GET',
                            'action' => $isOne ? 'view' : 'index',
                            'from' => $from,
                        ]
                    );

                    static::buildRelationshipLink($routeBuilder, $association);
                }
            );

            return;
        }

        $routeBuilder->connect(
            '/' . static::inflect($pathName),
            [
                'controller' => $name,
                '_method' => 'GET',
                'action' => $isOne ? 'view' : 'index',
                'from' => $from,
            ]
        );
        static::buildRelationshipLink($routeBuilder, $association);
    }

    /**
     * @param iterable $models Array of models
     * @param \Cake\Routing\RouteBuilder $routeBuilder Route builder
     * @return void
     */
    public static function mapModels(iterable $models, RouteBuilder $routeBuilder): void
    {
        $models = Hash::normalize($models);
        $locator = FactoryLocator::get('Table');

        foreach ($models as $model => $options) {
            if (is_string($options)) {
                $model = $options;
                $options = [];
            }

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
                'relationshipLinks' => [],
                'inflect' => static::getConfig('inflect'),
            ];

            $options['ignoredAssociations'] = array_merge(
                $options['ignoredAssociations'],
                [
                    'Users',
                    'Accounts',
                ]
            );

            $plugin = $routeBuilder->params()['plugin'] ?? null;
            $className = $options['className'] ?: implode('.', [$plugin, $model]);

            /** @var \Cake\ORM\Table $tableObject */
            $tableObject = $locator->get($className);

            $associations = $tableObject->associations();

            $callback = null;
            if ($options['allowedAssociations'] !== false) {
                $callback = function (RouteBuilder $routeBuilder) use ($associations, $options) {
                    /** @var \Cake\ORM\Association $association */
                    foreach ($associations as $association) {
                        $this->buildAssociationLinks($routeBuilder, $association, $options);
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
