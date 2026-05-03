<?php

namespace Database\Seeders;

use App\Models\File;
use App\Models\Publication;
use App\Models\PublicationComment;
use App\Models\PublicationFile;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DevelopmentSeeder extends Seeder
{
    private const DEV_IMAGE_FILE_ID = '01kqp470d3n2gcskcwam5tnf82';
    private const DEV_IMAGE_EXTERNAL_ID = '01kqp46ykkz46ragshs4cnjg6c';
    private const DEV_IMAGE_FILENAME = '1000_F_104747332_4CVc7NGSVMGOWDdVvW0AF1tgFrlSMA5G.jpg';
    private const DEV_IMAGE_PUBLIC_URL = '/a/01kqp46ykkz46ragshs4cnjg6c';
    private const DEV_IMAGE_MIME = 'image/webp';
    private const DEV_IMAGE_SIZE = 71978;

    /**
     * Seed development fixtures.
     */
    public function run(int $usersCount = 10): void
    {
        $usersCount = max(3, $usersCount);
        $imageFile = $this->upsertDevelopmentImageFile();

        $admin = $this->upsertUser(
            name: 'Administrador DEV',
            email: 'admin.dev@rdd.local',
            password: 'dev12345',
            initials: 'AD',
            role: 'admin',
            headline: 'Administrador de ambiente local',
        );

        $editor = $this->upsertUser(
            name: 'Editor DEV',
            email: 'editor.dev@rdd.local',
            password: 'dev12345',
            initials: 'ED',
            role: 'editor',
            headline: 'Editor de ambiente local',
        );

        $roles = ['professor', 'advogado', 'aluno', 'membro'];
        $members = collect();

        for ($i = 1; $i <= $usersCount; $i++) {
            $name = fake()->name();
            $email = sprintf('usuario%02d.dev@rdd.local', $i);
            $role = $roles[($i - 1) % count($roles)];

            $members->push($this->upsertUser(
                name: $name,
                email: $email,
                password: 'dev12345',
                initials: Str::of($name)->explode(' ')->filter()->take(2)->map(
                    fn (string $part) => Str::upper(Str::substr($part, 0, 1))
                )->join('') ?: 'RD',
                role: $role,
                headline: 'Conta de teste para desenvolvimento local',
            ));
        }

        $authors = collect([$admin, $editor])->merge($members->take(6));

        $publicationIds = [];
        foreach ($authors as $index => $author) {
            $slug = sprintf('dev-publicacao-%02d', $index + 1);
            $publication = Publication::query()->updateOrCreate(
                ['slug' => $slug],
                [
                    'user_id' => $author->id,
                    'post_type' => 'publication',
                    'content_type' => 'text',
                    'title' => fake()->sentence(8),
                    'excerpt' => fake()->sentence(20),
                    'content' => fake()->paragraphs(4, true),
                    'tag' => fake()->randomElement(['Direito Civil', 'Constitucional', 'Tributário']),
                    'cover_url' => self::DEV_IMAGE_PUBLIC_URL,
                    'status' => 'published',
                    'published_at' => now()->subDays($index),
                ],
            );

            PublicationFile::query()->updateOrCreate(
                [
                    'publication_id' => $publication->id,
                    'file_id' => $imageFile->id,
                ],
                [
                    'kind' => 'cover',
                    'sort_order' => 0,
                ],
            );

            $publicationIds[] = $publication->id;
        }

        foreach ($publicationIds as $publicationIndex => $publicationId) {
            foreach ($members->take(3) as $offset => $member) {
                $fingerprint = sprintf('dev-comment-%02d-%02d', $publicationIndex + 1, $offset + 1);
                $exists = PublicationComment::query()
                    ->where('publication_id', $publicationId)
                    ->where('body', 'like', $fingerprint.'%')
                    ->exists();

                if (! $exists) {
                    PublicationComment::query()->create([
                        'publication_id' => $publicationId,
                        'user_id' => $member->id,
                        'body' => $fingerprint.' - '.fake()->sentence(12),
                    ]);
                }

                DB::table('publication_likes')->insertOrIgnore([
                    'publication_id' => $publicationId,
                    'user_id' => $member->id,
                    'created_at' => now(),
                    'deleted_at' => null,
                ]);
            }
        }

        foreach ($members->take(5) as $follower) {
            DB::table('user_follows')->insertOrIgnore([
                'follower_id' => $follower->id,
                'followee_id' => $admin->id,
                'created_at' => now(),
                'deleted_at' => null,
            ]);
        }

        Publication::query()
            ->whereIn('id', $publicationIds)
            ->get()
            ->each(function (Publication $publication): void {
                $publication->likes_count = DB::table('publication_likes')
                    ->where('publication_id', $publication->id)
                    ->whereNull('deleted_at')
                    ->count();
                $publication->comments_count = PublicationComment::query()
                    ->where('publication_id', $publication->id)
                    ->count();
                $publication->save();
            });
    }

    private function upsertUser(
        string $name,
        string $email,
        string $password,
        string $initials,
        string $role,
        string $headline,
    ): User {
        $user = User::query()->updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => Hash::make($password),
                'email_verified_at' => now(),
            ],
        );

        $user->profile()->updateOrCreate([], [
            'initials' => $initials,
            'headline' => $headline,
        ]);
        $user->preferences()->updateOrCreate([], []);
        $user->roleRecord()->updateOrCreate([], ['role' => $role]);
        $user->verification()->updateOrCreate([], ['status' => 'approved']);

        return $user;
    }

    private function upsertDevelopmentImageFile(): File
    {
        $file = File::query()
            ->withTrashed()
            ->firstOrNew(['external_file_id' => self::DEV_IMAGE_EXTERNAL_ID]);

        if (! $file->exists) {
            $file->id = self::DEV_IMAGE_FILE_ID;
        }

        $file->fill([
            'success' => true,
            'original_filename' => self::DEV_IMAGE_FILENAME,
            'public_url' => self::DEV_IMAGE_PUBLIC_URL,
            'mime_type' => self::DEV_IMAGE_MIME,
            'size' => self::DEV_IMAGE_SIZE,
            'is_public' => true,
            'is_converted' => true,
        ]);

        if ($file->trashed()) {
            $file->restore();
        }

        $file->save();

        return $file;
    }
}
