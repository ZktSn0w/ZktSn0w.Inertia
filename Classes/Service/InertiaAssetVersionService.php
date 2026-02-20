<?php
namespace ZktSn0w\Inertia\Service;

use Exception;
use Neos\Flow\Annotations as Flow;
use ZktSn0w\Inertia\Domain\AssetVersion\StrategyInterface;

#[Flow\Scope('singleton')]
class InertiaAssetVersionService
{

    #[Flow\InjectConfiguration(path: "assetVersioning.strategy", package: "ZktSn0w.Inertia")]
    protected $assetVersioningStrategy;


    public function getAssetVersion(): ?string
    {
        /**
         * No version is valid too.
         */
        if (!isset($this->assetVersioningStrategy) || empty($this->assetVersioningStrategy)) {
            return null;
        }

        /**
         * @var StrategyInterface
         */
        $versionStrategy = new $this->assetVersioningStrategy['class']($this->assetVersioningStrategy['options'] ?: []);

        if(!($versionStrategy instanceof StrategyInterface)) {
            throw new Exception('Version Strategy does not implement the StrategyInterface');
        }

        return $versionStrategy->getVersion();
    }
}
