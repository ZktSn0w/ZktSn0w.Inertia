<?php
namespace ZktSn0w\Inertia\Http\Middleware;

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

        if ($request->getMethod() === 'GET' && $request->hasHeader(App::VERSION_HEADER->value) && $request->getHeader(App::VERSION_HEADER->value) !== $this->inertiaAssetVersionService->getAssetVersion()) {
            return $response->withAddedHeader(App::INERTIA_LOCATION_HEADER->value, $request->getUri()->getPath());
        }

        if ($response->getStatusCode() === 302 && in_array($request->getMethod(), ['PUT', 'PATCH', 'DELETE'])) {
            $response = $response->withStatus(303);
        }

        return $response->withHeader('Vary', 'Accept')->withHeader('X-Inertia', 'true');
    }
}
