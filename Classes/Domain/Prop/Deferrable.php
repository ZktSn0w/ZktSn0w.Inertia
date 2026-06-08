<?php

namespace ZktSn0w\Inertia\Domain\Prop;

interface Deferrable
{
    public function shouldDefer(): bool;
    public function group(): string;
}
