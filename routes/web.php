<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Central Routes
|--------------------------------------------------------------------------
|
| Served only on central domains (config('tenancy.central_domains')) —
| tenant subdomains are routed entirely through routes/tenant.php. No
| central marketing/admin page exists yet; this is a placeholder.
*/
Route::get('/', function () {
    return response()->json(['app' => 'School Management System', 'context' => 'central']);
});
