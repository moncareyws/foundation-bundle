<?php

namespace MoncareyWS\FoundationBundle\DependencyInjection;

use MoncareyWS\FoundationBundle\DependencyInjection\CompilerPass\FoundationCommandRegistrationPass;
use MoncareyWS\FoundationBundle\Maker\FoundationMakerInterface;
use Symfony\Bundle\TwigBundle\DependencyInjection\TwigExtension;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;

/**
 * This is the class that loads and manages your bundle configuration.
 *
 * @see http://symfony.com/doc/current/cookbook/bundles/extension.html
 */
class FoundationExtension extends Extension implements PrependExtensionInterface
{

    public function prepend(ContainerBuilder $container)
    {
        foreach ($container->getExtensionConfig('twig') as $config) {
            if (!isset($config['paths'])) $config['paths'] = [];
            $config['paths'][__DIR__.'/../Resources/skeleton'] = 'foundation_skeleton';
            $container->loadFromExtension('twig', $config);
        }
    }


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

        $container->registerForAutoconfiguration(FoundationMakerInterface::class)
            ->addTag(FoundationCommandRegistrationPass::FOUNDATION_MAKER_TAG);
    }
}
