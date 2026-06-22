<?php

/*
|--------------------------------------------------------------------------
| Central Routes
|--------------------------------------------------------------------------
|
| ADR-0008: there is no longer a separate "central domain" vs. "tenant
| domain" — one domain serves everyone, and the tenant is resolved from
| login credentials. The SPA shell (including the `/` root) is served by
| the catch-all route in routes/tenant.php; nothing central-only needs to
| live here yet.
*/
