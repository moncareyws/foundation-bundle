<?php
/**
 * Created by PhpStorm.
 * User: samuel
 * Date: 29/01/19
 * Time: 14:35
 */

namespace MoncareyWS\FoundationBundle\Maker;


use Symfony\Component\Console\Input\InputInterface;
use Symfony\Bundle\MakerBundle\ConsoleStyle;
use MoncareyWS\FoundationBundle\Command\FoundationCommand as Command;
use Symfony\Bundle\MakerBundle\InputConfiguration;
use Symfony\Bundle\MakerBundle\DependencyBuilder;
use MoncareyWS\FoundationBundle\Generator\Generator;

interface FoundationMakerInterface
{
    /**
     * Return the command name for your maker (e.g. make:report).
     *
     * @return string
     */
    public static function getCommandName(): string;

    /**
     * Configure the command: set description, input arguments, options, etc.
     *
     * By default, all arguments will be asked interactively. If you want
     * to avoid that, use the $inputConfig->setArgumentAsNonInteractive() method.
     *
     * @param Command            $command
     * @param InputConfiguration $inputConfig
     */
    public function configureCommand(Command $command, InputConfiguration $inputConfig);

    /**
     * Configure any library dependencies that your maker requires.
     *
     * @param DependencyBuilder $dependencies
     */
    public function configureDependencies(DependencyBuilder $dependencies);

    /**
     * If necessary, you can use this method to interactively ask the user for input.
     *
     * @param InputInterface $input
     * @param ConsoleStyle   $io
     * @param Command        $command
     */
    public function interact(InputInterface $input, ConsoleStyle $io, Command $command);

    /**
     * Called after normal code generation: allows you to do anything.
     *
     * @param InputInterface $input
     * @param ConsoleStyle   $io
     * @param Generator      $generator
     */
    public function generate(InputInterface $input, ConsoleStyle $io, Generator $generator);
}