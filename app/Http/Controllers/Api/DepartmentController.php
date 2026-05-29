<?php

namespace App\Http\Controllers\Api;

use App\Models\Announcement;
use App\Models\CommunicationBoard;
use App\Models\Department;
use App\Models\FacultyUser;
use Illuminate\Http\JsonResponse;

class DepartmentController
{
    public function index(): JsonResponse
    {
        $departments = Department::where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'description', 'contact_email', 'location']);

        return response()->json($departments);
    }

    public function show(Department $department): JsonResponse
    {
        $user = auth()->user();

        if ($user->role !== 'admin' && $user->department !== $department->code) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $faculty = FacultyUser::where('department', $department->code)
            ->where('is_active', true)
            ->where('role', '!=', 'student')
            ->get(['id', 'name', 'email', 'role']);

        $announcements = Announcement::where('is_active', true)
            ->where(function ($q) use ($department) {
                $q->where('visibility', 'all')
                    ->orWhere(function ($q2) use ($department) {
                        $q2->where('visibility', 'department')
                            ->where('department_id', $department->id);
                    });
            })
            ->orderByDesc('published_at')
            ->limit(10)
            ->with('author:id,name')
            ->get(['id', 'title', 'priority', 'published_at', 'author_id']);

        $boards = CommunicationBoard::where('is_active', true)
            ->where(function ($q) use ($department) {
                $q->where('visibility', 'all')
                    ->orWhere('department_id', $department->id);
            })
            ->with('creator:id,name')
            ->get(['id', 'name', 'description', 'visibility', 'creator_id', 'posts_count']);

        return response()->json([
            'department' => $department,
            'faculty_count' => $faculty->count(),
            'faculty' => $faculty,
            'announcements' => $announcements,
            'boards' => $boards,
        ]);
    }
}
