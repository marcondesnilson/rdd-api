<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePublicationCommentRequest;
use App\Http\Requests\StorePublicationRequest;
use App\Http\Requests\UploadPublicationFileRequest;
use App\Http\Resources\PublicationResource;
use App\Models\File;
use App\Models\PublicationComment;
use App\Models\PublicationFile;
use App\Models\PublicationLike;
use App\Models\PublicationSave;
use App\Models\PublicationView;
use App\Models\Publication;
use App\Models\Tag;
use App\Services\AuditTrail;
use App\Services\CdnFileUploadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PublicationController extends Controller
{
    public function __construct(
        private readonly AuditTrail $auditTrail,
        private readonly CdnFileUploadService $cdnFileUploadService,
    ) {}

    public function index(): JsonResponse
    {
        $publications = Publication::query()
            ->with(['user.profile', 'user.roleRecord'])
            ->where('post_type', 'publication')
            ->where('status', 'published')
            ->when(
                request()->filled('tag'),
                fn ($query) => $query->whereHas('tags', function ($query): void {
                    $tag = request()->string('tag')->toString();
                    $query->where('slug', Str::slug($tag))->orWhere('name', $tag);
                })
            )
            ->latest('published_at')
            ->latest('created_at')
            ->get();

        return response()->json([
            'publications' => PublicationResource::collection($publications),
        ]);
    }

    public function show(Publication $publication): JsonResponse
    {
        abort_unless($publication->post_type === 'publication' && $publication->status === 'published', 404);

        return response()->json([
            'publication' => PublicationResource::make($publication),
        ]);
    }

    public function store(StorePublicationRequest $request): JsonResponse
    {
        $this->authorizePublicationCreation($request);

        $data = $request->validated();
        $status = $data['status'] ?? 'pending_review';
        $slug = $this->uniqueSlugFor($data['title']);

        $publication = Publication::query()->create([
            'user_id' => $request->user()->id,
            'post_type' => 'publication',
            'content_type' => $data['contentType'] ?? 'text',
            'slug' => $slug,
            'title' => $data['title'],
            'excerpt' => $data['excerpt'],
            'content' => $data['content'],
            'tag' => $data['tag'] ?? null,
            'cover_url' => $data['coverUrl'] ?? null,
            'media_url' => $data['mediaUrl'] ?? null,
            'status' => $status,
            'search_engine_index' => $data['searchEngineIndex'] ?? true,
            'published_at' => $status === 'published' ? now() : null,
        ]);
        $this->syncTagsAndFiles($publication, $data);

        $this->auditTrail->record($request, 'publication.created', $request->user(), metadata: [
            'publicationId' => $publication->id,
            'status' => $publication->status,
        ]);

        return response()->json([
            'publication' => PublicationResource::make($publication),
        ], 201);
    }

    public function uploadFile(UploadPublicationFileRequest $request): JsonResponse
    {
        $stored = $this->cdnFileUploadService->uploadAndStore($request->file('file'));

        return response()->json([
            'file' => [
                'id' => $stored->id,
                'externalFileId' => $stored->external_file_id,
                'originalFilename' => $stored->original_filename,
                'publicUrl' => $stored->public_url,
                'mimeType' => $stored->mime_type,
                'size' => $stored->size,
                'kind' => $request->validated('kind', 'attachment'),
            ],
        ], 201);
    }

    public function comments(Publication $publication): JsonResponse
    {
        $comments = PublicationComment::query()
            ->with('user.profile')
            ->where('publication_id', $publication->id)
            ->latest()
            ->limit(200)
            ->get()
            ->map(fn (PublicationComment $comment): array => [
                'id' => $comment->id,
                'body' => $comment->body,
                'parentId' => $comment->parent_id,
                'createdAt' => $comment->created_at?->toISOString(),
                'user' => [
                    'id' => $comment->user?->id,
                    'name' => $comment->user?->name,
                    'initials' => $comment->user?->profile?->initials ?? 'RD',
                ],
            ])
            ->values();

        return response()->json(['comments' => $comments]);
    }

    public function storeComment(StorePublicationCommentRequest $request, Publication $publication): JsonResponse
    {
        $data = $request->validated();

        $comment = PublicationComment::query()->create([
            'publication_id' => $publication->id,
            'user_id' => $request->user()->id,
            'parent_id' => $data['parentId'] ?? null,
            'body' => $data['body'],
        ]);

        $publication->increment('comments_count');

        return response()->json([
            'comment' => [
                'id' => $comment->id,
                'body' => $comment->body,
                'parentId' => $comment->parent_id,
                'createdAt' => $comment->created_at?->toISOString(),
            ],
        ], 201);
    }

    public function like(Request $request, Publication $publication): JsonResponse
    {
        PublicationLike::query()->firstOrCreate([
            'publication_id' => $publication->id,
            'user_id' => $request->user()->id,
        ], [
            'created_at' => now(),
        ]);
        $publication->likes_count = PublicationLike::query()->where('publication_id', $publication->id)->count();
        $publication->save();

        return response()->json(['liked' => true, 'likesCount' => $publication->likes_count]);
    }

    public function unlike(Request $request, Publication $publication): JsonResponse
    {
        PublicationLike::query()
            ->where('publication_id', $publication->id)
            ->where('user_id', $request->user()->id)
            ->delete();
        $publication->likes_count = PublicationLike::query()->where('publication_id', $publication->id)->count();
        $publication->save();

        return response()->json(['liked' => false, 'likesCount' => $publication->likes_count]);
    }

    public function savePublication(Request $request, Publication $publication): JsonResponse
    {
        PublicationSave::query()->firstOrCreate([
            'publication_id' => $publication->id,
            'user_id' => $request->user()->id,
        ], [
            'created_at' => now(),
        ]);

        return response()->json(['saved' => true]);
    }

    public function unsavePublication(Request $request, Publication $publication): JsonResponse
    {
        PublicationSave::query()
            ->where('publication_id', $publication->id)
            ->where('user_id', $request->user()->id)
            ->delete();

        return response()->json(['saved' => false]);
    }

    public function view(Request $request, Publication $publication): JsonResponse
    {
        $user = $request->user() ?? auth('sanctum')->user();

        PublicationView::query()->create([
            'publication_id' => $publication->id,
            'user_id' => $user?->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'viewed_at' => now(),
        ]);

        return response()->json(['viewRecorded' => true]);
    }

    private function authorizePublicationCreation(Request $request): void
    {
        abort_unless(in_array($request->user()?->role, ['admin', 'editor', 'professor', 'advogado', 'aluno'], true), 403);
    }

    private function syncTagsAndFiles(Publication $publication, array $data): void
    {
        DB::transaction(function () use ($publication, $data): void {
            $autoTags = $this->extractHashtags(implode("\n", array_filter([
                (string) ($data['title'] ?? ''),
                (string) ($data['excerpt'] ?? ''),
                (string) ($data['content'] ?? ''),
                (string) ($data['body'] ?? ''),
            ])));

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

    private function uniqueSlugFor(string $title): string
    {
        $base = Str::slug($title);
        if ($base === '') {
            return Str::lower((string) Str::ulid());
        }

        $slug = $base;
        $suffix = 1;

        while (Publication::query()->where('slug', $slug)->exists()) {
            $suffix++;
            $slug = "{$base}-{$suffix}";
        }

        return $slug;
    }
}
