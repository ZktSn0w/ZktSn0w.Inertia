<?php

namespace ZktSn0w\Inertia;

use Psr\Http\Message\ServerRequestInterface;
use ZktSn0w\Inertia\Domain\Prop\Deferrable;

/**
 * Resolves raw controller props into final props + metadata.
 *
 * Handles: deferred props, closure evaluation, partial reload filtering,
 * shared data merging.
 *
 * Designed for extension — override the protected resolve*Prop() methods
 * to add custom prop types (e.g. once props, merge props).
 */
class PropsResolver
{
    /**
     * Keys of props that came from shared data.
     */
    private array $sharedPropKeys = [];

    /**
     * Deferred props grouped by defer group name.
     * ['default' => ['slowStats'], 'heavy' => ['heavyData']]
     */
    private array $deferredProps = [];

    public function __construct(
        private ServerRequestInterface $request,
        private array $rawProps,
    ) {
    }

    /**
     * Resolve all props: classify each prop, apply partial reload filtering,
     * merge shared props.
     *
     * @param array $sharedProps Shared props to merge (from SharedPropsService)
     * @param string $component Component name for partial reload check
     * @return array Final resolved props array
     */
    public function resolve(array $sharedProps, string $component): array
    {
        $this->sharedPropKeys = array_keys($sharedProps);

        $partialComponent = $this->request->getHeaderLine(App::PARTIAL_COMPONENT->value);
        $partialData = array_filter(explode(',', $this->request->getHeaderLine(App::PARTIAL_DATA->value)));
        $isPartial = $partialComponent === $component && $partialData !== [];
        $only = $isPartial ? $partialData : [];

        $resolved = [];
        foreach ($this->rawProps as $key => $prop) {
            if ($prop instanceof Deferrable && $prop->shouldDefer()) {
                $this->resolveDeferredProp($key, $prop, $isPartial, $only, $resolved);
            } elseif ($prop instanceof \Closure) {
                $this->resolveClosureProp($key, $prop, $isPartial, $only, $resolved);
            } else {
                $this->resolvePlainProp($key, $prop, $isPartial, $only, $resolved);
            }
        }

        return [...$resolved, ...$sharedProps];
    }

    /**
     * Handle a Deferrable prop.
     *
     * If the prop is in the partial-only list, evaluate and add to resolved.
     * Otherwise, group it into the deferred map for lazy loading.
     */
    protected function resolveDeferredProp(string $key, Deferrable $prop, bool $isPartial, array $only, array &$resolved): void
    {
        if ($isPartial && \in_array($key, $only, true)) {
            $resolved[$key] = $prop();
        } else {
            $this->deferredProps[$prop->group()][] = $key;
        }
    }

    /**
     * Handle a Closure prop.
     *
     * Closures are only evaluated when explicitly requested in a partial reload.
     * Otherwise they are dropped (not deferred — use DeferProp for deferral).
     */
    protected function resolveClosureProp(string $key, \Closure $prop, bool $isPartial, array $only, array &$resolved): void
    {
        if ($isPartial && \in_array($key, $only, true)) {
            $resolved[$key] = $prop();
        }
    }

    /**
     * Handle a plain (scalar/array/object) prop.
     *
     * Included unless filtered out by a partial reload that doesn't request it.
     */
    protected function resolvePlainProp(string $key, mixed $prop, bool $isPartial, array $only, array &$resolved): void
    {
        if (!$isPartial || \in_array($key, $only, true)) {
            $resolved[$key] = $prop;
        }
    }

    /**
     * Build the metadata array for the Inertia page response.
     *
     * Contains deferred props grouping, shared prop keys, and future metadata
     * like onceProps, mergeProps, etc.
     *
     * Empty entries are filtered out.
     */
    public function buildMetadata(): array
    {
        return array_filter([
            'sharedProps' => $this->sharedPropKeys,
            'deferredProps' => $this->deferredProps,
        ], fn($value) => \count($value) > 0);
    }
}
