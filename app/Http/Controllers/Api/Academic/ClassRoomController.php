<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Academic;

use App\Http\Controllers\Controller;
use App\Http\Resources\ClassRoomResource;
use App\Models\ClassRoom;

class ClassRoomController extends Controller
{
    public function index()
    {
        $this->authorize('viewAny', ClassRoom::class);

        $classes = ClassRoom::query()->orderBy('name')->get();

        return ClassRoomResource::collection($classes);
    }
}
