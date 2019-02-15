<?php

namespace MoncareyWS\FoundationBundle;

use MoncareyWS\FoundationBundle\Bundle\BundleHasAssetsToBuild;
use MoncareyWS\FoundationBundle\Bundle\BundleHasAssetsToMove;
use MoncareyWS\FoundationBundle\DependencyInjection\CompilerPass\FoundationCommandRegistrationPass;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;


class FoundationBundle extends Bundle implements BundleHasAssetsToBuild, BundleHasAssetsToMove
{

    public function build(ContainerBuilder $container)
    {
        // add a priority so we run before the core command pass
        $container->addCompilerPass(new FoundationCommandRegistrationPass(), PassConfig::TYPE_BEFORE_OPTIMIZATION, 10);
    }

    public function getGulpSassPaths(): array
    {
        return [
            'node_modules/foundation-sites/scss',
            'node_modules/motion-ui/src',
            'node_modules/@fortawesome/fontawesome-free/scss',
            'node_modules/@moncareyws/foundation-perfect-scrollbar/src/scss/plugin',
            'node_modules/@moncareyws/foundation-select/src/scss/plugin',
        ];
    }

    public function getFilesToMove(string $bundleAsstesDir): array
    {
        $files = [
            '/js/app.js' => '/js/app.js',
            '/scss/app.scss' => '/scss/app.scss',
            '/scss/_settings.scss' => '/scss/_settings.scss',
            '/scss/_fonts.scss' => '/scss/_fonts.scss'
        ];

        $fontawesomeWebfontsPath = "/node_modules/@fortawesome/fontawesome-free/webfonts";

        if (is_dir($bundleAsstesDir.$fontawesomeWebfontsPath)) {
            $webfontsDir = opendir($bundleAsstesDir.$fontawesomeWebfontsPath);
            while (false !== ($entry = readdir($webfontsDir))) {
                if (!in_array($entry, ['.','..'])) {
                    $files["{$fontawesomeWebfontsPath}/{$entry}"] = "/fonts/fontawesome/{$entry}";
                }
            }
        }

        return $files;
    }

}
