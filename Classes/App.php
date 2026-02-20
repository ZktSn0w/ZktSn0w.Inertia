<?php

namespace ZktSn0w\Inertia;

enum App: string {
    /**
     * The header Inertia uses to identify it's ajax page requests.
     */
    case HEADER = "X-Inertia";

    /**
     * The asset version header Inertia uses to check if assets have updated.
     */
    case VERSION_HEADER = "X-Inertia-Version";

    /**
     * The Location header where Inertia is supposed to redirect to.
     */
    case INERTIA_LOCATION_HEADER = "X-Inertia-Location";
}
