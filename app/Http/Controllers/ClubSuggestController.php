<?php

namespace App\Http\Controllers;

use App\Http\Resources\Search\SuggestClubResource;
use App\Services\Club\ClubSuggestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ClubSuggestController extends Controller
{
    public function __construct(private ClubSuggestService $service) {}

    /**
     * GET /api/clubs/suggest
     *
     * Headers:
     *   X-User-Lat, X-User-Lng (optional - nếu có sẽ trả nhóm nearby)
     * Query:
     *   lat, lng (optional - fallback nếu không có header)
     */
    public function suggest(Request $request): JsonResponse
    {
        $userId = Auth::id();
        if (!$userId) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        // Resolve location: ưu tiên X-User-* headers (convention hiện tại),
        // fallback ?lat= & ?lng= query string.
        $lat = $request->header('X-User-Lat') ?? $request->query('lat');
        $lng = $request->header('X-User-Lng') ?? $request->query('lng');
        $lat = is_numeric($lat) ? (float) $lat : null;
        $lng = is_numeric($lng) ? (float) $lng : null;

        $clubs = $this->service->suggest($userId, $lat, $lng);

        return response()->json([
            'data' => SuggestClubResource::collection($clubs),
            'meta' => ['total' => $clubs->count()],
        ]);
    }
}