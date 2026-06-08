<?php
namespace ZktSn0w\Inertia\Http\Middleware;

use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Neos\Flow\Annotations as Flow;
use ZktSn0w\Inertia\App;
use ZktSn0w\Inertia\Service\InertiaAssetVersionService;
final class InertiaMiddleware implements MiddlewareInterface
{
    #[Flow\Inject()]
    protected InertiaAssetVersionService $inertiaAssetVersionService;

    public function process(ServerRequestInterface $request, RequestHandlerInterface $next): ResponseInterface
    {
        if (!$request->hasHeader(App::HEADER->value)) {
            return $next->handle($request);
        }

        $response = $next->handle($request);
        $response = $response->withHeader(App::HEADER->value, 'true');

        if ($request->getMethod() === 'GET' && $request->hasHeader(App::VERSION_HEADER->value) && $request->getHeaderLine(App::VERSION_HEADER->value) !== $this->inertiaAssetVersionService->getAssetVersion()) {
            $response = new Response(409, [App::INERTIA_LOCATION_HEADER->value => $request->getUri()->getPath()]);
            return $response;
        }

        if ($response->getStatusCode() === 302 && in_array($request->getMethod(), ['PUT', 'PATCH', 'DELETE'])) {
            $response = $response->withStatus(303);
        }

        return $response->withHeader('Vary', 'Accept');
    }
}
