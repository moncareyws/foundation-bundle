<?php

namespace MoncareyWS\FoundationBundle\DependencyInjection;

use MoncareyWS\FoundationBundle\DependencyInjection\CompilerPass\FoundationCommandRegistrationPass;
use Symfony\Bundle\MakerBundle\MakerInterface;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

/**
 * This is the class that loads and manages your bundle configuration.
 *
 * @see http://symfony.com/doc/current/cookbook/bundles/extension.html
 */
class MakerExtension extends Extension
{
    /**
     * {@inheritdoc}
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $loader = new Loader\XmlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('services.xml');
        $loader->load('makers.xml');

        $configuration = $this->getConfiguration($configs, $container);
        $config = $this->processConfiguration($configuration, $configs);

        $rootNamespace = trim($config['root_namespace'], '\\');

        $makeCommandDefinition = $container->getDefinition('foundation.maker.generator');
        $makeCommandDefinition->replaceArgument(1, $rootNamespace);

//        $doctrineHelperDefinition = $container->getDefinition('maker.doctrine_helper');
//        $doctrineHelperDefinition->replaceArgument(0, $rootNamespace.'\\Entity');

        $container->registerForAutoconfiguration(MakerInterface::class)
            ->addTag(FoundationCommandRegistrationPass::FOUNDATION_MAKER_TAG);
    }
}
