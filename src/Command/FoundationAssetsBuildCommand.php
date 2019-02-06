<?php
/**
 * Created by PhpStorm.
 * User: samuel
 * Date: 2/6/19
 * Time: 9:41 PM
 */

namespace MoncareyWS\FoundationBundle\Command;


use MoncareyWS\FoundationBundle\FoundationBundle;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\Process;

class FoundationAssetsBuildCommand extends Command
{
    use CommandNeedsPublicDir;

    protected static $defaultName = 'foundation:assets:build';

    protected function execute(InputInterface $input, OutputInterface $output)
    {

        $exitCode = 0;
        /** @var KernelInterface $kernel */
        $kernel = $this->getApplication()->getKernel();
        $targetDir = $this->getPublicDirectory($kernel->getContainer());

        if (!is_dir($targetDir)) {
            $targetDir = $kernel->getProjectDir().'/'.$targetDir;

            if (!is_dir($targetDir)) {
                throw new InvalidArgumentException(sprintf('The target directory "%s" does not exist.', $input->getArgument('target')));
            }
        }

        $bundlesDir = $targetDir.'/bundles/';
        $cwd = null;

        $io = new SymfonyStyle($input, $output);
        $io->newLine();

        try {
            foreach ($kernel->getBundles() as $bundle) {
                if (!($bundle instanceof FoundationBundle)) continue;

                $assetDir = preg_replace('/bundle$/', '', strtolower($bundle->getName()));
                $cwd = $bundlesDir . $assetDir;
            }

            if (null === $cwd) throw new IOException('Foundation assets are not installed. Run \'foundation:assets:install\' and run this command again.');

            $io->text('Starting gulp');
            $io->newLine();

            $build = new Process(['gulp'], $cwd);
            $build->setTimeout(null);
            $build->setIdleTimeout(null);

            $build->run(function ($type, $buffer) use ($io) {
                if (Process::ERR === $type) {
                    $io->error($buffer);
                } else {
                    $io->text($buffer);
                }
            });

        } catch (\Exception $e) {
            $exitCode = 1;
            $io->error($e->getMessage());
        }

        return $exitCode;
    }

}