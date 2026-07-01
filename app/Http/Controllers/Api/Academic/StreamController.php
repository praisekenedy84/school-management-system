<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Academic;

use App\Events\Academic\StreamChanged;
use App\Http\Controllers\Controller;
use App\Http\Requests\Academic\StreamRequest;
use App\Http\Resources\StreamResource;
use App\Models\ClassRoom;
use App\Models\Stream;
use Illuminate\Support\Facades\Auth;

class StreamController extends Controller
{
    public function index(ClassRoom $classRoom)
    {
        $this->authorize('view', $classRoom);

        $streams = $classRoom->streams()->orderBy('name')->get();

        return StreamResource::collection($streams);
    }

    public function store(StreamRequest $request, ClassRoom $classRoom)
    {
        $stream = $classRoom->streams()->create([
            ...$request->validated(),
            'school_id' => $classRoom->school_id,
        ]);

        StreamChanged::dispatch($stream, 'created', Auth::user());

        return StreamResource::make($stream)
            ->response()
            ->setStatusCode(201);
    }

    public function update(StreamRequest $request, ClassRoom $classRoom, Stream $stream)
    {
        abort_unless($stream->class_id === $classRoom->id, 404);

        $stream->update($request->validated());

        StreamChanged::dispatch($stream, 'updated', Auth::user());

        return StreamResource::make($stream);
    }

    public function destroy(ClassRoom $classRoom, Stream $stream)
    {
        $this->authorize('update', $classRoom);

        abort_unless($stream->class_id === $classRoom->id, 404);

        $stream->update(['is_active' => false]);

        StreamChanged::dispatch($stream, 'updated', Auth::user());

        return StreamResource::make($stream);
    }
}
