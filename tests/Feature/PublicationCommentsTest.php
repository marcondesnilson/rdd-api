<?php

namespace Tests\Feature;

use App\Models\Publication;
use App\Models\PublicationComment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicationCommentsTest extends TestCase
{
    use RefreshDatabase;

    public function test_comment_owner_can_delete_own_comment(): void
    {
        $author = User::factory()->create();
        $publication = $this->createPublishedPublication($author);
        $commentOwner = User::factory()->create();
        $comment = PublicationComment::query()->create([
            'publication_id' => $publication->id,
            'user_id' => $commentOwner->id,
            'body' => 'Comentário de teste',
        ]);

        $token = $commentOwner->createToken('frontend:test')->plainTextToken;

        $this->withToken($token)
            ->deleteJson("/publications/{$publication->id}/comments/{$comment->id}")
            ->assertOk()
            ->assertJsonPath('deleted', true);

        $this->assertSoftDeleted('publication_comments', ['id' => $comment->id]);
    }

    public function test_admin_can_delete_other_user_comment(): void
    {
        $author = User::factory()->create();
        $publication = $this->createPublishedPublication($author);
        $commentOwner = User::factory()->create();
        $comment = PublicationComment::query()->create([
            'publication_id' => $publication->id,
            'user_id' => $commentOwner->id,
            'body' => 'Comentário de teste',
        ]);

        $admin = User::factory()->create();
        $admin->roleRecord()->update(['role' => 'admin']);
        $adminToken = $admin->createToken('frontend:admin')->plainTextToken;

        $this->withToken($adminToken)
            ->deleteJson("/publications/{$publication->id}/comments/{$comment->id}")
            ->assertOk()
            ->assertJsonPath('deleted', true);

        $this->assertSoftDeleted('publication_comments', ['id' => $comment->id]);
    }

    public function test_non_admin_non_owner_cannot_delete_comment(): void
    {
        $author = User::factory()->create();
        $publication = $this->createPublishedPublication($author);
        $commentOwner = User::factory()->create();
        $comment = PublicationComment::query()->create([
            'publication_id' => $publication->id,
            'user_id' => $commentOwner->id,
            'body' => 'Comentário de teste',
        ]);

        $otherUser = User::factory()->create();
        $token = $otherUser->createToken('frontend:test')->plainTextToken;

        $this->withToken($token)
            ->deleteJson("/publications/{$publication->id}/comments/{$comment->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('publication_comments', [
            'id' => $comment->id,
            'deleted_at' => null,
        ]);
    }

    private function createPublishedPublication(User $author): Publication
    {
        return Publication::query()->create([
            'user_id' => $author->id,
            'post_type' => 'publication',
            'content_type' => 'text',
            'slug' => 'publicacao-comentarios',
            'title' => 'Publicação comentários',
            'excerpt' => 'Resumo',
            'content' => 'Conteúdo',
            'status' => 'published',
            'published_at' => now(),
            'comments_count' => 1,
        ]);
    }
}

