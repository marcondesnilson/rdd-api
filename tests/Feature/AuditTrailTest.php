<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditTrailTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_tracks_session_and_access_data(): void
    {
        User::factory()->create([
            'email' => 'ana@example.com',
            'password' => 'secret123',
        ]);

        $response = $this
            ->withServerVariables([
                'REMOTE_ADDR' => '203.0.113.10',
                'HTTP_USER_AGENT' => 'Mozilla/5.0 Chrome/120.0',
            ])
            ->postJson('/auth/login', [
                'email' => 'ana@example.com',
                'password' => 'secret123',
            ]);

        $token = $response->json('token');

        $response
            ->assertOk()
            ->assertJsonPath('user.email', 'ana@example.com')
            ->assertJsonPath('user.lastIp', '203.0.113.10');

        $this->assertDatabaseHas('personal_access_tokens', [
            'ip_address' => '203.0.113.10',
            'last_used_ip_address' => '203.0.113.10',
        ]);
        $this->assertDatabaseHas('user_access_logs', [
            'event' => 'auth.login',
            'ip_address' => '203.0.113.10',
        ]);

        $this
            ->withToken($token)
            ->withServerVariables([
                'REMOTE_ADDR' => '203.0.113.11',
                'HTTP_USER_AGENT' => 'Mozilla/5.0 Chrome/120.0',
            ])
            ->getJson('/me/sessions')
            ->assertOk()
            ->assertJsonPath('sessions.0.browser', 'Chrome')
            ->assertJsonPath('sessions.0.ip', '203.0.113.11');
    }

    public function test_admin_can_inspect_user_logs(): void
    {
        $admin = User::factory()->create();
        $admin->roleRecord()->update(['role' => 'admin']);
        $adminToken = $admin->createToken('frontend:admin')->plainTextToken;

        $user = User::factory()->create();

        $this
            ->withToken($adminToken)
            ->withServerVariables([
                'REMOTE_ADDR' => '203.0.113.20',
                'HTTP_USER_AGENT' => 'Mozilla/5.0 Firefox/120.0',
            ])
            ->postJson('/admin/users', [
                'name' => 'Novo Editor',
                'email' => 'novo.editor@example.com',
                'password' => 'secret123',
                'role' => 'editor',
            ])
            ->assertCreated();

        $this
            ->withToken($adminToken)
            ->getJson("/admin/users/{$user->id}/logs")
            ->assertOk()
            ->assertJsonStructure([
                'logs' => [
                    '*' => ['id', 'event', 'actor', 'method', 'path', 'statusCode', 'ip', 'userAgent', 'metadata', 'occurredAt'],
                ],
            ]);

        $createdUser = User::query()->where('email', 'novo.editor@example.com')->firstOrFail();

        $this
            ->withToken($adminToken)
            ->getJson("/admin/users/{$createdUser->id}/logs")
            ->assertOk()
            ->assertJsonFragment([
                'event' => 'admin.user_created',
                'ip' => '203.0.113.20',
            ]);
    }
}
