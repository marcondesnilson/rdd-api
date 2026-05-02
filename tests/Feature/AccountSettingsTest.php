<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AccountSettingsTest extends TestCase
{
    use RefreshDatabase;

    private function generateTotpCode(string $secret, int $timeSlice): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $clean = strtoupper(preg_replace('/[^A-Z2-7]/', '', $secret) ?? '');

        $bits = '';
        foreach (str_split($clean) as $char) {
            $position = strpos($alphabet, $char);
            if ($position === false) {
                return '000000';
            }
            $bits .= str_pad(decbin($position), 5, '0', STR_PAD_LEFT);
        }

        $key = '';
        foreach (str_split($bits, 8) as $byte) {
            if (strlen($byte) === 8) {
                $key .= chr(bindec($byte));
            }
        }

        $binaryTime = pack('N*', 0).pack('N*', $timeSlice);
        $hash = hash_hmac('sha1', $binaryTime, $key, true);
        $offset = ord(substr($hash, -1)) & 0x0F;
        $value = (
            ((ord($hash[$offset]) & 0x7F) << 24) |
            ((ord($hash[$offset + 1]) & 0xFF) << 16) |
            ((ord($hash[$offset + 2]) & 0xFF) << 8) |
            (ord($hash[$offset + 3]) & 0xFF)
        ) % 1000000;

        return str_pad((string) $value, 6, '0', STR_PAD_LEFT);
    }

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

    public function test_authenticated_user_can_update_security_preferences_and_password(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('Senha@123'),
        ]);
        $secret = 'JBSWY3DPEHPK3PXP';
        $code = $this->generateTotpCode($secret, intdiv(time(), 30));

        $token = $user->createToken('frontend:test')->plainTextToken;

        $response = $this
            ->withToken($token)
            ->patchJson('/me/security', [
                'currentPassword' => 'Senha@123',
                'newPassword' => 'NovaSenha@123',
                'newPasswordConfirmation' => 'NovaSenha@123',
                'mfaEnabled' => true,
                'mfaMethod' => 'totp',
                'mfaSecret' => $secret,
                'mfaCode' => $code,
                'securityEmailAlerts' => false,
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('user.mfaEnabled', true)
            ->assertJsonPath('user.mfaMethods.0', 'totp')
            ->assertJsonPath('user.securityEmailAlerts', false);

        $this->assertTrue(Hash::check('NovaSenha@123', $user->fresh()->password));
        $this->assertDatabaseHas('user_preferences', [
            'user_id' => $user->id,
            'security_email_alerts' => false,
        ]);
        $this->assertDatabaseHas('user_mfa', [
            'user_id' => $user->id,
            'method' => 'totp',
            'enabled' => true,
        ]);
    }

    public function test_authenticated_user_cannot_enable_totp_with_invalid_code(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('Senha@123'),
        ]);

        $token = $user->createToken('frontend:test')->plainTextToken;

        $response = $this
            ->withToken($token)
            ->patchJson('/me/security', [
                'mfaEnabled' => true,
                'mfaMethod' => 'totp',
                'mfaSecret' => 'JBSWY3DPEHPK3PXP',
                'mfaCode' => '000000',
            ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['mfaCode']);
    }

    public function test_authenticated_user_cannot_change_password_with_invalid_current_password(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('Senha@123'),
        ]);

        $token = $user->createToken('frontend:test')->plainTextToken;

        $response = $this
            ->withToken($token)
            ->patchJson('/me/security', [
                'currentPassword' => 'SenhaErrada@123',
                'newPassword' => 'NovaSenha@123',
                'newPasswordConfirmation' => 'NovaSenha@123',
            ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['currentPassword']);
    }

    public function test_authenticated_user_can_verify_mfa_totp_with_saved_secret(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('Senha@123'),
        ]);
        $secret = 'JBSWY3DPEHPK3PXP';
        $code = $this->generateTotpCode($secret, intdiv(time(), 30));

        $token = $user->createToken('frontend:test')->plainTextToken;

        $this
            ->withToken($token)
            ->patchJson('/me/security', [
                'mfaEnabled' => true,
                'mfaMethod' => 'totp',
                'mfaSecret' => $secret,
                'mfaCode' => $code,
            ])
            ->assertOk();

        $verifyResponse = $this
            ->withToken($token)
            ->postJson('/auth/mfa/verify', [
                'method' => 'totp',
                'mfaCode' => $this->generateTotpCode($secret, intdiv(time(), 30)),
            ]);

        $verifyResponse
            ->assertOk()
            ->assertJsonPath('verified', true);
    }
}
