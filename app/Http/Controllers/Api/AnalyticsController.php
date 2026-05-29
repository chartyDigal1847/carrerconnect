<?php

namespace App\Http\Controllers\Api;

use App\Services\Security\RoleCapabilityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AnalyticsController
{
    public function __construct(private RoleCapabilityService $capabilities) {}

    public function facultyActivity(Request $request): JsonResponse
    {
        if (! $this->capabilities->can(auth()->user(), 'analytics.*')) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $department = $request->query('department');
        $days = (int) $request->query('days', 30);

        $rows = DB::select('CALL sp_faculty_activity_report(?, ?)', [$department, $days]);

        return response()->json(['data' => $rows]);
    }

    public function announcementDelivery(Request $request): JsonResponse
    {
        if (! $this->capabilities->can(auth()->user(), 'analytics.*')) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $id = $request->query('announcement_id');
        $rows = DB::select('CALL sp_announcement_delivery_stats(?)', [$id]);

        return response()->json(['data' => $rows]);
    }

    public function engagement(): JsonResponse
    {
        if (! $this->capabilities->can(auth()->user(), 'analytics.*')) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        return response()->json([
            'boards' => DB::table('v_communication_engagement')->get(),
            'departments' => DB::table('v_department_announcement_analytics')->get(),
            'resources' => DB::table('v_resource_usage_analytics')->get(),
        ]);
    }
}
