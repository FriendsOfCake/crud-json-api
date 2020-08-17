<?php

namespace CrudJsonApi;

use Cake\Utility\Inflector;

trait InflectTrait
{
    /**
     * @param object $configClass Class to get config from
     * @param string $input Input string
     * @return string
     */
    protected function inflect(object $configClass, string $input): string
    {
        $inflect = $configClass->getConfig('inflect', 'variable');

        if (!$inflect) {
            return $input;
        }

        return Inflector::$inflect($input);
    }
}
