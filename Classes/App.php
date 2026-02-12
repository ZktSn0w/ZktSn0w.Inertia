<?php

namespace ZktSn0w\Inertia;

enum App: string {
    /**
     * The header Inertia uses to identify it's ajax page requests.
     * @var string
     */
    case HEADER = "X-Inertia";

    /**
     * The asset version header Inertia uses to check if assets have updated.
     * @var string
     */
    case VERSION_HEADER = "X-Inertia-Version";

    case LOCATION_HEADER = "X-Inertia-Location";

}
