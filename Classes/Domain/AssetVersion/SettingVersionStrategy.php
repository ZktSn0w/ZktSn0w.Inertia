<?php

namespace ZktSn0w\Inertia\Domain\AssetVersion;

use Neos\Flow\Annotations as Flow;
use ZktSn0w\Inertia\Domain\AssetVersion\StrategyInterface;

class SettingVersionStrategy implements StrategyInterface
{

    #[Flow\InjectConfiguration(path: "assetVersioning.strategy.options", package: "ZktSn0w.Inertia")]
    protected array $options;

    public function getVersion(): string
    {
        if (empty($this->options) || !isset($this->options['value']) || empty($this->options['value'])) {
            return null;
        }

        return $this->options['value'];
    }
}
