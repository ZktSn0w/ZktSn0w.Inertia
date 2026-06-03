<?php

namespace ZktSn0w\Inertia\Domain;


class Page implements \JsonSerializable
{
    private string $component;
    private array $props;
    private ?string $version = null;
    private ?string $url = null;

    public static function create(string $component, array $props): self
    {
        return new self($component, $props);
    }

    public function __construct(string $component, array $props)
    {
        $this->component = $component;
        $this->props = $props;
    }

    public function setVersion(string $version): void
    {
        $this->version = $version;
    }

    public function setUrl(string $url): void
    {
        $this->url = $url;
    }

    public function jsonSerialize(): array
    {
        $data = [
            'component' => $this->component,
            'props' => $this->props,
        ];

        if ($this->version !== null) {
            $data['version'] = $this->version;
        }

        if ($this->url !== null) {
            $data['url'] = $this->url;
        }

        return $data;
    }
}
