<?php
/**
 * Created by PhpStorm.
 * User: samuel
 * Date: 08/02/19
 * Time: 04:58
 */

namespace MoncareyWS\FoundationBundle\Maker;


use MoncareyWS\FoundationBundle\Generator\Generator;
use Symfony\Bundle\MakerBundle\ConsoleStyle;
use Symfony\Bundle\MakerBundle\DependencyBuilder;
use Symfony\Bundle\MakerBundle\InputConfiguration;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Filesystem\Filesystem;

class FoundationTemplatingStructure extends AbstractFoundationMaker
{

    /** @var Filesystem */
    private $filesystem;

    private $rootDirectory;

    /**
     * PHP 5 allows developers to declare constructor methods for classes.
     * Classes which have a constructor method call this method on each newly-created object,
     * so it is suitable for any initialization that the object may need before it is used.
     *
     * Note: Parent constructors are not called implicitly if the child class defines a constructor.
     * In order to run a parent constructor, a call to parent::__construct() within the child constructor is required.
     *
     * param [ mixed $args [, $... ]]
     * @link https://php.net/manual/en/language.oop5.decon.php
     */
    public function __construct(Filesystem $filesystem, $rootDirectory)
    {
        $this->filesystem = $filesystem;
        $this->rootDirectory = $rootDirectory;
    }

    /**
     * Return the command name for your maker (e.g. make:report).
     *
     * @return string
     */
    public static function getCommandName(): string
    {
        return 'foundation:make:templating-structure';
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
            ->setDescription('Creates a basic templating structure')
            ->setHelp(file_get_contents(__DIR__.'/../Resources/help/FoundationTemplatingStructure.txt'))
        ;
    }

    /**
     * Configure any library dependencies that your maker requires.
     *
     * @param DependencyBuilder $dependencies
     */
    public function configureDependencies(DependencyBuilder $dependencies)
    {
        $dependencies->addClassDependency(
            TwigBundle::class,
            'twig-bundle'
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
        $skeletonDir = realpath(__DIR__.'../Resources/skeleton/templating_structure');
        $templatesDir = "{$this->rootDirectory}/templates";

        $this->filesystem->mirror($skeletonDir, $templatesDir);
    }

}