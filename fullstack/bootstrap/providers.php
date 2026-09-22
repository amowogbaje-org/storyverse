<?php

return [
    App\Providers\AppServiceProvider::class,
    // Telescope is dev-tool-grade but genuinely useful in production on a
    // single small app like this - it's gated to admins only (see the
    // provider itself), and there's no separate API/monitoring split to
    // worry about anymore, so it's just switched on.
    App\Providers\TelescopeServiceProvider::class,
];
