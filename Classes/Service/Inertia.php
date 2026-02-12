<?php

namespace ZktSn0w\Inertia\Service;

use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Neos\Flow\Annotations as Flow;
use Neos\Fusion\View\FusionView;
use ZktSn0w\Inertia\App;
use function Neos\Flow\var_dump;

#[Flow\Scope("singleton")]
class Inertia
{

    #[Flow\Inject()]
    protected InertiaAssetVersionService $assetVersionService;

    #[Flow\InjectConfiguration(path: "rootView", package: "ZktSn0w.Inertia")]
    protected string $viewPath;

    public function render(Request $request, string $component, array $props = [], array $viewProps = [], ?FusionView $view)
    {
        $isInertiaRequest = $request->hasHeader(App::HEADER->value);
        $userAssetVersion = $request->hasHeader(App::VERSION_HEADER->value) ? $request->getHeader(App::VERSION_HEADER->value) : null;
        $assetVersion = $this->assetVersionService->getAssetVersion();

        if (!$assetVersion) {
            // TODO: what should we do then?
            return new Response(500, [], 'error');
        }

        $page = [
            'component' => $component,
            'props' => $props,
            'version' => $assetVersion,
            'url' => $request->getUri()
        ];
        $headers = [];

        if ($isInertiaRequest) {
            if ($assetVersion !== $userAssetVersion) {
                // TODO: What now? i guess 409 with x-inertia-location header to redirect to home or something??
            }
            $headers = [
                App::HEADER->value => true,
                'Content-Type' => 'application/json'
            ];
            return new Response(headers: $headers, body: json_encode($page));
        } else {
            $view->setFusionPath('App');
            $view->assign('page', $page);
            $rendered = $view->render();
            $body = $rendered instanceof \Psr\Http\Message\ResponseInterface
                ? (string) $rendered->getBody()
                : (string) $rendered;
            return new Response(headers: $headers, body: $body);
        }
    }
}
