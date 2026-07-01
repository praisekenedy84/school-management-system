<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\ListUsersRequest;
use App\Http\Resources\UserLookupResource;
use App\Models\User;

class UserController extends Controller
{
    /**
     * Searchable lookup for teachers or guardians (parents). Scoped to the
     * caller's school when they have one; tenant-wide admins may pass
     * `school_id` to narrow results (e.g. when linking a guardian to a
     * student on a specific campus).
     */
    public function index(ListUsersRequest $request)
    {
        $role = $request->string('role')->toString();

        abort_unless($request->user()?->can('lookup', [User::class, $role]) ?? false, 403);

        $schoolId = $request->user()->school_id ?? $request->input('school_id');

        $roles = match ($role) {
            'teacher' => ['teacher', 'class_teacher'],
            'parent' => ['parent'],
            default => [],
        };

        $users = User::query()
            ->where('is_active', true)
            ->when($schoolId !== null, fn ($query) => $query->where('school_id', $schoolId))
            ->when($request->filled('search'), function ($query) use ($request) {
                $term = '%'.$request->string('search').'%';

                $query->where(function ($query) use ($term) {
                    $query->where('name', 'ilike', $term)
                        ->orWhere('email', 'ilike', $term);
                });
            })
            ->role($roles)
            ->orderBy('name')
            ->limit($request->integer('limit', 200))
            ->get();

        return UserLookupResource::collection($users);
    }
}
