<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAdminUserRequest;
use App\Http\Resources\SessionUserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminUserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorizeAdminAccess($request);

        $users = User::query()
            ->with(['profile', 'preferences', 'roleRecord', 'verification'])
            ->latest()
            ->get();

        return response()->json([
            'users' => SessionUserResource::collection($users),
        ]);
    }

    public function store(StoreAdminUserRequest $request): JsonResponse
    {
        $data = $request->validated();

        $user = User::query()->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'email_verified_at' => now(),
        ]);

        $user->profile()->create([
            'initials' => $this->initialsFor($data['name']),
            'headline' => $data['headline'] ?? 'Membro da República',
        ]);
        $user->preferences()->create();
        $user->roleRecord()->create(['role' => $data['role']]);
        $user->verification()->create([
            'status' => in_array($data['role'], ['admin', 'editor'], true) ? 'approved' : 'none',
        ]);

        return response()->json([
            'user' => SessionUserResource::make($user->load(['profile', 'preferences', 'roleRecord', 'verification'])),
        ], 201);
    }

    private function authorizeAdminAccess(Request $request): void
    {
        abort_unless(in_array($request->user()?->role, ['admin', 'editor'], true), 403);
    }

    private function initialsFor(string $name): string
    {
        $parts = array_values(array_filter(explode(' ', trim($name))));
        $initials = collect($parts)
            ->take(2)
            ->map(fn (string $part) => mb_strtoupper(mb_substr($part, 0, 1)))
            ->join('');

        return $initials ?: 'RD';
    }
}
