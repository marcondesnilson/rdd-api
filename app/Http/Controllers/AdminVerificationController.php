<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReviewUserVerificationRequest;
use App\Models\UserVerification;
use App\Services\AuditTrail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminVerificationController extends Controller
{
    public function __construct(private readonly AuditTrail $auditTrail) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorizeAdminAccess($request);

        $verifications = UserVerification::query()
            ->with('user.profile')
            ->where('status', 'pending')
            ->orderByDesc('submitted_at')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (UserVerification $verification): array => [
                'id' => (string) $verification->id,
                'userId' => (string) $verification->user_id,
                'userName' => $verification->user?->name ?? 'Membro da República',
                'userEmail' => $verification->user?->email ?? '',
                'initials' => $verification->user?->profile?->initials ?? 'RD',
                'requestedRole' => $verification->requested_role,
                'document' => $verification->document,
                'submittedAt' => $verification->submitted_at?->toISOString() ?? $verification->created_at?->toISOString(),
            ])
            ->values();

        return response()->json([
            'verifications' => $verifications,
        ]);
    }

    public function review(ReviewUserVerificationRequest $request, UserVerification $verification): JsonResponse
    {
        $data = $request->validated();
        $user = $verification->user;

        if (! $user) {
            return response()->json(['message' => 'Usuário da verificação não encontrado.'], 404);
        }

        $verification->status = $data['status'];
        $verification->save();

        if ($data['status'] === 'approved' && filled($verification->requested_role)) {
            $user->roleRecord()->updateOrCreate(
                ['user_id' => $user->id],
                ['role' => $verification->requested_role],
            );
        }

        $this->auditTrail->record($request, 'admin.verification_reviewed', $request->user(), $user, [
            'verificationId' => (string) $verification->id,
            'status' => $data['status'],
            'requestedRole' => $verification->requested_role,
        ]);

        return response()->json([
            'verification' => [
                'id' => (string) $verification->id,
                'status' => $verification->status,
            ],
        ]);
    }

    private function authorizeAdminAccess(Request $request): void
    {
        abort_unless(in_array($request->user()?->role, ['admin', 'editor'], true), 403);
    }
}
