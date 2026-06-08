<?php

namespace ZktSn0w\Inertia\Domain\Prop;

class DeferProp implements Deferrable
{
    private \Closure $callback;
    private string $group;

    public function __construct(\Closure $callback, string $group = 'default')
    {
        $this->callback = $callback;
        $this->group    = $group;
    }

    public function group(): string
    {
        return $this->group;
    }

    public function shouldDefer(): bool
    {
        return true;
    }

    public function __invoke(): mixed
    {
        return ($this->callback)();
    }
}
