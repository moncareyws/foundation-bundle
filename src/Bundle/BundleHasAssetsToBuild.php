<?php

namespace MoncareyWS\FoundationBundle\Bundle;


/**
 * Interface BundleHasAssetsToBuild
 * @package MoncareyWS\FoundationBundle\Bundle
 */
interface BundleHasAssetsToBuild extends BundleHasNodeModules
{
    /**
     * @return array
     */
    public function getGulpSassPaths(): array;
}