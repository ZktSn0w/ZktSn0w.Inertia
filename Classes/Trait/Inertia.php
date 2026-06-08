<?php

namespace ZktSn0w\Inertia\Trait;

use GuzzleHttp\Psr7\Response;
use Neos\Flow\Mvc\ActionRequest;
use Psr\Http\Message\ResponseInterface;
use ZktSn0w\Inertia\App;
use ZktSn0w\Inertia\Domain\Page;
use ZktSn0w\Inertia\Domain\Prop\Deferrable;
use ZktSn0w\Inertia\Service\InertiaAssetVersionService;

trait Inertia
{
    private InertiaAssetVersionService $assetVersionService;

    public function injectAssetVersionService(InertiaAssetVersionService $assetVersionService): void
    {
        $this->assetVersionService = $assetVersionService;
    }


    private function resolveProps($props, $partialData): array
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
                if (!$isPartial || in_array($key, $partialData, true)) {
                    $resolvedProps[$key] = $prop();
                }
            } else {
                if (!$isPartial || in_array($key, $partialData, true)) {
                    $resolvedProps[$key] = $prop;
                }
            }
        }

        return [$resolvedProps, $deferredMap];
    }

    private function inertia(string $component, array $props = [], array $viewProps = []): ResponseInterface
    {
        if (!$this->request) {
            throw new \RuntimeException('Request object is not set. Did you use the Inertia trait outside of an ActionController?');
        }

        if (!($this->request instanceof ActionRequest)) {
            throw new \RuntimeException('Request object is not a Neos ActionRequest.');
        }

        $httpRequest = $this->request->getHttpRequest();
        $assetVersion = $this->assetVersionService->getAssetVersion();
        $partialComponent = $httpRequest->getHeaderLine(App::PARTIAL_COMPONENT->value);
        $partialData = array_filter(explode(',', $httpRequest->getHeaderLine(App::PARTIAL_DATA->value)));
        $isPartialReload = $partialComponent === $component && $partialData !== [];

        [$resolvedProps, $deferredMap] = $this->resolveProps($props, $isPartialReload ? $partialData : []);

        $page = Page::create($component, $resolvedProps);

        if ($deferredMap !== []) {
            $page->setDeferredProps($deferredMap);
        }

        if ($assetVersion !== null) {
            $page->setVersion($assetVersion);
        }

        $page->setUrl((string) $httpRequest->getUri()->getPath());

        if (!$httpRequest->hasHeader(App::HEADER->value)) {
            $this->view->assign('page', $page);
            $this->view->assignMultiple($viewProps);

            $rendered = $this->view->render();
            $body = $rendered instanceof ResponseInterface
                ? (string) $rendered->getBody()
                : (string) $rendered;

            return new Response(body: $body);
        }

        $headers = [
            'Vary' => App::HEADER->value,
            'Content-Type' => 'application/json',
        ];

        if ($assetVersion !== null) {
            $headers[App::VERSION_HEADER->value] = $assetVersion;
        }

        return new Response(status: 200, headers: $headers, body: json_encode($page));
    }
}
