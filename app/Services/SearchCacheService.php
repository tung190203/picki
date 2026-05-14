<?php

namespace App\Services;

use App\Models\SearchLog;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class SearchCacheService
{
    private const HOT_SEARCH_TTL_SECONDS = 300; // 5 minutes
    private const RESULT_CACHE_TTL_SECONDS = 30; // 30 seconds for identical searches

    public function getHotSearches(string $tab, int $limit = 10): Collection
    {
        return Cache::remember(
            "hot_search:{$tab}",
            self::HOT_SEARCH_TTL_SECONDS,
            fn() => SearchLog::where('tab', $tab)
                ->whereNotNull('keyword')
                ->where('keyword', '!=', '')
                ->selectRaw('keyword, COUNT(*) as search_count')
                ->groupBy('keyword')
                ->orderByDesc('search_count')
                ->limit($limit)
                ->get()
                ->pluck('keyword')
        );
    }

    public function getPopularTabs(int $limit = 5): Collection
    {
        return Cache::remember('popular_search_tabs', 300, fn() =>
            SearchLog::selectRaw('tab, COUNT(*) as count')
                ->groupBy('tab')
                ->orderByDesc('count')
                ->limit($limit)
                ->pluck('tab')
        );
    }

    public function logSearch(
        ?int $userId,
        string $tab,
        ?string $keyword,
        ?array $filters,
        ?string $subTab,
        ?int $resultCount
    ): void {
        SearchLog::create([
            'user_id'       => $userId,
            'tab'           => $tab,
            'keyword'       => $keyword,
            'filters_json'  => $filters ? json_encode($filters) : null,
            'sub_tab'       => $subTab,
            'result_count'  => $resultCount ? (string) $resultCount : null,
            'searched_at'   => now(),
        ]);
    }

    public function cacheKey(string $tab, array $params): string
    {
        ksort($params);
        return "search_result:{$tab}:" . md5(json_encode($params));
    }
}
