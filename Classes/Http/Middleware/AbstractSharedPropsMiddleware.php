<?php

namespace ZktSn0w\Inertia\Http\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Neos\Flow\Annotations as Flow;
use ZktSn0w\Inertia\Service\SharedPropsService;

abstract class AbstractSharedPropsMiddleware implements MiddlewareInterface
{
    #[Flow\Inject]
    protected SharedPropsService $shared;

    public function process(ServerRequestInterface $request, RequestHandlerInterface $next): ResponseInterface
    {
        $this->shared->share($this->getSharedProps($request));
        return $next->handle($request);
    }

    abstract protected function getSharedProps(ServerRequestInterface $request): array;
}
