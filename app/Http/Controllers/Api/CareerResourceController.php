<?php

namespace App\Http\Controllers\Api;

use App\Models\CareerResource;
use App\Models\ResourceCategory;
use App\Models\ActivityLog;
use App\Services\Domain\EventOutboxService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

class CareerResourceController
{
    public function __construct(private EventOutboxService $events) {}
    public function index(Request $request): JsonResponse
    {
        $category = $request->query('category');
        $featured = $request->boolean('featured');

        $query = CareerResource::query()
            ->where('is_approved', true)
            ->with('category', 'author');

        if ($category) {
            $query->where('category_id', $category);
        }

        if ($featured) {
            $query->where('is_featured', true);
        }

        $resources = $query
            ->orderBy('is_featured', 'desc')
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json($resources);
    }

    public function show(CareerResource $resource): JsonResponse
    {
        if (!$resource->is_approved) {
            return response()->json(['error' => 'Not found'], 404);
        }

        $resource->incrementViews();
        $resource->load('category', 'author');

        return response()->json($resource);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'category_id' => 'required|exists:resource_categories,id',
            'resource_type' => 'required|in:pdf,link,video,document,guide',
            'external_url' => 'url',
            'thumbnail' => 'image|max:2048',
        ]);

        $resource = CareerResource::create([
            ...$validated,
            'author_id' => auth()->id(),
            'is_approved' => auth()->user()->role === 'admin',
        ]);

        ActivityLog::create([
            'user_id' => auth()->id(),
            'action' => 'created',
            'entity_type' => 'resource',
            'entity_id' => $resource->id,
            'ip_address' => request()->ip(),
        ]);

        $this->events->record('ResourceUploaded', [
            'resource_id' => $resource->id,
            'title' => $resource->title,
            'category_id' => $resource->category_id,
            'author_id' => $resource->author_id,
        ]);

        return response()->json($resource, 201);
    }

    public function download(CareerResource $resource): JsonResponse
    {
        if (!$resource->is_approved || !$resource->file_path) {
            return response()->json(['error' => 'Not available'], 404);
        }

        $resource->incrementDownloads();

        return response()->json([
            'download_url' => Storage::url($resource->file_path),
        ]);
    }

    public function categories(): JsonResponse
    {
        $categories = ResourceCategory::where('is_active', true)
            ->with('resources')
            ->get();

        return response()->json($categories);
    }
}
