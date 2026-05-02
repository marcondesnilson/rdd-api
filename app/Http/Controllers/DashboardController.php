<?php

namespace App\Http\Controllers;

use App\Models\Publication;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function metrics(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $publicationsCount = Publication::query()
            ->where('user_id', $userId)
            ->where('post_type', 'publication')
            ->count();

        $viewsCount = DB::table('publication_views')
            ->join('publications', 'publications.id', '=', 'publication_views.publication_id')
            ->where('publications.user_id', $userId)
            ->where('publications.post_type', 'publication')
            ->whereNull('publication_views.deleted_at')
            ->whereNull('publications.deleted_at')
            ->count();

        $likesCount = DB::table('publication_likes')
            ->join('publications', 'publications.id', '=', 'publication_likes.publication_id')
            ->where('publications.user_id', $userId)
            ->where('publications.post_type', 'publication')
            ->whereNull('publication_likes.deleted_at')
            ->whereNull('publications.deleted_at')
            ->count();

        $followersCount = DB::table('user_follows')
            ->where('followee_id', $userId)
            ->whereNull('deleted_at')
            ->count();

        return response()->json([
            'metrics' => [
                'viewsCount' => $viewsCount,
                'likesCount' => $likesCount,
                'followersCount' => $followersCount,
                'publicationsCount' => $publicationsCount,
            ],
        ]);
    }
}
