<?php

namespace App\Http\Controllers\Api;

use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ActivityController
{
    public function index(Request $request): JsonResponse
    {
        $type = $request->query('type');
        $entity = $request->query('entity');

        $query = ActivityLog::query()
            ->orderBy('created_at', 'desc');

        if (auth()->user()->role !== 'admin') {
            $query->where('user_id', auth()->id());
        }

        if ($type) {
            $query->where('action', $type);
        }

        if ($entity) {
            $query->where('entity_type', $entity);
        }

        $activities = $query->with('user')->paginate(50);

        return response()->json($activities);
    }

    public function getSystemActivity(): JsonResponse
    {
        if (auth()->user()->role !== 'admin') {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $activities = ActivityLog::query()
            ->orderBy('created_at', 'desc')
            ->with('user')
            ->limit(100)
            ->get();

        return response()->json($activities);
    }
}
