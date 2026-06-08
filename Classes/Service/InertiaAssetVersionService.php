<?php
namespace ZktSn0w\Inertia\Service;

use Neos\Flow\Annotations as Flow;
use ZktSn0w\Inertia\Domain\AssetVersion\StrategyInterface;

#[Flow\Scope('singleton')]
class InertiaAssetVersionService
{
    #[Flow\InjectConfiguration(path: "assetVersioning.strategy", package: "ZktSn0w.Inertia")]
    protected array $assetVersioningStrategy;

    private ?StrategyInterface $strategy = null;

    public function getAssetVersion(): ?string
    {
        if (empty($this->assetVersioningStrategy['class'])) {
            return null;
        }

        if($this->strategy === null) {
            $class = $this->assetVersioningStrategy['class'];
            $options = $this->assetVersioningStrategy['options'] ?? [];

            $strategy = new $class($options);

            if (!($strategy instanceof StrategyInterface)) {
                throw new \InvalidArgumentException(sprintf('"%s" does not implement StrategyInterface', $class));
            } else {
                $this->strategy = $strategy;
            }
        }

        return $this->strategy->getVersion();
    }
}
