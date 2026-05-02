<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
class SessionUserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $relations = ['profile', 'preferences', 'roleRecord', 'verification'];

        if ($this->resource->exists) {
            $relations[] = 'latestLoginLog';
        }

        $this->resource->loadMissing($relations);
        $latestLoginLog = $this->resource->relationLoaded('latestLoginLog')
            ? $this->resource->getRelation('latestLoginLog')
            : null;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'initials' => $this->profile?->initials ?? 'RD',
            'role' => $this->roleRecord?->role ?? 'membro',
            'headline' => $this->profile?->headline,
            'bio' => $this->profile?->bio,
            'avatarUrl' => $this->profile?->avatar_url,
            'phone' => $this->profile?->phone,
            'language' => $this->profile?->language ?? 'pt-BR',
            'publicProfile' => $this->preferences?->public_profile ?? true,
            'showEmail' => $this->preferences?->show_email ?? false,
            'searchEngineIndex' => $this->preferences?->search_engine_index ?? true,
            'allowMessages' => $this->preferences?->allow_messages ?? true,
            'showActivity' => $this->preferences?->show_activity ?? true,
            'verification' => [
                'status' => $this->verification?->status ?? 'none',
                'requestedRole' => $this->verification?->requested_role,
                'document' => $this->verification?->document,
                'submittedAt' => $this->verification?->submitted_at?->toISOString(),
            ],
            'lastLogin' => $latestLoginLog?->occurred_at?->toISOString(),
            'lastIp' => $latestLoginLog?->ip_address,
            'createdAt' => $this->created_at?->toISOString(),
        ];
    }
}
