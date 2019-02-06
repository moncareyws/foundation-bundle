<?php
/**
 * Created by PhpStorm.
 * User: samuel
 * Date: 06/02/19
 * Time: 16:18
 */

namespace MoncareyWS\FoundationBundle\Command;

use MoncareyWS\FoundationBundle\FoundationBundle;
use Symfony\Bundle\FrameworkBundle\Command\AssetsInstallCommand;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Process\Process;

class FoundationAssetsInstallCommand extends AssetsInstallCommand
{

    protected static $defaultName = 'foundation:assets:install';

    private $filesystem;

    public function __construct(Filesystem $filesystem)
    {
        parent::__construct($filesystem);

        $this->filesystem = $filesystem;
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $exitCode = parent::execute($input, $output);

        /** @var KernelInterface $kernel */
        $kernel = $this->getApplication()->getKernel();
        $targetDir = rtrim($input->getArgument('target'), '/');

        if (!$targetDir) {
            $targetDir = $this->getPublicDirectory($kernel->getContainer());
        }

        if (!is_dir($targetDir)) {
            $targetDir = $kernel->getProjectDir().'/'.$targetDir;

            if (!is_dir($targetDir)) {
                throw new InvalidArgumentException(sprintf('The target directory "%s" does not exist.', $input->getArgument('target')));
            }
        }

        $bundlesDir = $targetDir.'/bundles/';

        $io = new SymfonyStyle($input, $output);
        $io->newLine();

        try {
            $originDir = null;

            foreach ($kernel->getBundles() as $bundle) {
                if (!($bundle instanceof FoundationBundle)) continue;

                $assetDir = preg_replace('/bundle$/', '', strtolower($bundle->getName()));
                $originDir = $bundlesDir . $assetDir;
            }

            if (null === $originDir) throw new IOException('Foundation assets have not been installed.');

            $process = new Process(['npm','install'], $originDir);
            $process->disableOutput();
            $process->start();

            $files = [
                '/js/app.js',
                '/scss/app.scss',
                '/scss/_settings.scss'
            ];

            foreach ($files as $file) {
                if (!file_exists($targetDir.$file)) {
                    $this->filesystem->copy($originDir.$file, $targetDir.$file);
                    $this->filesystem->remove($originDir.$file);
                }
            }

            $process->wait();

        } catch (IOException $e) {
            $exitCode = 1;
            $io->error($e->getMessage());
        }

        return $exitCode;
    }

    private function getPublicDirectory(ContainerInterface $container)
    {
        $defaultPublicDir = 'public';

        if (!$container->hasParameter('kernel.project_dir')) {
            return $defaultPublicDir;
        }

        $composerFilePath = $container->getParameter('kernel.project_dir').'/composer.json';

        if (!file_exists($composerFilePath)) {
            return $defaultPublicDir;
        }

        $composerConfig = json_decode(file_get_contents($composerFilePath), true);

        if (isset($composerConfig['extra']['public-dir'])) {
            return $composerConfig['extra']['public-dir'];
        }

        return $defaultPublicDir;
    }
}