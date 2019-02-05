<?php

/*
 * This file is part of the Symfony MakerBundle package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace MoncareyWS\FoundationBundle\Maker;

use Symfony\Bundle\MakerBundle\ConsoleStyle;
use Symfony\Bundle\MakerBundle\DependencyBuilder;
use Symfony\Bundle\MakerBundle\MakerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;

/**
 * Convenient abstract class for makers.
 */
abstract class AbstractFoundationMaker implements FoundationMakerInterface
{
    public function interact(InputInterface $input, ConsoleStyle $io, Command $command)
    {
    }

    protected function writeSuccessMessage(ConsoleStyle $io)
    {
        $io->newLine();
        $io->writeln(' <bg=green;fg=white>          </>');
        $io->writeln(' <bg=green;fg=white> Success! </>');
        $io->writeln(' <bg=green;fg=white>          </>');
        $io->newLine();
    }

    protected function addDependencies(array $dependencies, string $message = null): string
    {
        $dependencyBuilder = new DependencyBuilder();

        foreach ($dependencies as $class => $name) {
            $dependencyBuilder->addClassDependency($class, $name);
        }

        return $dependencyBuilder->getMissingPackagesMessage(
            $this->getCommandName(),
            $message
        );
    }

    protected function dump($var, ConsoleStyle $io)
    {
        ob_start();
        print_r($var);
        $dump = ob_get_clean();
        $io->text($dump);
    }
}
