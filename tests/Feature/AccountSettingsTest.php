<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_update_account_settings(): void
    {
        $user = User::factory()->create([
            'name' => 'Ana Souza',
            'email' => 'ana@example.com',
        ]);

        $token = $user->createToken('frontend:test')->plainTextToken;

        $response = $this
            ->withToken($token)
            ->patchJson('/me', [
                'name' => 'Ana Beatriz Souza',
                'email' => 'ana.beatriz@example.com',
                'headline' => 'Estudante de Direito',
                'bio' => 'Pesquisa direito constitucional.',
                'phone' => '+55 11 99999-9999',
                'language' => 'pt-BR',
                'publicProfile' => false,
                'showEmail' => true,
                'searchEngineIndex' => false,
                'allowMessages' => false,
                'showActivity' => false,
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('user.name', 'Ana Beatriz Souza')
            ->assertJsonPath('user.email', 'ana.beatriz@example.com')
            ->assertJsonPath('user.initials', 'AB')
            ->assertJsonPath('user.phone', '+55 11 99999-9999')
            ->assertJsonPath('user.publicProfile', false)
            ->assertJsonPath('user.showEmail', true)
            ->assertJsonPath('user.searchEngineIndex', false)
            ->assertJsonPath('user.allowMessages', false)
            ->assertJsonPath('user.showActivity', false);

        $this->assertDatabaseHas('users', [
            'email' => 'ana.beatriz@example.com',
        ]);
        $this->assertDatabaseHas('user_profiles', [
            'user_id' => $user->id,
            'phone' => '+55 11 99999-9999',
        ]);
        $this->assertDatabaseHas('user_preferences', [
            'user_id' => $user->id,
            'public_profile' => false,
            'show_email' => true,
        ]);
    }

    public function test_authenticated_user_can_list_own_sessions(): void
    {
        $user = User::factory()->create();
        $currentToken = $user->createToken('frontend:current')->plainTextToken;
        $user->createToken('frontend:other');

        $response = $this->withToken($currentToken)->getJson('/me/sessions');

        $response
            ->assertOk()
            ->assertJsonCount(2, 'sessions')
            ->assertJsonFragment([
                'device' => 'frontend',
                'browser' => 'Sessão API',
                'current' => true,
            ]);
    }
}
