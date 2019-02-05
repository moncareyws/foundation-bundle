<?php
/**
 * Created by PhpStorm.
 * User: samuel
 * Date: 01/02/19
 * Time: 15:57
 */

namespace MoncareyWS\FoundationBundle\Generator;


class Validator
{
    public static function validateFormat($format)
    {
        if (!$format) {
            throw new \RuntimeException('Please enter a configuration format.');
        }

        $format = strtolower($format);

        // in case they typed "yaml", but ok with that
        if ($format == 'yaml') {
            $format = 'yml';
        }

        if (!in_array($format, array('php', 'xml', 'yml', 'annotation'))) {
            throw new \RuntimeException(sprintf('Format "%s" is not supported.', $format));
        }

        return $format;
    }

    public static function validateRoutePrefix($routePrefix)
    {
        return $routePrefix;
    }
}