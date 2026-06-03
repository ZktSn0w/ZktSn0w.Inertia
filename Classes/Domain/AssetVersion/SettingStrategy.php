<?php

namespace ZktSn0w\Inertia\Domain\AssetVersion;

class SettingStrategy implements StrategyInterface
{
    public function __construct(private readonly array $options) {}

    public function getVersion(): ?string
    {
        return !empty($this->options['value']) ? (string) $this->options['value'] : null;
    }
}
