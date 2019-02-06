<?php
/**
 * Created by PhpStorm.
 * User: samuel
 * Date: 2/6/19
 * Time: 10:50 PM
 */

namespace MoncareyWS\FoundationBundle\Command;

use Symfony\Component\DependencyInjection\ContainerInterface;


trait CommandNeedsPublicDir
{

    private function getPublicDirectory(ContainerInterface $container)
    {
        $defaultPublicDir = 'public';

        if (!$container->hasParameter('kernel.project_dir')) {
            return $defaultPublicDir;
        }

        $composerFilePath = $container->getParameter('kernel.project_dir').'/composer.json';

        if (!file_exists($composerFilePath)) {
            return $defaultPublicDir;
        }

        $composerConfig = json_decode(file_get_contents($composerFilePath), true);

        if (isset($composerConfig['extra']['public-dir'])) {
            return $composerConfig['extra']['public-dir'];
        }

        return $defaultPublicDir;
    }

}