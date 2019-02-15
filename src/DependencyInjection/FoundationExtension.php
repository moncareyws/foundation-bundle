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
        foreach ($container->getExtensionConfig('twig') as $twigConfig) {

            if (!isset($twigConfig['paths']))
                $twigConfig['paths'] = [];

            $twigConfig['paths'][__DIR__.'/../Resources/views'] = 'foundation';
            $twigConfig['paths'][__DIR__.'/../Resources/skeleton'] = 'foundation_skeleton';

            if (!isset($twigConfig['form_themes']))
                $twigConfig['form_themes'] = [];

            $twigConfig['form_themes'][] = '@foundation/form/fields.html.twig';

            if (!isset($twigConfig['globals']))
                $twigConfig['globals'] = [];

            if (!isset($twigConfig['globals']['js_files']))
                $twigConfig['globals']['js_files'] = [];

            $twigConfig['globals']['js_files']+= [
                '/bundles/foundation/node_modules/jquery/dist/jquery.js' => 0,
                '/bundles/foundation/node_modules/what-input/dist/what-input.js' => 1,
                '/bundles/foundation/node_modules/foundation-sites/dist/js/foundation.js' => 2,
                '/bundles/foundation/node_modules/perfect-scrollbar/dist/js/perfect-scrollbar.jquery.js' => 10,
                '/bundles/foundation/node_modules/@moncareyws/foundation-perfect-scrollbar/dist/js/foundation.perfectScrollbar.js' => 11,
                '/bundles/foundation/node_modules/@moncareyws/foundation-select/dist/js/foundation.select.js' => 12,
                '/js/app.js' => 80
            ];

            $container->loadFromExtension('twig', $twigConfig);
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
