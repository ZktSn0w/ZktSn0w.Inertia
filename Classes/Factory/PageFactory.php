<?php

namespace ZktSn0w\Inertia\Factory;

use Neos\Flow\Annotations as Flow;
use Psr\Http\Message\ServerRequestInterface;
use ZktSn0w\Inertia\Domain\Page;
use ZktSn0w\Inertia\PropsResolver;
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
     * Create a fully resolved Page object from component name, raw props, and the current request.
     *
     * Raw props may contain DeferProp, Closure, or plain values.
     * The factory resolves them based on partial reload headers.
     */
    public function createPage(string $component, array $props, ServerRequestInterface $httpRequest): Page
    {
        $resolver = new PropsResolver($httpRequest, $props);
        $resolvedProps = $resolver->resolve($this->sharedPropsService->getProps(), $component);

        $page = new Page($component, $resolvedProps);

        $metadata = $resolver->buildMetadata();
        if (isset($metadata['deferredProps'])) {
            $page->setDeferredProps($metadata['deferredProps']);
        }

        $version = $this->assetVersionService->getAssetVersion();
        if ($version !== null) {
            $page->setVersion($version);
        }

        $page->setUrl((string) $httpRequest->getUri()->getPath());

        return $page;
    }

    /**
     * Create the view payload array from component name, raw props, and the current request.
     *
     * Returns ['inertiaPage' => Page] for direct use with view->assignMultiple().
     */
    public function create(string $component, array $props, ServerRequestInterface $httpRequest): array
    {
        return ['inertiaPage' => $this->createPage($component, $props, $httpRequest)];
    }

}
