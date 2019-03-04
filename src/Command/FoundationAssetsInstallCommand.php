<?php
/**
 * Created by PhpStorm.
 * User: samuel
 * Date: 06/02/19
 * Time: 16:18
 */

namespace MoncareyWS\FoundationBundle\Command;

use MoncareyWS\FoundationBundle\Bundle\BundleHasAssetsToMove;
use MoncareyWS\FoundationBundle\Bundle\BundleHasNodeModules;
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
    use CommandNeedsPublicDir;

    protected static $defaultName = 'foundation:assets:install';

    private $filesystem;

    public function __construct(Filesystem $filesystem)
    {
        parent::__construct($filesystem);

        $this->filesystem = $filesystem;
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setDefinition(array(
                new InputArgument('target', InputArgument::OPTIONAL, 'The target directory', null),
            ))
            ->setDescription('Installs bundles web assets under a public directory, runs \'npm install\' and moves some files to the public directory root')
            ->setHelp(file_get_contents(__DIR__.'/../Resources/help/FoundationAssetsInstall.txt'))
        ;
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {

        /** @var KernelInterface $kernel */
        $kernel = $this->getApplication()->getKernel();
        $targetArg = rtrim($input->getArgument('target'), '/');

        if (!$targetArg) {
            $targetArg = $this->getPublicDirectory($kernel->getContainer());
        }

        if (!is_dir($targetArg)) {
            $targetArg = "{$kernel->getProjectDir()}/{$targetArg}";

            if (!is_dir($targetArg)) {
                throw new InvalidArgumentException(sprintf('The target directory "%s" does not exist.', $input->getArgument('target')));
            }
        }

        $bundlesDir = $targetArg.'/bundles/';

        $io = new SymfonyStyle($input, $output);
        $io->newLine();
        $io->text('Installing assets as <info>hard copies</info>.');
        $io->newLine();

        $rows = array();
        $exitCode = 0;
        $validAssetDirs = array();
        /** @var BundleInterface $bundle */
        foreach ($kernel->getBundles() as $bundle) {
            if (!is_dir($originDir = $bundle->getPath().'/Resources/public')) continue;

            $assetDir = preg_replace('/bundle$/', '', strtolower($bundle->getName()));
            $targetDir = $bundlesDir.$assetDir;
            $relativeTargetDirPath = str_replace("{$kernel->getProjectDir()}/",'', $targetDir);
            $validAssetDirs[] = $assetDir;

            try {
                $this->filesystem->remove($targetDir);
                $this->hardCopy($originDir, $targetDir);

                $io->success("Copied assets from {$bundle->getName()} to {$relativeTargetDirPath}");

                if ($bundle instanceof BundleHasNodeModules) {
                    $io->newLine();
                    $io->text("Running 'npm install' on assets from {$bundle->getName()} ...");

                    if ($this->runNpmInstall($targetDir, $io)->isSuccessful())
                        $io->success("Node modules succesfully installed in {$relativeTargetDirPath}");
                }

                if ($bundle instanceof BundleHasAssetsToMove) {
                    $io->newLine();
                    $io->text("Moving assets from {$relativeTargetDirPath}");

                    $this->moveAssets($bundle->getFilesToMove($targetDir), $targetDir, $targetArg, $io);
                }

            } catch (\Exception $e) {
                $exitCode = 1;
                $io->error($e->getMessage());
            }
        }

        // remove the assets of the bundles that no longer exist
        if (is_dir($bundlesDir)) {
            $dirsToRemove = Finder::create()->depth(0)->directories()->exclude($validAssetDirs)->in($bundlesDir);
            $this->filesystem->remove($dirsToRemove);
        }

        if (0 !== $exitCode) {
            $io->error('Some errors occurred while installing assets.');
        } else {
            $io->success('All assets, if any, were successfully installed.');
        }

        return $exitCode;
    }

    /**
     * Copies origin to target.
     */
    private function hardCopy(string $originDir, string $targetDir)
    {
        $this->filesystem->mkdir($targetDir, 0777);
        // We use a custom iterator to ignore VCS files
        $this->filesystem->mirror($originDir, $targetDir, Finder::create()->ignoreDotFiles(false)->in($originDir));
    }

    private function runNpmInstall(string $cwd, SymfonyStyle $io): Process
    {
        $process = new Process(['npm','install'], $cwd);
        $process->setIdleTimeout(null);
        $process->setTimeout(null);
        $process->run(function ($type, $buffer) use ($io) {
            if ($io->getVerbosity() >= OutputInterface::VERBOSITY_VERBOSE)
                $io->write($buffer);
        });

        return $process;
    }

    private function moveAssets(array $assetsToMove, string $originDir, string $targetDir, SymfonyStyle $io)
    {
        foreach ($assetsToMove as $asset => $target) {
            $io->newLine();
            $io->text("Moving {$asset} ...");
            if (!file_exists($targetDir.$target)) {
                $this->filesystem->copy($originDir.$asset, $targetDir.$target);
                $io->text("Done");
            }
            else $io->note("{$target} already exists");
        }
    }
}