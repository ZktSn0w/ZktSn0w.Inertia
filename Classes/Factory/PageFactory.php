<?php

namespace ZktSn0w\Inertia\Factory;

use Neos\Flow\Annotations as Flow;
use Psr\Http\Message\ServerRequestInterface;
use ZktSn0w\Inertia\App;
use ZktSn0w\Inertia\Domain\Page;
use ZktSn0w\Inertia\Domain\Prop\Deferrable;
use ZktSn0w\Inertia\Service\InertiaAssetVersionService;
use ZktSn0w\Inertia\Service\SharedPropsService;

/**
 * Creates fully resolved Page objects from raw controller props.
 *
 * Handles: deferred props, closure evaluation, partial reload filtering,
 * shared data merging, asset version, URL.
 *
 * View-agnostic — works with Fusion, Fluid, any view.
 */
class PageFactory
{
    #[Flow\Inject]
    protected SharedPropsService $sharedPropsService;

    #[Flow\Inject]
    protected InertiaAssetVersionService $assetVersionService;

    /**
     * Create a Page from component name, raw props, and the current request.
     *
     * Raw props may contain DeferProp, Closure, or plain values.
     * The factory resolves them based on partial reload headers.
     */
    public function create(string $component, array $props, ServerRequestInterface $httpRequest): Page
    {
        $partialComponent = $httpRequest->getHeaderLine(App::PARTIAL_COMPONENT->value);
        $partialData = array_filter(explode(',', $httpRequest->getHeaderLine(App::PARTIAL_DATA->value)));
        $isPartialReload = $partialComponent === $component && $partialData !== [];

        [$resolvedProps, $deferredMap] = $this->resolveProps($props, $isPartialReload ? $partialData : []);

        $page = new Page($component, $resolvedProps);

        if ($deferredMap !== []) {
            $page->setDeferredProps($deferredMap);
        }

        $version = $this->assetVersionService->getAssetVersion();
        if ($version !== null) {
            $page->setVersion($version);
        }

        $page->setUrl((string) $httpRequest->getUri()->getPath());

        return $page;
    }

    /**
     * Resolve props: evaluate closures, handle deferred props,
     * filter for partial reloads, merge shared data.
     *
     * @return array{0: array, 1: array} [resolvedProps, deferredMap]
     */
    private function resolveProps(array $props, array $partialData): array
    {
        $resolvedProps = [];
        $deferredMap = [];
        $isPartial = $partialData !== [];

        foreach ($props as $key => $prop) {
            if ($prop instanceof Deferrable && $prop->shouldDefer()) {
                if ($isPartial && in_array($key, $partialData, true)) {
                    $resolvedProps[$key] = $prop();
                } else {
                    $deferredMap[$prop->group()][] = $key;
                }
            } elseif ($prop instanceof \Closure) {
                if ($isPartial && in_array($key, $partialData, true)) {
                    $resolvedProps[$key] = $prop();
                }
            } else {
                if (!$isPartial || in_array($key, $partialData, true)) {
                    $resolvedProps[$key] = $prop;
                }
            }
        }

        $resolvedProps = array_merge($resolvedProps, $this->sharedPropsService->getProps());

        return [$resolvedProps, $deferredMap];
    }
}
