<?php

namespace Tests\Unit;

use App\Http\Resources\SessionUserResource;
use App\Models\User;
use App\Models\UserPreference;
use App\Models\UserProfile;
use App\Models\UserRole;
use App\Models\UserVerification;
use Illuminate\Http\Request;
use Tests\TestCase;

class SessionUserResourceTest extends TestCase
{
    public function test_it_returns_the_frontend_session_shape(): void
    {
        $user = new User([
            'name' => 'Administrador RDD',
            'email' => 'admin@admin.com',
        ]);
        $user->id = '01hz0000000000000000000000';
        $user->created_at = now();
        $user->setRelation('profile', new UserProfile([
            'initials' => 'AD',
            'headline' => 'Administrador da República do Direito',
            'phone' => '+55 11 99999-9999',
            'language' => 'pt-BR',
        ]));
        $user->setRelation('preferences', new UserPreference([
            'public_profile' => true,
            'show_email' => false,
            'search_engine_index' => true,
            'allow_messages' => true,
            'show_activity' => true,
        ]));
        $user->setRelation('roleRecord', new UserRole(['role' => 'admin']));
        $user->setRelation('verification', new UserVerification(['status' => 'approved']));

        $payload = (new SessionUserResource($user))->toArray(Request::create('/me'));

        $this->assertSame('01hz0000000000000000000000', $payload['id']);
        $this->assertSame('admin@admin.com', $payload['email']);
        $this->assertSame('admin', $payload['role']);
        $this->assertSame('+55 11 99999-9999', $payload['phone']);
        $this->assertTrue($payload['publicProfile']);
        $this->assertFalse($payload['showEmail']);
        $this->assertSame('approved', $payload['verification']['status']);
        $this->assertArrayHasKey('createdAt', $payload);
    }
}
