<?php

namespace ZktSn0w\Inertia\Service;

use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Neos\Flow\Annotations as Flow;
use Neos\Fusion\View\FusionView;
use ZktSn0w\Inertia\App;

#[Flow\Scope("singleton")]
class Inertia
{

    #[Flow\Inject()]
    protected InertiaAssetVersionService $assetVersionService;

    public function render(Request $request, string $component, array $props = [], array $viewProps = [], ?FusionView $view)
    {
        $headers = [];
        $status = 200;
        $body = null;
        $assetVersion = $this->assetVersionService->getAssetVersion();
        $page = [
            'component' => $component,
            'props' => $props,
            'version' => $assetVersion,
            'url' => $request->getUri()
        ];
        $isInertiaRequest = $request->hasHeader(App::HEADER->value);
        $userAssetVersion = $request->hasHeader(App::VERSION_HEADER->value) ? $request->getHeader(App::VERSION_HEADER->value) : null;

        if ($isInertiaRequest) {
            if (isset($assetVersion)) {
                $headers[App::VERSION_HEADER->value] = $assetVersion;

                if ($assetVersion !== $userAssetVersion && $request->getMethod() === "GET") {
                    $status = 409;
                    $headers[App::INERTIA_LOCATION_HEADER->value] = $request->getUri()->getPath();
                }
            }

            $headers[App::HEADER->value] = true;
            $headers["VARY"] = App::HEADER->name;
            $headers['Content-Type'] = 'application/json';
            $body = json_encode($page);
        } else {
            if (isset($assetVersion)) {
                $page['version'] = $assetVersion;
            }

            $view->setFusionPath('App');
            $view->assign('page', $page);
            $view->assignMultiple($viewProps);

            $rendered = $view->render();

            $body = $rendered instanceof \Psr\Http\Message\ResponseInterface
                ? (string) $rendered->getBody()
                : (string) $rendered;
        }
        return new Response(status: $status, headers: $headers, body: $body);
    }
}
