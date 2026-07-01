<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Academic;

use App\Events\Academic\AcademicTermChanged;
use App\Http\Controllers\Controller;
use App\Http\Requests\Academic\AcademicTermRequest;
use App\Http\Resources\AcademicTermResource;
use App\Models\AcademicSession;
use App\Models\AcademicTerm;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AcademicTermController extends Controller
{
    public function index(AcademicSession $academicSession)
    {
        $this->authorize('view', $academicSession);

        $terms = $academicSession->terms()->orderBy('start_date')->get();

        return AcademicTermResource::collection($terms);
    }

    public function store(AcademicTermRequest $request, AcademicSession $academicSession)
    {
        $term = DB::transaction(function () use ($request, $academicSession) {
            $term = $academicSession->terms()->create([
                ...$request->validated(),
                'school_id' => $academicSession->school_id,
            ]);

            $this->demoteOtherCurrentTerms($term);

            return $term;
        });

        AcademicTermChanged::dispatch($term, 'created', Auth::user());

        return AcademicTermResource::make($term)
            ->response()
            ->setStatusCode(201);
    }

    public function update(AcademicTermRequest $request, AcademicSession $academicSession, AcademicTerm $term)
    {
        abort_unless($term->academic_session_id === $academicSession->id, 404);

        DB::transaction(function () use ($request, $term) {
            $term->update($request->validated());

            $this->demoteOtherCurrentTerms($term);
        });

        AcademicTermChanged::dispatch($term, 'updated', Auth::user());

        return AcademicTermResource::make($term);
    }

    public function destroy(AcademicSession $academicSession, AcademicTerm $term)
    {
        $this->authorize('update', $academicSession);

        abort_unless($term->academic_session_id === $academicSession->id, 404);

        $term->delete();

        AcademicTermChanged::dispatch($term, 'deleted', Auth::user());

        return response()->noContent();
    }

    private function demoteOtherCurrentTerms(AcademicTerm $term): void
    {
        if (! $term->is_current) {
            return;
        }

        AcademicTerm::where('academic_session_id', $term->academic_session_id)
            ->whereKeyNot($term->id)
            ->update(['is_current' => false]);
    }
}
