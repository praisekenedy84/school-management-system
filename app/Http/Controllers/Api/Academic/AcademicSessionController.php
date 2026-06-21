<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Academic;

use App\Http\Controllers\Controller;
use App\Http\Resources\AcademicSessionResource;
use App\Models\AcademicSession;

class AcademicSessionController extends Controller
{
    public function index()
    {
        $this->authorize('viewAny', AcademicSession::class);

        $sessions = AcademicSession::query()->orderBy('start_date', 'desc')->get();

        return AcademicSessionResource::collection($sessions);
    }
}
