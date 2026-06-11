<?php

namespace ZktSn0w\Inertia\Trait;

use GuzzleHttp\Psr7\Response;
use Neos\Flow\Mvc\ActionRequest;
use Psr\Http\Message\ResponseInterface;
use ZktSn0w\Inertia\App;
use ZktSn0w\Inertia\Factory\PageFactory;
use ZktSn0w\Inertia\Service\SharedPropsService;

/**
 * View-agnostic Inertia trait for controllers.
 *
 * Works with Fusion, Fluid, or any view engine.
 *
 *   return $this->inertia('Dashboard', ['stats' => [...]]);
 */
trait Inertia
{
    private PageFactory $pageFactory;
    private SharedPropsService $sharedPropsService;

    public function injectPageFactory(PageFactory $pageFactory): void
    {
        $this->pageFactory = $pageFactory;
    }

    public function injectSharedPropsService(SharedPropsService $sharedPropsService): void
    {
        $this->sharedPropsService = $sharedPropsService;
    }

    /**
     * Share props across all Inertia responses (e.g., from middleware or controller).
     */
    protected function share(array $properties): void
    {
        $this->sharedPropsService->share($properties);
    }

    /**
     * Return a 409 with X-Inertia-Location header for external redirects.
     */
    protected function location(string $url): Response
    {
        return new Response(409, [App::INERTIA_LOCATION_HEADER->value => $url]);
    }

    /**
     * Render an Inertia response.
     *
     * If the request has X-Inertia header: returns JSON response (view skipped).
     * Otherwise: assigns the page to the view and returns null (view renders).
     *
     * @return ResponseInterface|null  JSON response for Inertia requests, null otherwise
     */
    protected function inertia(string $component, array $props = []): ?ResponseInterface
    {
        if (!($this->request instanceof ActionRequest)) {
            throw new \RuntimeException(sprintf(
                'Request object is not a Neos ActionRequest. Did you use the %s trait outside of an ActionController?',
                __TRAIT__
            ));
        }

        $httpRequest = $this->request->getHttpRequest();
        $page = $this->pageFactory->create($component, $props, $httpRequest);

        if ($httpRequest->hasHeader(App::HEADER->value)) {
            $version = $page->jsonSerialize()['version'] ?? null;
            $headers = [
                'Vary' => App::HEADER->value,
                'Content-Type' => 'application/json',
            ];
            if ($version !== null) {
                $headers[App::VERSION_HEADER->value] = $version;
            }

            return new Response(200, $headers, json_encode($page));
        }

        $this->view->assign('inertiaPage', $page);

        return null;
    }
}
