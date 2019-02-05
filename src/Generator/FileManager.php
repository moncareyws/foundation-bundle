<?php

namespace MoncareyWS\FoundationBundle\Generator;

use Symfony\Bundle\MakerBundle\FileManager as MakerFileManager;
use Symfony\Bundle\MakerBundle\Util\AutoloaderUtil;
use Symfony\Component\Filesystem\Filesystem;
use Twig\Environment;

class FileManager extends MakerFileManager
{
    /** @var Environment */
    protected $twig;

    public function __construct(Filesystem $fs, AutoloaderUtil $autoloaderUtil, string $rootDirectory, Environment $twig)
    {
        $this->twig = $twig;
        parent::__construct($fs, $autoloaderUtil, $rootDirectory);
    }

    public function parseTemplate(string $templatePath, array $parameters): string
    {
        if (pathinfo($templatePath, PATHINFO_EXTENSION) == 'twig') {
//            fwrite(STDOUT, print_r($parameters, true));
            return $this->twig->render($templatePath, $parameters);
        }

        return parent::parseTemplate($templatePath,$parameters);
    }
}