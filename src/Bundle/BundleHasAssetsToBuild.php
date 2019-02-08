<?php
/**
 * Created by PhpStorm.
 * User: samuel
 * Date: 07/02/19
 * Time: 23:55
 */

namespace MoncareyWS\FoundationBundle\Bundle;


interface BundleHasAssetsToBuild extends BundleHasNodeModules
{
    public function getGulpSassPaths(): array;
}