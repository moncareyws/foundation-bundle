<?php

/*
 * This file is part of the Symfony MakerBundle package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace MoncareyWS\FoundationBundle\Command;

use MoncareyWS\FoundationBundle\Maker\FoundationMakerInterface;
use Symfony\Bundle\MakerBundle\ApplicationAwareMakerInterface;
use Symfony\Bundle\MakerBundle\ConsoleStyle;
use MoncareyWS\FoundationBundle\Generator\FileManager;
use MoncareyWS\FoundationBundle\Generator\Generator;
use Symfony\Bundle\MakerBundle\InputConfiguration;
use Symfony\Bundle\MakerBundle\Validator;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Used as the Command class for the makers.
 *
 * @internal
 */
final class FoundationCommand extends Command
{
    private $maker;
    private $fileManager;
    private $inputConfig;
    /** @var ConsoleStyle */
    private $io;
    private $generator;

    public function __construct(FoundationMakerInterface $maker, FileManager $fileManager, Generator $generator)
    {
        $this->maker = $maker;
        $this->fileManager = $fileManager;
        $this->inputConfig = new InputConfiguration();
        $this->generator = $generator;

        parent::__construct();
    }

    protected function configure()
    {
        $this->maker->configureCommand($this, $this->inputConfig);
    }

    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        $this->io = new ConsoleStyle($input, $output);
        $this->fileManager->setIO($this->io);
    }

    protected function interact(InputInterface $input, OutputInterface $output)
    {
        if (!$this->fileManager->isNamespaceConfiguredToAutoload($this->generator->getRootNamespace())) {
            $this->io->note([
                sprintf('It looks like your app may be using a namespace other than "%s".', $this->generator->getRootNamespace()),
                'To configure this and make your life easier, see: https://symfony.com/doc/current/bundles/SymfonyMakerBundle/index.html#configuration',
            ]);
        }

        foreach ($this->getDefinition()->getArguments() as $argument) {
            if ($input->getArgument($argument->getName())) {
                continue;
            }

            if (\in_array($argument->getName(), $this->inputConfig->getNonInteractiveArguments(), true)) {
                continue;
            }

            $value = $this->io->ask($argument->getDescription(), $argument->getDefault(), [Validator::class, 'notBlank']);
            $input->setArgument($argument->getName(), $value);
        }

        $this->maker->interact($input, $this->io, $this);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->maker->generate($input, $this->io, $this->generator);

        // sanity check for custom makers
        if ($this->generator->hasPendingOperations()) {
            throw new \LogicException('Make sure to call the writeChanges() method on the generator.');
        }
    }

    public function setApplication(Application $application = null)
    {
        parent::setApplication($application);

        if ($this->maker instanceof ApplicationAwareMakerInterface) {
            if (null === $application) {
                throw new \RuntimeException('Application cannot be null.');
            }

            $this->maker->setApplication($application);
        }
    }

    public function getGenerator(): Generator
    {
        return $this->generator;
    }
}
