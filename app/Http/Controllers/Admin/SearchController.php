<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Helpers\ResponseHelper;
use App\Models\Matches;
use App\Models\Tournament;
use App\Models\User;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'keyword' => 'required|string|min:1',
            'page' => 'integer|min:1',
            'limit' => 'integer|min:1|max:100',
        ]);

        $keyword = $validated['keyword'];
        $page = $validated['page'] ?? 1;
        $limit = $validated['limit'] ?? 10;

        $users = User::where('full_name', 'like', "%{$keyword}%")
            ->orWhere('email', 'like', "%{$keyword}%")
            ->orWhere('phone', 'like', "%{$keyword}%")
            ->limit($limit)
            ->get(['id', 'full_name', 'avatar_url', 'role']);

        $matches = Matches::where('name_of_match', 'like', "%{$keyword}%")
            ->limit($limit)
            ->get(['id', 'name_of_match as title', 'status', 'created_at']);

        $tournaments = Tournament::where('name', 'like', "%{$keyword}%")
            ->limit($limit)
            ->get(['id', 'name', 'status', 'poster_url', 'created_at']);

        return ResponseHelper::single([
            'users' => $users,
            'matches' => $matches,
            'tournaments' => $tournaments,
        ], 'Tìm kiếm thành công');
    }
}
