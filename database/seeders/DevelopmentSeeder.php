<?php

namespace Database\Seeders;

use App\Models\File;
use App\Models\Publication;
use App\Models\PublicationComment;
use App\Models\PublicationFile;
use App\Models\Tag;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
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
        $usersCount = max(20, $usersCount);
        $imageFile = $this->upsertDevelopmentImageFile();
        $devImageUrl = $this->resolveDevImageUrl();

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

        $users = collect([$admin, $editor])->merge($members)->values();
        $tags = $this->upsertTags();
        $this->cleanupPreviousDevelopmentPublications();

        $longPublicationsCount = max(24, $usersCount * 4);
        $timelinePublicationsCount = max(80, $usersCount * 10);

        $longPublications = $this->seedPublications(
            users: $users,
            tags: $tags,
            imageFile: $imageFile,
            devImageUrl: $devImageUrl,
            count: $longPublicationsCount,
            postType: 'publication',
        );

        $timelinePublications = $this->seedPublications(
            users: $users,
            tags: $tags,
            imageFile: $imageFile,
            devImageUrl: $devImageUrl,
            count: $timelinePublicationsCount,
            postType: 'timeline',
        );

        $allPublications = $longPublications->merge($timelinePublications)->values();

        $this->seedComments($allPublications, $users);
        $this->seedLikes($allPublications, $users);
        $this->seedSaves($allPublications, $users);
        $this->seedViews($allPublications, $users);
        $this->seedFollows($users);
        $this->refreshAggregates($allPublications);
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
        $devImageUrl = $this->resolveDevImageUrl();

        $file = File::query()
            ->withTrashed()
            ->firstOrNew(['external_file_id' => self::DEV_IMAGE_EXTERNAL_ID]);

        if (! $file->exists) {
            $file->id = self::DEV_IMAGE_FILE_ID;
        }

        $file->fill([
            'success' => true,
            'original_filename' => self::DEV_IMAGE_FILENAME,
            'public_url' => $devImageUrl,
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

    private function cleanupPreviousDevelopmentPublications(): void
    {
        $developmentPublicationIds = Publication::query()
            ->withTrashed()
            ->where('slug', 'like', 'dev-%')
            ->pluck('id');

        if ($developmentPublicationIds->isEmpty()) {
            return;
        }

        DB::table('publication_views')->whereIn('publication_id', $developmentPublicationIds)->delete();
        DB::table('publication_saves')->whereIn('publication_id', $developmentPublicationIds)->delete();
        DB::table('publication_likes')->whereIn('publication_id', $developmentPublicationIds)->delete();
        DB::table('publication_comments')->whereIn('publication_id', $developmentPublicationIds)->delete();
        DB::table('publication_files')->whereIn('publication_id', $developmentPublicationIds)->delete();
        DB::table('publication_tag')->whereIn('publication_id', $developmentPublicationIds)->delete();
        // `slug` is unique; remove previous DEV records permanently to avoid collisions
        // when the seeder recreates deterministic `dev-*` slugs.
        DB::table('publications')->whereIn('id', $developmentPublicationIds)->delete();
    }

    private function upsertTags(): Collection
    {
        $tagNames = [
            'Direito Civil',
            'Direito Penal',
            'Constitucional',
            'Tributário',
            'Empresarial',
            'Trabalhista',
            'Consumidor',
            'LGPD',
            'Processo Civil',
            'Criminal',
            'Jurisprudência',
            'Carreira Jurídica',
        ];

        return collect($tagNames)
            ->map(function (string $name): Tag {
                $slug = Str::slug($name);

                return Tag::query()->firstOrCreate(
                    ['slug' => $slug !== '' ? $slug : Str::lower((string) Str::ulid())],
                    ['name' => $name],
                );
            })
            ->values();
    }

    private function seedPublications(
        Collection $users,
        Collection $tags,
        File $imageFile,
        string $devImageUrl,
        int $count,
        string $postType,
    ): Collection {
        $items = collect();
        $contentTypes = ['text', 'image', 'video', 'link'];

        for ($i = 1; $i <= $count; $i++) {
            $author = $users->random();
            $contentType = $contentTypes[array_rand($contentTypes)];
            $isLong = $postType === 'publication';
            $slugPrefix = $isLong ? 'dev-publicacao' : 'dev-timeline';
            $slug = sprintf('%s-%04d', $slugPrefix, $i);
            $tag = $tags->random();
            $status = $isLong
                ? fake()->randomElement(['published', 'published', 'published', 'pending_review', 'draft'])
                : 'published';
            $baseDate = CarbonImmutable::now()->subHours(random_int(1, 720));
            $mediaUrl = in_array($contentType, ['image', 'video', 'link'], true)
                ? $devImageUrl
                : null;

            $publication = Publication::query()->create([
                'user_id' => $author->id,
                'post_type' => $postType,
                'content_type' => $contentType,
                'slug' => $slug,
                'title' => $isLong ? fake()->sentence(8) : null,
                'excerpt' => $isLong ? fake()->sentence(20) : null,
                'content' => $isLong ? fake()->paragraphs(random_int(4, 9), true) : null,
                'body' => $isLong ? null : fake()->paragraphs(random_int(1, 3), true),
                'tag' => $tag->name,
                'cover_url' => $isLong ? $devImageUrl : null,
                'media_url' => $mediaUrl,
                'status' => $status,
                'search_engine_index' => $isLong && $status === 'published',
                'published_at' => $status === 'published' ? $baseDate : null,
                'created_at' => $baseDate,
                'updated_at' => $baseDate,
            ]);

            $publication->tags()->syncWithoutDetaching(
                $tags->random(random_int(1, min(3, $tags->count())))->pluck('id')->all()
            );

            if ($contentType !== 'text') {
                PublicationFile::query()->create([
                    'publication_id' => $publication->id,
                    'file_id' => $imageFile->id,
                    'kind' => $isLong ? 'cover' : 'image',
                    'sort_order' => 0,
                ]);
            }

            $items->push($publication);
        }

        return $items;
    }

    private function resolveDevImageUrl(): string
    {
        $baseUrl = rtrim((string) config('services.cdn_upload.base_url'), '/');

        if ($baseUrl === '') {
            return self::DEV_IMAGE_PUBLIC_URL;
        }

        return $baseUrl.'/'.ltrim(self::DEV_IMAGE_PUBLIC_URL, '/');
    }

    private function seedComments(Collection $publications, Collection $users): void
    {
        foreach ($publications as $publication) {
            $commentsCount = random_int(2, 14);
            $rootComments = collect();

            for ($i = 1; $i <= $commentsCount; $i++) {
                $commentedAt = CarbonImmutable::parse((string) $publication->created_at)->addMinutes($i * random_int(5, 40));
                $author = $users->random();
                $parent = $rootComments->isNotEmpty() && $i % 4 === 0 ? $rootComments->random() : null;

                $comment = PublicationComment::query()->create([
                    'publication_id' => $publication->id,
                    'user_id' => $author->id,
                    'parent_id' => $parent?->id,
                    'body' => fake()->sentence(random_int(8, 24)),
                    'created_at' => $commentedAt,
                    'updated_at' => $commentedAt,
                ]);

                if ($parent === null) {
                    $rootComments->push($comment);
                }
            }
        }
    }

    private function seedLikes(Collection $publications, Collection $users): void
    {
        foreach ($publications as $publication) {
            $likesUsers = $users->shuffle()->take(random_int(3, min(25, $users->count())));
            foreach ($likesUsers as $likedBy) {
                DB::table('publication_likes')->insertOrIgnore([
                    'publication_id' => $publication->id,
                    'user_id' => $likedBy->id,
                    'created_at' => CarbonImmutable::parse((string) $publication->created_at)->addMinutes(random_int(1, 600)),
                    'deleted_at' => null,
                ]);
            }
        }
    }

    private function seedSaves(Collection $publications, Collection $users): void
    {
        foreach ($publications as $publication) {
            $savedByUsers = $users->shuffle()->take(random_int(1, min(18, $users->count())));
            foreach ($savedByUsers as $savedBy) {
                DB::table('publication_saves')->insertOrIgnore([
                    'publication_id' => $publication->id,
                    'user_id' => $savedBy->id,
                    'created_at' => CarbonImmutable::parse((string) $publication->created_at)->addMinutes(random_int(1, 720)),
                    'deleted_at' => null,
                ]);
            }
        }
    }

    private function seedViews(Collection $publications, Collection $users): void
    {
        foreach ($publications as $publication) {
            $viewsCount = random_int(8, 60);
            for ($i = 0; $i < $viewsCount; $i++) {
                $viewer = $users->random();
                DB::table('publication_views')->insert([
                    'id' => Str::lower((string) Str::ulid()),
                    'publication_id' => $publication->id,
                    'user_id' => random_int(0, 100) < 80 ? $viewer->id : null,
                    'ip_address' => fake()->ipv4(),
                    'user_agent' => fake()->userAgent(),
                    'viewed_at' => CarbonImmutable::parse((string) $publication->created_at)->addMinutes(random_int(1, 1440)),
                    'created_at' => now(),
                    'updated_at' => now(),
                    'deleted_at' => null,
                ]);
            }
        }
    }

    private function seedFollows(Collection $users): void
    {
        foreach ($users as $follower) {
            $followees = $users
                ->where('id', '!=', $follower->id)
                ->shuffle()
                ->take(random_int(3, min(20, max(3, $users->count() - 1))));

            foreach ($followees as $followee) {
                DB::table('user_follows')->insertOrIgnore([
                    'follower_id' => $follower->id,
                    'followee_id' => $followee->id,
                    'created_at' => now()->subDays(random_int(0, 60)),
                    'deleted_at' => null,
                ]);
            }
        }
    }

    private function refreshAggregates(Collection $publications): void
    {
        $publications->each(function (Publication $publication): void {
            $publication->likes_count = DB::table('publication_likes')
                ->where('publication_id', $publication->id)
                ->whereNull('deleted_at')
                ->count();
            $publication->comments_count = DB::table('publication_comments')
                ->where('publication_id', $publication->id)
                ->whereNull('deleted_at')
                ->count();
            $publication->save();
        });
    }
}
