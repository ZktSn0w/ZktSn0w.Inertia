<?php

namespace ZktSn0w\Inertia\Domain;

class Page implements \JsonSerializable
{
    private string $component;
    private array $props;
    private ?string $version = null;
    private ?string $url = null;
    private array $deferredProps = [];
    private array $errors = [];

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

    public function setDeferredProps(array $deferedProps): void
    {
        $this->deferredProps = $deferedProps;
    }

    /**
     * Set validation or error-bag data (Inertia error protocol).
     *
     * Keys map to form field names; values are arrays of error messages.
     * An empty array serializes to {} (empty JSON object) to match
     * what the Inertia client expects.
     */
    public function setErrors(array $errors): void
    {
        $this->errors = $errors;
    }

    public function jsonSerialize(): array
    {
        $data = [
            'component' => $this->component,
            'props' => $this->props,
            'errors' => $this->errors !== [] ? $this->errors : new \stdClass(),
        ];

        if ($this->version !== null) {
            $data['version'] = $this->version;
        }

        if ($this->url !== null) {
            $data['url'] = $this->url;
        }

        if ($this->deferredProps !== []) {
            $data['deferredProps'] = $this->deferredProps;
        }

        return $data;
    }
}
