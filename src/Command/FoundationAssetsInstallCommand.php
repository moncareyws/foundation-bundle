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
        parent::configure();
        $this
            ->setDescription('Installs bundles web assets under a public directory, runs \'npm install\' and moves some files to the public directory root')
            ->setHelp(file_get_contents(__DIR__.'/../Resources/help/FoundationAssetsInstall.txt'))
        ;
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

            $io->text('Running \'npm install\' on assets from the foundation bundle.');
            $io->newLine();

            $process = new Process(['npm','install'], $originDir);
            $process->setIdleTimeout(null);
            $process->setTimeout(null);
            $process->run(function ($type, $buffer) use ($io) {$io->writeln($buffer);});

            $files = [
                '/js/app.js' => '/js/app.js',
                '/scss/app.scss' => '/scss/app.scss',
                '/scss/_settings.scss' => '/scss/_settings.scss',
                '/scss/_fonts.scss' => '/scss/_fonts.scss'
            ];

            $fontawesomeWebfontsPath = "/node_modules/@fortawesome/fontawesome-free/webfonts";

            if (is_dir($kernel->getProjectDir().'/'.$originDir.$fontawesomeWebfontsPath)) {
                $webfontsDir = opendir($kernel->getProjectDir().'/'.$originDir.$fontawesomeWebfontsPath);
                while (false !== ($entry = readdir($webfontsDir))) {
                    $io->note($entry);
                    if (!in_array($entry, ['.','..'])) {
                        $files["{$fontawesomeWebfontsPath}/{$entry}"] = "/fonts/fontawesome/{$entry}";
                    }
                }
            }
            else {
                $io->error($kernel->getProjectDir().'/'."{$originDir}{$fontawesomeWebfontsPath} not found!");
            }

            $io->text('Moving assets from foundation bundle ...');

            foreach ($files as $file => $target) {
                $io->text("Copying {$file} ...");
                if (!file_exists($targetDir.$file)) {
                    $this->filesystem->copy($originDir.$file, $targetDir.$target);
                    $io->text("{$file} copied.");
                }
                else $io->text("{$file} already exists, skipping ...");
            }

        } catch (\Exception $e) {
            $exitCode = 1;
            $io->error($e->getMessage());
        }

        return $exitCode;
    }
}