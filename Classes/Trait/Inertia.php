<?php

namespace ZktSn0w\Inertia\Trait;

use GuzzleHttp\Psr7\Response;
use Neos\Flow\Mvc\ActionRequest;
use Psr\Http\Message\ResponseInterface;
use ZktSn0w\Inertia\App;
use ZktSn0w\Inertia\Factory\PageFactory;
use ZktSn0w\Inertia\Service\SharedPropsService;
use Neos\Flow\Mvc\View\ViewInterface;
/**
 * View-agnostic Inertia trait for controllers.
 *
 * Works with Fusion, Fluid, or any view engine.
 *
 *   return $this->inertia('Dashboard', ['stats' => [...]]);
 */
trait Inertia
{
    /**
     * @var ViewInterface
     */
    protected $view = null;

    /**
     * @var ActionRequest
     */
    protected $request;

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
     * XHR (X-Inertia header present): returns JSON Response with the Page object.
     * Full-page visit: assigns the Page to the view and calls view->render(),
     * returning the rendered result.
     *
     * HTTP headers (Content-Type, Vary, X-Inertia-Version) are the middleware's
     * responsibility — the trait only handles rendering.
     */
    protected function inertia(string $component, array $props = []): ResponseInterface
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
            return new Response(200, [], json_encode($page));
        }

        $this->view->assign('inertiaPage', $page);

        return $this->wrapRenderResult($this->view->render());
    }

    /**
     * Normalize view->render() return into a ResponseInterface.
     *
     * render() may return ResponseInterface directly, or a string / StreamableInterface.
     */
    private function wrapRenderResult(mixed $result): ResponseInterface
    {
        if ($result instanceof ResponseInterface) {
            return $result;
        }

        return new Response(200, [], (string) $result);
    }
}
