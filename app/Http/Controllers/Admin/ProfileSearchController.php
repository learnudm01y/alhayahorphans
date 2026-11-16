<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\SearchService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ProfileSearchController extends Controller
{
    public function __construct(private SearchService $searchService)
    {
    }

    public function quickSearch(Request $request)
    {
        $input = $request->validate(['query' => 'required|string|min:3|max:255|regex:/[\p{L}\p{N}]/u']);
        try {
            $result = $this->searchService->smartSearch(['search_type' => 'all', 'search_text' => $input['query']], 10);
            return response()->json([
                'success' => true,
                'data' => $result['data'],
                'total' => $result['total_count'],
                'cached' => $result['cached'],
                'all_results_url' => route('search.records.index', ['search_text' => $input['query'], 'request_status_id' => '']),
            ]);
        } catch (\Throwable $e) {
            Log::error('Profile search failed', ['exception' => get_class($e), 'code' => $e->getCode(), 'user_id' => auth()->id()]);
            return response()->json(['success' => false, 'message' => 'تعذر تنفيذ البحث. يرجى المحاولة مرة أخرى.'], 500);
        }
    }
}
