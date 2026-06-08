<?php

namespace ZktSn0w\Inertia\Service;

use Neos\Flow\Annotations as Flow;

#[Flow\Scope('singleton')]
class SharedPropsService {
    private array $props = [];

    public function getProps(): array
    {
        return $this->props;
    }

    public function share(array $props)
    {
        $this->props = array_merge($this->props, $props);
    }
}
