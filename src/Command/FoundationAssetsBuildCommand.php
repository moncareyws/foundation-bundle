<?php
/**
 * Created by PhpStorm.
 * User: samuel
 * Date: 2/6/19
 * Time: 9:41 PM
 */

namespace MoncareyWS\FoundationBundle\Command;


use MoncareyWS\FoundationBundle\Bundle\BundleHasAssetsToBuild;
use MoncareyWS\FoundationBundle\FoundationBundle;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
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

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setDefinition(array(
                new InputArgument('target', InputArgument::OPTIONAL, 'The target directory', null),
            ))
            ->addOption('watch', null, InputOption::VALUE_NONE, 'Watch for changes', null)
            ->setDescription('Builds the assets from the foundation bundle')
            ->setHelp(file_get_contents(__DIR__.'/../Resources/help/FoundationAssetsBuild.txt'))
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {

        $exitCode = 0;
        /** @var KernelInterface $kernel */
        $kernel = $this->getApplication()->getKernel();
        $targetArg = rtrim($input->getArgument('target'), '/');

        if (!$targetArg) {
            $targetArg = $this->getPublicDirectory($kernel->getContainer());
        }

        if (!is_dir($targetArg)) {
            $targetArg = $kernel->getProjectDir().'/'.$targetArg;

            if (!is_dir($targetArg)) {
                throw new InvalidArgumentException(sprintf('The target directory "%s" does not exist.', $input->getArgument('target')));
            }
        }

        $watchOpt = $input->getOption('watch');

        $bundlesDir = $targetArg.'/bundles/';
        $cwd = null;

        $gulpTask = $watchOpt ? 'default' : 'sass';

        $io = new SymfonyStyle($input, $output);
        $io->newLine();

        try {

            $gulpSassPaths = [];

            foreach ($kernel->getBundles() as $bundle) {
                $assetDir = preg_replace('/bundle$/', '', strtolower($bundle->getName()));

                if ($bundle instanceof FoundationBundle)
                    $cwd = $bundlesDir.$assetDir;

                if ($bundle instanceof BundleHasAssetsToBuild) {
                    $prefix = '';
                    if (!($bundle instanceof FoundationBundle))
                        $prefix = "../../bundles/{$assetDir}/";

                    $gulpSassPathsTmp = $bundle->getGulpSassPaths();
                    array_walk($gulpSassPathsTmp, function (&$item, $key, $prefix) {
                        $item = $prefix.$item;
                    }, $prefix);

                    $gulpSassPaths = array_merge($gulpSassPaths, $gulpSassPathsTmp);
                }
            }

            if (null === $cwd)
                throw new IOException('Foundation assets are not installed. Run \'foundation:assets:install\' and run this command again.');

            $gulpSassPathsJsonFile = "{$cwd}/gulp_sass_paths.json";
            file_put_contents($gulpSassPathsJsonFile, json_encode($gulpSassPaths));

            $io->text("Starting gulp {$gulpTask}");
            $io->newLine();

            $build = new Process(['gulp', $gulpTask], $cwd);
            $build->setTimeout(null);
            $build->setIdleTimeout(null);

            $build->run(function ($type, $buffer) use ($io) {
                if (Process::ERR === $type) {
                    $io->error($buffer);
                } else {
                    $io->write($buffer);
                }
            });

        } catch (\Exception $e) {
            $exitCode = 1;
            $io->error($e->getMessage());
        }

        return $exitCode;
    }

}