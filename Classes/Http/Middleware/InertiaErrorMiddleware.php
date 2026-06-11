<?php

namespace ZktSn0w\Inertia\Http\Middleware;

use GuzzleHttp\Psr7\Response;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Mvc\Exception\StopActionException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use ZktSn0w\Inertia\App;
use ZktSn0w\Inertia\Domain\Page;
use ZktSn0w\Inertia\Service\InertiaAssetVersionService;

/**
 * Catches unhandled exceptions during Inertia XHR requests and returns
 * JSON error responses instead of exposing raw PHP stack traces.
 *
 * Non-Inertia requests (no X-Inertia header) pass through unchanged
 * so Flow's default exception handling (Fusion error pages, etc.) applies.
 *
 * Must be placed OUTSIDE InertiaMiddleware in the chain so it catches
 * exceptions thrown by controllers, other middleware, and InertiaMiddleware itself.
 */
final class InertiaErrorMiddleware implements MiddlewareInterface
{
    #[Flow\InjectConfiguration(path: "ZktSn0w.Inertia.errorHandling")]
    protected ?array $errorHandlingConfig = null;

    #[Flow\Inject]
    protected InertiaAssetVersionService $assetVersionService;

    public function process(ServerRequestInterface $request, RequestHandlerInterface $next): ResponseInterface
    {
        try {
            return $next->handle($request);
        } catch (\Throwable $exception) {
            // Non-Inertia requests: let Flow's normal error handling take over
            if (!$request->hasHeader(App::HEADER->value)) {
                throw $exception;
            }

            // StopActionException is Flow internal (redirect/forward) — let it propagate.
            // The dispatching middleware handles it; reaching here means something is off.
            if ($exception instanceof StopActionException) {
                throw $exception;
            }

            return $this->createErrorResponse($request, $exception);
        }
    }

    /**
     * Build an Inertia-compatible JSON error response for the XHR client.
     */
    private function createErrorResponse(ServerRequestInterface $request, \Throwable $exception): ResponseInterface
    {
        $statusCode = $this->resolveStatusCode($exception);
        $config = $this->errorHandlingConfig ?? [];
        $showDetails = $config['showDetailedErrors'] ?? false;
        $errorComponent = $config['errorComponent'] ?? null;
        $component = $errorComponent ?: 'Error';

        $props = ['status' => $statusCode];

        if ($showDetails) {
            $props['message'] = $exception->getMessage();
            $props['file'] = $exception->getFile();
            $props['line'] = $exception->getLine();
            $props['trace'] = $exception->getTraceAsString();
        } else {
            $props['message'] = $this->statusCodeMessage($statusCode);
        }

        $page = new Page($component, $props);
        $page->setUrl((string) $request->getUri()->getPath());

        $version = $this->assetVersionService->getAssetVersion();
        if ($version !== null) {
            $page->setVersion($version);
        }

        $headers = [
            'Content-Type' => 'application/json',
            App::HEADER->value => 'true',
        ];

        if ($version !== null) {
            $headers[App::VERSION_HEADER->value] = $version;
        }

        return new Response($statusCode, $headers, json_encode($page));
    }

    /**
     * Try to extract a meaningful HTTP status code from the exception.
     *
     * Some Flow exceptions carry a status code via getStatusCode().
     * Falls back to 500 for generic Throwables.
     */
    private function resolveStatusCode(\Throwable $exception): int
    {
        if (method_exists($exception, 'getStatusCode')) {
            $code = $exception->getStatusCode();
            if (is_int($code) && $code >= 400 && $code < 600) {
                return $code;
            }
        }

        return 500;
    }

    /**
     * Human-readable status message for production error responses.
     */
    private function statusCodeMessage(int $code): string
    {
        return match ($code) {
            400 => 'Bad Request',
            401 => 'Unauthorized',
            403 => 'Forbidden',
            404 => 'Not Found',
            405 => 'Method Not Allowed',
            408 => 'Request Timeout',
            409 => 'Conflict',
            422 => 'Unprocessable Entity',
            429 => 'Too Many Requests',
            500 => 'Internal Server Error',
            502 => 'Bad Gateway',
            503 => 'Service Unavailable',
            504 => 'Gateway Timeout',
            default => 'An error occurred',
        };
    }
}
