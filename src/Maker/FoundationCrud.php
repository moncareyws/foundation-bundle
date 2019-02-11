<?php

namespace MoncareyWS\FoundationBundle\Maker;


use Symfony\Bundle\MakerBundle\ConsoleStyle;
use Symfony\Bundle\MakerBundle\DependencyBuilder;
use MoncareyWS\FoundationBundle\Generator\Generator;
use Symfony\Bundle\MakerBundle\InputConfiguration;
use MoncareyWS\FoundationBundle\Command\FoundationCommand as Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Bundle\MakerBundle\Doctrine\DoctrineHelper;
use MoncareyWS\FoundationBundle\Renderer\FormTypeRenderer;
use Symfony\Bundle\MakerBundle\Str;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Bundle\MakerBundle\Validator;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;
use Doctrine\Common\Inflector\Inflector;

class FoundationCrud extends AbstractFoundationMaker
{
    private $doctrineHelper;

    private $formTypeRenderer;

    public function __construct(DoctrineHelper $doctrineHelper, FormTypeRenderer $formTypeRenderer)
    {
        $this->doctrineHelper = $doctrineHelper;
        $this->formTypeRenderer = $formTypeRenderer;
    }
    /**
     * Return the command name for your maker (e.g. make:report).
     *
     * @return string
     */
    public static function getCommandName(): string
    {
        return 'foundation:make:crud';
    }

    /**
     * Configure the command: set description, input arguments, options, etc.
     *
     * By default, all arguments will be asked interactively. If you want
     * to avoid that, use the $inputConfig->setArgumentAsNonInteractive() method.
     *
     * @param Command $command
     * @param InputConfiguration $inputConfig
     */
    public function configureCommand(Command $command, InputConfiguration $inputConfig)
    {
        $command
            ->setDescription('Creates CRUD for Doctrine entity class')
            ->addArgument('entity', InputArgument::OPTIONAL, sprintf('The class name of the entity to create CRUD (e.g. <fg=yellow>%s</>)', Str::asClassName(Str::getRandomTerm())))
            ->addOption('format', 'f', InputOption::VALUE_REQUIRED, 'Configuration format (php, xml, yaml, or annotation)')
            ->addOption('route-prefix', 'r', InputOption::VALUE_REQUIRED, 'Route prefix')
            ->addOption('pluralize-index-route', 'p', InputOption::VALUE_NONE, 'Pluralize index route')
            ->addOption('entity-route-parameter', 'i', InputOption::VALUE_REQUIRED, 'Entity field to use as route parameter')
            ->setHelp(file_get_contents(__DIR__.'/../Resources/help/FoundationCrud.txt'))
        ;

        $inputConfig->setArgumentAsNonInteractive('entity');
    }

    public function interact(InputInterface $input, ConsoleStyle $io, Command $command)
    {

        // entity
        $entity = $input->getArgument('entity');

        if (null === $entity) {
            $entityArgument = $command->getDefinition()->getArgument('entity');

            $entities = $this->doctrineHelper->getEntitiesForAutocomplete();

            $entityQuestion = new Question($entityArgument->getDescription());
            $entityQuestion->setAutocompleterValues($entities);

            $entity = $io->askQuestion($entityQuestion);

            $input->setArgument('entity', $entity);
        }

        // format
        $format = $input->getOption('format');

        if (null === $format) {
            $formatOption = $command->getDefinition()->getOption('format');

            $formatQuestion = new Question($formatOption->getDescription(), 'annotation');
            $formatQuestion->setAutocompleterValues(['php','xml','yaml','annotation']);
            $formatQuestion->setValidator(array('MoncareyWS\FoundationBundle\Generator\Validator', 'validateFormat'));

            $format = $io->askQuestion($formatQuestion);

            $input->setOption('format', $format);
        }

        // route prefix
        $routePrefix = $input->getOption('route-prefix');

        if (null === $routePrefix) {
            $routePrefixOption = $command->getDefinition()->getOption('route-prefix');

            $routePrefixDefault = strtolower(str_replace(array('\\', '/'), '_', $entity));
            $routePrefixQuestion = new Question($routePrefixOption->getDescription(), $routePrefixDefault);
            $routePrefixQuestion->setValidator(array('MoncareyWS\FoundationBundle\Generator\Validator', 'validateRoutePrefix'));

            $routePrefix = $io->askQuestion($routePrefixQuestion);

            $input->setOption('route-prefix', $routePrefix);
        }

        // pluralize index route
        $pluralizeIndexRoute = $input->getOption('pluralize-index-route');

        if (!$pluralizeIndexRoute) {
            $pluralizeIndexRouteOption = $command->getDefinition()->getOption('pluralize-index-route');

            $pluralizeIndexRouteQuestion = new ConfirmationQuestion($pluralizeIndexRouteOption->getDescription(), 'yes');
            $pluralizeIndexRouteQuestion->setAutocompleterValues(['yes','no']);

            $pluralizeIndexRoute = $io->askQuestion($pluralizeIndexRouteQuestion);

            $input->setOption('pluralize-index-route', $pluralizeIndexRoute);
        }

        $entityRouteParameter = $input->getOption('entity-route-parameter');

        if (null === $entityRouteParameter) {
            $entityRouteParameterOption = $command->getDefinition()->getOption('entity-route-parameter');

            $entityClassDetails = $command->getGenerator()->createClassNameDetails($entity, 'Entity\\');
            $entityDoctrineDetails = $this->doctrineHelper->createDoctrineDetails($entityClassDetails->getFullName());
            $fields = array_keys($entityDoctrineDetails->getDisplayFields());

            $entityRouteParameterQuestion = new Question($entityRouteParameterOption->getDescription(), 'id');
            $entityRouteParameterQuestion->setAutocompleterValues($fields);

            $entityRouteParameter = $io->askQuestion($entityRouteParameterQuestion);

            $input->setOption('entity-route-parameter', $entityRouteParameter);
        }

    }

    /**
     * Configure any library dependencies that your maker requires.
     *
     * @param DependencyBuilder $dependencies
     */
    public function configureDependencies(DependencyBuilder $dependencies)
    {
        $dependencies->addClassDependency(
            Route::class,
            'router'
        );

        $dependencies->addClassDependency(
            AbstractType::class,
            'form'
        );

        $dependencies->addClassDependency(
            Validation::class,
            'validator'
        );

        $dependencies->addClassDependency(
            TwigBundle::class,
            'twig-bundle'
        );

        $dependencies->addClassDependency(
            DoctrineBundle::class,
            'orm-pack'
        );

        $dependencies->addClassDependency(
            CsrfTokenManager::class,
            'security-csrf'
        );

        $dependencies->addClassDependency(
            ParamConverter::class,
            'annotations'
        );
    }

    /**
     * Called after normal code generation: allows you to do anything.
     *
     * @param InputInterface $input
     * @param ConsoleStyle $io
     * @param Generator $generator
     */
    public function generate(InputInterface $input, ConsoleStyle $io, Generator $generator)
    {
        
        $entityClassDetails = $generator->createClassNameDetails(
            Validator::entityExists($input->getArgument('entity'), $this->doctrineHelper->getEntitiesForAutocomplete()),
            'Entity\\'
        );

        $entityDoctrineDetails = $this->doctrineHelper->createDoctrineDetails($entityClassDetails->getFullName());
        
        $repositoryVars = [];

        if (null !== $entityDoctrineDetails->getRepositoryClass()) {
            $repositoryClassDetails = $generator->createClassNameDetails(
                '\\'.$entityDoctrineDetails->getRepositoryClass(),
                'Repository\\',
                'Repository'
            );

            $repositoryVars = [
                'repository_full_class_name' => $repositoryClassDetails->getFullName(),
                'repository_class_name' => $repositoryClassDetails->getShortName(),
                'repository_var' => lcfirst(Inflector::singularize($repositoryClassDetails->getShortName())),
            ];
        }

        $controllerClassDetails = $generator->createClassNameDetails(
            $entityClassDetails->getRelativeNameWithoutSuffix().'Controller',
            'Controller\\',
            'Controller'
        );

        $iter = 0;
        do {
            $formClassDetails = $generator->createClassNameDetails(
                $entityClassDetails->getRelativeNameWithoutSuffix().($iter ?: '').'Type',
                'Form\\',
                'Type'
            );
            ++$iter;
        } while (class_exists($formClassDetails->getFullName()));

        $entityVarPlural = lcfirst(Inflector::pluralize($entityClassDetails->getShortName()));
        $entityVarSingular = lcfirst(Inflector::singularize($entityClassDetails->getShortName()));

        $entityTwigVarPlural = Str::asTwigVariable($entityVarPlural);
        $entityTwigVarSingular = Str::asTwigVariable($entityVarSingular);

        $routeNamePrefix = Str::asRouteName($controllerClassDetails->getRelativeNameWithoutSuffix());
        $routePathPrefix = $input->getOption('route-prefix');
        $indexRoutePath = $input->getOption('pluralize-index-route') ? Inflector::pluralize($routePathPrefix) : $routePathPrefix;

        $entityRouteParameter = $input->getOption('entity-route-parameter');

        $templatesPath = Str::asFilePath($controllerClassDetails->getRelativeNameWithoutSuffix());

        $configFormat = $input->getOption('format');

        $generator->generateController(
            $controllerClassDetails->getFullName(),
            'crud/controller.php.twig',
            array_merge([
                'class_name' => $controllerClassDetails->getShortName(),
                'entity_full_class_name' => $entityClassDetails->getFullName(),
                'entity_class_name' => $entityClassDetails->getShortName(),
                'entity' => $entityClassDetails->getShortName(),
                'form_full_class_name' => $formClassDetails->getFullName(),
                'form_class_name' => $formClassDetails->getShortName(),
                'route_path_prefix' => $routePathPrefix,
                'index_route' => $indexRoutePath,
                'route_name_prefix' => $routeNamePrefix,
                'templates_path' => $templatesPath,
                'entity_var_plural' => $entityVarPlural,
                'entity_twig_var_plural' => $entityTwigVarPlural,
                'entity_var_singular' => $entityVarSingular,
                'entity_twig_var_singular' => $entityTwigVarSingular,
                'entity_identifier' => $entityRouteParameter,
                'format' => $configFormat,
            ],
                $repositoryVars
            )
        );

        $this->formTypeRenderer->render(
            $formClassDetails,
            $entityDoctrineDetails->getFormFields(),
            $entityClassDetails
        );

        $templates = [
            'delete' => [
                'route_name' => $routeNamePrefix,
                'entity_twig_var_singular' => $entityTwigVarSingular,
                'entity_identifier' => $entityRouteParameter,
            ],
            'edit' => [
                'entity_class_name' => $entityClassDetails->getShortName(),
                'entity_twig_var_singular' => $entityTwigVarSingular,
                'entity_identifier' => $entityRouteParameter,
                'route_name' => $routeNamePrefix,
            ],
            'index' => [
                'entity_class_name' => $entityClassDetails->getShortName(),
                'entity_twig_var_plural' => $entityTwigVarPlural,
                'entity_twig_var_singular' => $entityTwigVarSingular,
                'entity_identifier' => $entityRouteParameter,
                'entity_fields' => $entityDoctrineDetails->getDisplayFields(),
                'route_name' => $routeNamePrefix,
                'templates_path' => $templatesPath,
            ],
            'create' => [
                'entity_class_name' => $entityClassDetails->getShortName(),
                'route_name' => $routeNamePrefix,
            ],
            'show' => [
                'entity_class_name' => $entityClassDetails->getShortName(),
                'entity_twig_var_singular' => $entityTwigVarSingular,
                'entity_identifier' => $entityRouteParameter,
                'entity_fields' => $entityDoctrineDetails->getDisplayFields(),
                'route_name' => $routeNamePrefix,
                'templates_path' => $templatesPath,
            ],

        ];

        foreach ($templates as $template => $variables) {
            $generator->generateFile(
                'templates/'.$templatesPath.'/'.$template.'.html.twig',
                'crud/views/'.$template.'.html.twig.twig',
                $variables
            );
        }

        $variables = [
            'entity_class_name' => $entityClassDetails->getShortName(),
            'entity_twig_var_singular' => $entityTwigVarSingular,
            'entity_identifier' => $entityRouteParameter,
            'entity_fields' => $entityDoctrineDetails->getDisplayFields(),
            'route_name' => $routeNamePrefix,
        ];
        $generator->generateFile(
            "templates/{$templatesPath}/{$entityTwigVarSingular}_view_mode/{$entityTwigVarSingular}_detail.html.twig",
            "crud/views/entity_view_mode/entity_detail.html.twig.twig",
            $variables
        );
        $generator->generateFile(
            "templates/{$templatesPath}/{$entityTwigVarSingular}_view_mode/{$entityTwigVarSingular}_teaser.html.twig",
            "crud/views/entity_view_mode/entity_teaser.html.twig.twig",
            $variables
        );

        if (in_array($configFormat, ['php','xml','yaml'])) {
            $generator->generateFile(
                sprintf("config/routes/%s.%s", strtolower($entityClassDetails->getShortName()), $configFormat),
                "crud/config/routing.{$configFormat}.twig",
                [
                    'route_name_prefix' => $routeNamePrefix,
                    'route_path_prefix' => $routePathPrefix,
                    'index_route' => $indexRoutePath,
                    'route_entity_parameter' => $entityRouteParameter,
                    'controller' => $controllerClassDetails->getFullName(),
                ]
            );
        }

        $generator->writeChanges();

        $this->writeSuccessMessage($io);

        $io->text(sprintf('Next: Check your new CRUD by going to <fg=yellow>%s/</>', Str::asRoutePath($controllerClassDetails->getRelativeNameWithoutSuffix())));
    }

    protected function getRoutePrefix(InputInterface $input, $entity)
    {
        $prefix = $input->getOption('route-prefix') ?: strtolower(str_replace(array('\\', '/'), '_', $entity));

        if ($prefix && '/' === $prefix[0]) {
            $prefix = substr($prefix, 1);
        }

        return $prefix;
    }
}