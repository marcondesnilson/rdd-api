<?php

namespace Tests\Feature;

use App\Models\Publication;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MyPublicationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_list_own_publications(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        Publication::query()->create([
            'user_id' => $user->id,
            'post_type' => 'publication',
            'content_type' => 'text',
            'slug' => 'minha-publicacao',
            'title' => 'Minha publicação',
            'excerpt' => 'Resumo',
            'content' => 'Conteúdo',
            'status' => 'draft',
        ]);

        Publication::query()->create([
            'user_id' => $otherUser->id,
            'post_type' => 'publication',
            'content_type' => 'text',
            'slug' => 'publicacao-de-outro',
            'title' => 'Publicação de outro',
            'excerpt' => 'Resumo',
            'content' => 'Conteúdo',
            'status' => 'published',
            'published_at' => now(),
        ]);

        Publication::query()->create([
            'user_id' => $user->id,
            'post_type' => 'timeline',
            'content_type' => 'text',
            'body' => 'Post curto',
            'status' => 'published',
            'published_at' => now(),
        ]);

        $token = $user->createToken('frontend:test')->plainTextToken;

        $response = $this->withToken($token)->getJson('/me/publications');

        $response
            ->assertOk()
            ->assertJsonCount(1, 'publications')
            ->assertJsonPath('publications.0.slug', 'minha-publicacao')
            ->assertJsonPath('publications.0.status', 'draft');
    }

    public function test_authenticated_user_can_filter_own_publications_by_status(): void
    {
        $user = User::factory()->create();

        Publication::query()->create([
            'user_id' => $user->id,
            'post_type' => 'publication',
            'content_type' => 'text',
            'slug' => 'artigo-publicado',
            'title' => 'Artigo publicado',
            'excerpt' => 'Resumo',
            'content' => 'Conteúdo',
            'status' => 'published',
            'published_at' => now(),
        ]);

        Publication::query()->create([
            'user_id' => $user->id,
            'post_type' => 'publication',
            'content_type' => 'text',
            'slug' => 'artigo-rascunho',
            'title' => 'Artigo rascunho',
            'excerpt' => 'Resumo',
            'content' => 'Conteúdo',
            'status' => 'draft',
        ]);

        $token = $user->createToken('frontend:test')->plainTextToken;

        $response = $this->withToken($token)->getJson('/me/publications?status=published');

        $response
            ->assertOk()
            ->assertJsonCount(1, 'publications')
            ->assertJsonPath('publications.0.slug', 'artigo-publicado')
            ->assertJsonPath('publications.0.status', 'published');
    }
}
