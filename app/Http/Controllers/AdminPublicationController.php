<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateAdminPublicationStatusRequest;
use App\Http\Resources\PublicationResource;
use App\Models\Publication;
use App\Services\AuditTrail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminPublicationController extends Controller
{
    public function __construct(private readonly AuditTrail $auditTrail) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorizeAdminAccess($request);

        $data = $request->validate([
            'status' => ['nullable', 'string'],
        ]);

        $publications = Publication::query()
            ->with(['user.profile', 'user.roleRecord'])
            ->where('post_type', 'publication')
            ->when(
                isset($data['status']) && is_string($data['status']) && $data['status'] !== '',
                fn ($query) => $query->where('status', $data['status']),
                fn ($query) => $query->where('status', 'pending_review')
            )
            ->latest('created_at')
            ->get();

        return response()->json([
            'publications' => PublicationResource::collection($publications),
        ]);
    }

    public function updateStatus(UpdateAdminPublicationStatusRequest $request, Publication $publication): JsonResponse
    {
        $this->authorizeAdminAccess($request);

        $data = $request->validated();
        $status = $data['status'];

        $publication->status = $status;
        $publication->published_at = $status === 'published'
            ? ($publication->published_at ?? now())
            : $publication->published_at;
        $publication->save();

        $this->auditTrail->record($request, 'admin.publication_status_updated', $request->user(), metadata: [
            'status' => $status,
            'publicationId' => $publication->id,
            'publicationSlug' => $publication->slug,
        ]);

        return response()->json([
            'publication' => PublicationResource::make($publication->fresh(['user.profile', 'user.roleRecord'])),
        ]);
    }

    private function authorizeAdminAccess(Request $request): void
    {
        abort_unless($request->user()?->role === 'admin', 403);
    }
}
