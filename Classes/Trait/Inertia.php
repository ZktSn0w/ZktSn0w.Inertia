<?php

namespace ZktSn0w\Inertia\Trait;

use GuzzleHttp\Psr7\Response;
use Neos\Flow\Mvc\ActionRequest;
use Psr\Http\Message\ResponseInterface;
use ZktSn0w\Inertia\App;
use ZktSn0w\Inertia\Domain\Page;
use ZktSn0w\Inertia\Service\InertiaAssetVersionService;

trait Inertia
{
    private InertiaAssetVersionService $assetVersionService;

    public function injectAssetVersionService(InertiaAssetVersionService $assetVersionService): void
    {
        $this->assetVersionService = $assetVersionService;
    }

    private function renderInertia(string $component, array $props = [], array $viewProps = []): ResponseInterface
    {
        if (!$this->request) {
            throw new \Exception('Request object is not set.');
        }

        $requestPropertyClassName = get_class($this->request);

        if (!($requestPropertyClassName === ActionRequest::class)) {
            throw new \Exception('Request object is not a Neos ActionRequest.');
        }

        $headers = [];
        $status = 200;
        $body = null;
        $url = $this->request->getHttpRequest()->getUri();
        $assetVersion = $this->assetVersionService->getAssetVersion();
        $page = Page::create($component, $props);
        $isInertiaRequest = $this->request->getHttpRequest()->hasHeader(App::HEADER->value);
        $assetVersionSet = isset($assetVersion);
        $urlSet = isset($url);

        if ($assetVersionSet) {
            $page->setVersion($assetVersion);
        }

        if ($urlSet) {
            $page->setUrl((string) $url);
        }

        if ($isInertiaRequest) {
            if (isset($assetVersion)) {
                $headers[App::VERSION_HEADER->value] = $assetVersion;
            }

            $headers[App::HEADER->value] = true;
            $headers["VARY"] = App::HEADER->name;
            $headers['Content-Type'] = 'application/json';
            $body = json_encode($page);

        } else {
            $this->view->assign('page', $page);
            $this->view->assignMultiple($viewProps);

            $rendered = $this->view->render();

            $body = $rendered instanceof ResponseInterface
                ? (string) $rendered->getBody()
                : (string) $rendered;
        }

        return new Response(status: $status, headers: $headers, body: $body);
    }
}
