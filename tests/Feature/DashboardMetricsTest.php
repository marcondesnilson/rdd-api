<?php

namespace Tests\Feature;

use App\Models\Publication;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DashboardMetricsTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_receives_dashboard_metrics_from_database(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $publication = Publication::query()->create([
            'user_id' => $user->id,
            'post_type' => 'publication',
            'content_type' => 'text',
            'slug' => 'artigo-1',
            'title' => 'Artigo 1',
            'excerpt' => 'Resumo',
            'content' => 'Conteúdo',
            'status' => 'published',
            'published_at' => now(),
        ]);

        Publication::query()->create([
            'user_id' => $user->id,
            'post_type' => 'publication',
            'content_type' => 'text',
            'slug' => 'artigo-2',
            'title' => 'Artigo 2',
            'excerpt' => 'Resumo',
            'content' => 'Conteúdo',
            'status' => 'draft',
        ]);

        Publication::query()->create([
            'user_id' => $user->id,
            'post_type' => 'timeline',
            'content_type' => 'text',
            'body' => 'Timeline',
            'status' => 'published',
            'published_at' => now(),
        ]);

        DB::table('publication_views')->insert([
            'id' => (string) str()->ulid(),
            'publication_id' => $publication->id,
            'user_id' => $otherUser->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
            'viewed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
            'deleted_at' => null,
        ]);

        DB::table('publication_likes')->insert([
            'publication_id' => $publication->id,
            'user_id' => $otherUser->id,
            'created_at' => now(),
            'deleted_at' => null,
        ]);

        DB::table('user_follows')->insert([
            'follower_id' => $otherUser->id,
            'followee_id' => $user->id,
            'created_at' => now(),
            'deleted_at' => null,
        ]);

        $token = $user->createToken('frontend:test')->plainTextToken;

        $response = $this->withToken($token)->getJson('/me/dashboard/metrics');

        $response
            ->assertOk()
            ->assertJsonPath('metrics.viewsCount', 1)
            ->assertJsonPath('metrics.likesCount', 1)
            ->assertJsonPath('metrics.followersCount', 1)
            ->assertJsonPath('metrics.publicationsCount', 2);
    }
}
