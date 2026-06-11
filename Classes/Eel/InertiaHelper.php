<?php

namespace ZktSn0w\Inertia\Eel;

use Neos\Eel\ProtectedContextAwareInterface;
use Psr\Http\Message\ServerRequestInterface;
use ZktSn0w\Inertia\App;
use function Neos\Flow\var_dump;

/**
 * Eel helper for Inertia protocol checks in Fusion expressions.
 *
 * Usage in Fusion:
 *   condition = ${Inertia.isInertiaRequest(request.httpRequest)}
 */
class InertiaHelper implements ProtectedContextAwareInterface
{
    /**
     * Check whether the current request is an Inertia XHR navigation.
     */
    public function isInertiaRequest(ServerRequestInterface $request): bool
    {
        return $request->getHeaderLine(App::HEADER->value) === 'true';
    }

    public function allowsCallOfMethod($methodName): bool
    {
        return true;
    }
}
