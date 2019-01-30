<?php

namespace MoncareyWS\FoundationBundle\Generator;

use Symfony\Bundle\MakerBundle\Generator as MakerGenerator;


class Generator extends MakerGenerator
{
    public function __construct(FileManager $fileManager, string $namespacePrefix)
    {
        parent::__construct($fileManager, $namespacePrefix);
    }

    /**
     * Generate a new file for a class from a template.
     *
     * @param string $className The fully-qualified class name
     * @param string $templateName Template name in Resources/skeleton to use
     * @param array $variables Array of variables to pass to the template
     *
     * @return string The path where the file will be created
     *
     * @throws \Exception
     */
    public function generateClass(string $className, string $templateName, array $variables = []): string
    {
        $this->fixTemplateName($templateName);
        return parent::generateClass($className, $templateName, $variables);
    }

    /**
     * Generate a normal file from a template.
     *
     * @param string $targetPath
     * @param string $templateName
     * @param array $variables
     *
     * @throws \Exception
     */
    public function generateFile(string $targetPath, string $templateName, array $variables)
    {
        $this->fixTemplateName($templateName);
        parent::generateFile($targetPath, $templateName, $variables);
    }

    public function generateController(string $controllerClassName, string $controllerTemplatePath, array $parameters = []): string
    {
        $this->fixTemplateName($templateName);
        return parent::generateController($controllerClassName, $controllerTemplatePath, $parameters);
    }

    private function fixTemplateName(string &$templateName) {
        if (!file_exists($templateName)) {
            $templateName = __DIR__.'/../Resources/skeleton/'.$templateName;

            if (!file_exists($templateName)) {
                throw new \Exception(sprintf('Cannot find template "%s"', $templateName));
            }
        }
    }

}