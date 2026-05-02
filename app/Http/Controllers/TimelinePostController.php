<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTimelinePostRequest;
use App\Http\Resources\TimelinePostResource;
use App\Models\File;
use App\Models\Publication;
use App\Models\PublicationFile;
use App\Models\Tag;
use App\Models\User;
use App\Services\AuditTrail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TimelinePostController extends Controller
{
    public function __construct(private readonly AuditTrail $auditTrail) {}

    public function index(): JsonResponse
    {
        $posts = Publication::query()
            ->with(['user.profile', 'user.roleRecord'])
            ->where('post_type', 'timeline')
            ->when(
                request()->filled('contentType'),
                fn ($query) => $query->where('content_type', request()->string('contentType')->toString())
            )
            ->when(
                request()->filled('tag'),
                fn ($query) => $query->whereHas('tags', function ($query): void {
                    $tag = request()->string('tag')->toString();
                    $query->where('slug', Str::slug($tag))->orWhere('name', $tag);
                })
            )
            ->latest()
            ->limit(100)
            ->get();

        return response()->json([
            'posts' => TimelinePostResource::collection($posts),
        ]);
    }

    public function store(StoreTimelinePostRequest $request): JsonResponse
    {
        $data = $request->validated();

        $post = Publication::query()->create([
            'user_id' => $request->user()->id,
            'post_type' => 'timeline',
            'content_type' => $data['contentType'],
            'body' => $data['body'],
            'media_url' => $data['mediaUrl'] ?? null,
            'status' => 'published',
            'search_engine_index' => false,
            'published_at' => now(),
        ]);
        $this->syncTagsAndFiles($post, $data);

        $this->auditTrail->record($request, 'timeline.post_created', $request->user(), metadata: [
            'timelinePostId' => $post->id,
            'contentType' => $post->content_type,
        ]);

        return response()->json([
            'post' => TimelinePostResource::make($post),
        ], 201);
    }

    public function profileTimeline(Request $request, User $user): JsonResponse
    {
        $posts = Publication::query()
            ->with(['user.profile', 'user.roleRecord'])
            ->where('post_type', 'timeline')
            ->where('user_id', $user->id)
            ->when(
                $request->filled('contentType'),
                fn ($query) => $query->where('content_type', $request->string('contentType')->toString())
            )
            ->when(
                $request->filled('tag'),
                fn ($query) => $query->whereHas('tags', function ($query) use ($request): void {
                    $tag = $request->string('tag')->toString();
                    $query->where('slug', Str::slug($tag))->orWhere('name', $tag);
                })
            )
            ->latest()
            ->limit(100)
            ->get();

        $this->auditTrail->record($request, 'timeline.profile_viewed', $request->user(), $user);

        return response()->json([
            'posts' => TimelinePostResource::collection($posts),
        ]);
    }

    private function syncTagsAndFiles(Publication $publication, array $data): void
    {
        DB::transaction(function () use ($publication, $data): void {
            $autoTags = $this->extractHashtags((string) ($data['body'] ?? ''));

            $tags = collect($data['tags'] ?? [])
                ->filter(fn ($tag) => is_string($tag) && trim($tag) !== '')
                ->map(fn (string $name) => trim($name))
                ->merge($autoTags)
                ->unique()
                ->values();

            if ($tags->isNotEmpty()) {
                $tagIds = $tags->map(function (string $name) {
                    $slug = Str::slug($name);
                    $tag = Tag::query()->firstOrCreate(
                        ['slug' => $slug !== '' ? $slug : Str::lower((string) Str::ulid())],
                        ['name' => $name],
                    );

                    return $tag->id;
                })->all();

                $publication->tags()->syncWithoutDetaching($tagIds);
            }

            $fileIds = collect($data['fileIds'] ?? [])
                ->filter(fn ($id) => is_string($id) && trim($id) !== '')
                ->unique()
                ->values();

            if ($fileIds->isNotEmpty()) {
                $files = File::query()->whereIn('id', $fileIds)->get(['id', 'mime_type']);
                foreach ($files as $index => $file) {
                    $kind = Str::startsWith($file->mime_type, 'video/') ? 'video' : 'image';

                    PublicationFile::query()->firstOrCreate([
                        'publication_id' => $publication->id,
                        'file_id' => $file->id,
                    ], [
                        'kind' => $kind,
                        'sort_order' => $index,
                    ]);
                }
            }
        });
    }

    /**
     * @return array<int, string>
     */
    private function extractHashtags(string $text): array
    {
        if ($text === '') {
            return [];
        }

        preg_match_all('/#([\p{L}\p{N}_]+)/u', $text, $matches);
        $tags = collect($matches[1] ?? [])
            ->filter(fn ($value) => is_string($value) && trim($value) !== '')
            ->map(fn (string $value) => mb_strtolower(trim($value)))
            ->unique()
            ->values()
            ->all();

        return $tags;
    }
}
