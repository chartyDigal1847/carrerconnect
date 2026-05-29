<?php

namespace App\Http\Controllers\Api;

use App\Models\Application;
use App\Models\FacultyUser;
use App\Models\Internship;
use App\Models\Job;
use App\Models\RecruitmentActivityLog;
use App\Services\Security\RoleCapabilityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class OpportunityController
{
    public function __construct(private RoleCapabilityService $capabilities) {}

    public function bootstrap(Request $request): JsonResponse
    {
        $user = $this->user();
        $this->denyUnless($user, 'opportunities.read');

        $jobs = Job::orderByDesc('created_at')->get()->map(fn ($j) => $this->formatJob($j));
        $internships = Internship::orderByDesc('created_at')->get()->map(fn ($i) => $this->formatInternship($i));
        $applications = $this->applicationsQuery($user)->get()->map(fn ($a) => $this->formatApplication($a));

        $activityLog = [];
        if ($this->capabilities->can($user, 'opportunities.reports')) {
            $activityLog = RecruitmentActivityLog::orderByDesc('logged_at')
                ->limit(25)
                ->get()
                ->map(fn ($l) => [
                    'message' => $l->message,
                    'type' => $l->type,
                    'at' => $l->logged_at?->toIso8601String(),
                ]);
        }

        return response()->json([
            'role' => $user->role,
            'jobs' => $jobs,
            'internships' => $internships,
            'applications' => $applications,
            'activityLog' => $activityLog,
        ]);
    }

    public function reports(Request $request): JsonResponse
    {
        $user = $this->user();
        $this->denyUnless($user, 'opportunities.reports');

        $applications = Application::all();
        $jobs = Job::all();
        $internships = Internship::all();

        $total = $applications->count();
        $accepted = $applications->where('status', 'accepted')->count();
        $pending = $applications->where('status', 'pending')->count();
        $rejected = $applications->where('status', 'rejected')->count();

        return response()->json([
            'total_applications' => $total,
            'accepted' => $accepted,
            'pending' => $pending,
            'rejected' => $rejected,
            'acceptance_rate' => $total > 0 ? round(($accepted / $total) * 100) : 0,
            'open_jobs' => $jobs->where('status', 'open')->count(),
            'closed_jobs' => $jobs->where('status', 'closed')->count(),
            'open_internships' => $internships->where('status', 'open')->count(),
            'closed_internships' => $internships->where('status', 'closed')->count(),
            'applications' => $applications->map(fn ($a) => $this->formatApplication($a)),
        ]);
    }

    public function storeJob(Request $request): JsonResponse
    {
        $user = $this->user();
        $this->denyUnless($user, 'opportunities.manage');

        $data = $request->validate([
            'title' => 'required|string|max:255',
            'companyName' => 'required|string|max:255',
            'location' => 'required|string|max:255',
            'jobType' => 'required|string|max:100',
            'workSetup' => 'required|string|max:100',
            'description' => 'required|string',
        ]);

        $job = Job::create([
            'title' => $data['title'],
            'company_name' => $data['companyName'],
            'location' => $data['location'],
            'job_type' => $data['jobType'],
            'work_setup' => $data['workSetup'],
            'description' => $data['description'],
            'status' => 'open',
        ]);

        RecruitmentActivityLog::record("New job posted: {$job->title} at {$job->company_name}", 'blue');

        return response()->json($this->formatJob($job), 201);
    }

    public function updateJob(Request $request, int $id): JsonResponse
    {
        $user = $this->user();
        $this->denyUnless($user, 'opportunities.manage');

        $job = Job::findOrFail($id);
        $data = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'companyName' => 'sometimes|required|string|max:255',
            'location' => 'sometimes|required|string|max:255',
            'jobType' => 'sometimes|required|string|max:100',
            'workSetup' => 'sometimes|required|string|max:100',
            'description' => 'sometimes|required|string',
        ]);

        $job->update([
            'title' => $data['title'] ?? $job->title,
            'company_name' => $data['companyName'] ?? $job->company_name,
            'location' => $data['location'] ?? $job->location,
            'job_type' => $data['jobType'] ?? $job->job_type,
            'work_setup' => $data['workSetup'] ?? $job->work_setup,
            'description' => $data['description'] ?? $job->description,
        ]);

        RecruitmentActivityLog::record("Job updated: {$job->title}", 'amber');

        return response()->json($this->formatJob($job));
    }

    public function updateJobStatus(Request $request, int $id): JsonResponse
    {
        $user = $this->user();
        $this->denyUnless($user, 'opportunities.manage');

        $job = Job::findOrFail($id);
        $data = $request->validate(['status' => 'required|in:open,closed']);
        $job->update(['status' => $data['status']]);

        RecruitmentActivityLog::record("Job \"{$job->title}\" marked as {$data['status']}", 'amber');

        return response()->json(['status' => $job->status]);
    }

    public function destroyJob(Request $request, int $id): Response
    {
        $user = $this->user();
        $this->denyUnless($user, 'opportunities.delete');

        $job = Job::findOrFail($id);
        $title = $job->title;
        $job->delete();

        RecruitmentActivityLog::record("Job deleted: {$title}", 'red');

        return response()->noContent();
    }

    public function storeInternship(Request $request): JsonResponse
    {
        $user = $this->user();
        $this->denyUnless($user, 'opportunities.manage');

        $data = $request->validate([
            'title' => 'required|string|max:255',
            'companyName' => 'required|string|max:255',
            'location' => 'required|string|max:255',
            'workSetup' => 'required|string|max:100',
            'durationWeeks' => 'required|integer|min:1',
            'description' => 'required|string',
        ]);

        $internship = Internship::create([
            'title' => $data['title'],
            'company_name' => $data['companyName'],
            'location' => $data['location'],
            'work_setup' => $data['workSetup'],
            'duration_weeks' => $data['durationWeeks'],
            'description' => $data['description'],
            'status' => 'open',
        ]);

        RecruitmentActivityLog::record("New internship posted: {$internship->title} at {$internship->company_name}", 'blue');

        return response()->json($this->formatInternship($internship), 201);
    }

    public function updateInternship(Request $request, int $id): JsonResponse
    {
        $user = $this->user();
        $this->denyUnless($user, 'opportunities.manage');

        $internship = Internship::findOrFail($id);
        $data = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'companyName' => 'sometimes|required|string|max:255',
            'location' => 'sometimes|required|string|max:255',
            'workSetup' => 'sometimes|required|string|max:100',
            'durationWeeks' => 'sometimes|required|integer|min:1',
            'description' => 'sometimes|required|string',
        ]);

        $internship->update([
            'title' => $data['title'] ?? $internship->title,
            'company_name' => $data['companyName'] ?? $internship->company_name,
            'location' => $data['location'] ?? $internship->location,
            'work_setup' => $data['workSetup'] ?? $internship->work_setup,
            'duration_weeks' => $data['durationWeeks'] ?? $internship->duration_weeks,
            'description' => $data['description'] ?? $internship->description,
        ]);

        RecruitmentActivityLog::record("Internship updated: {$internship->title}", 'amber');

        return response()->json($this->formatInternship($internship));
    }

    public function updateInternshipStatus(Request $request, int $id): JsonResponse
    {
        $user = $this->user();
        $this->denyUnless($user, 'opportunities.manage');

        $internship = Internship::findOrFail($id);
        $data = $request->validate(['status' => 'required|in:open,closed']);
        $internship->update(['status' => $data['status']]);

        RecruitmentActivityLog::record("Internship \"{$internship->title}\" marked as {$data['status']}", 'amber');

        return response()->json(['status' => $internship->status]);
    }

    public function destroyInternship(Request $request, int $id): Response
    {
        $user = $this->user();
        $this->denyUnless($user, 'opportunities.delete');

        $internship = Internship::findOrFail($id);
        $title = $internship->title;
        $internship->delete();

        RecruitmentActivityLog::record("Internship deleted: {$title}", 'red');

        return response()->noContent();
    }

    public function storeApplication(Request $request): JsonResponse
    {
        $user = $this->user();
        $this->denyUnless($user, 'opportunities.apply');

        if ($user->role !== 'student') {
            return response()->json(['error' => 'Only students can submit applications'], 403);
        }

        $data = $request->validate([
            'jobId' => 'nullable|integer|exists:career_jobs,id',
            'internshipId' => 'nullable|integer|exists:internships,id',
            'phone' => 'nullable|string|max:50',
            'resume' => 'nullable|string|max:255',
        ]);

        if (empty($data['jobId']) && empty($data['internshipId'])) {
            return response()->json(['error' => 'jobId or internshipId is required'], 422);
        }

        [$position, $jobId, $internshipId] = $this->resolvePosition($data);

        $application = Application::create([
            'student_name' => $user->name,
            'email' => strtolower((string) $user->email),
            'phone' => $data['phone'] ?? null,
            'resume' => $data['resume'] ?? null,
            'job_id' => $jobId,
            'internship_id' => $internshipId,
            'position' => $position,
            'status' => 'pending',
            'date_applied' => now()->toDateString(),
            'submitted_by' => 'student',
        ]);

        RecruitmentActivityLog::record("New application: {$application->student_name} applied for {$position}", 'green');

        return response()->json($this->formatApplication($application), 201);
    }

    public function updateApplicationStatus(Request $request, int $id): JsonResponse
    {
        $user = $this->user();
        $this->denyUnless($user, 'opportunities.approve');

        $application = Application::findOrFail($id);
        $data = $request->validate(['status' => 'required|in:pending,accepted,rejected']);
        $application->update(['status' => $data['status']]);

        $color = match ($data['status']) {
            'accepted' => 'green',
            'rejected' => 'red',
            default => 'amber',
        };
        RecruitmentActivityLog::record(
            "Application by {$application->student_name} for {$application->position} was {$data['status']}",
            $color
        );

        return response()->json([
            'id' => $application->id,
            'status' => $application->status,
        ]);
    }

    public function destroyApplication(Request $request, int $id): Response
    {
        $user = $this->user();
        $this->denyUnless($user, 'opportunities.delete');

        $application = Application::findOrFail($id);
        $name = $application->student_name;
        $position = $application->position;
        $application->delete();

        RecruitmentActivityLog::record("Application by {$name} for {$position} was deleted", 'red');

        return response()->noContent();
    }

    private function user(): FacultyUser
    {
        $user = auth('faculty')->user();
        if (! $user instanceof FacultyUser) {
            abort(401, 'Unauthorized');
        }

        return $user;
    }

    private function denyUnless(FacultyUser $user, string $capability): void
    {
        if (! $this->capabilities->can($user, $capability)) {
            abort(403, 'Insufficient permissions');
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{0: string, 1: ?int, 2: ?int}
     */
    private function resolvePosition(array $data): array
    {
        $position = 'Unknown Position';
        $jobId = null;
        $internshipId = null;

        if (! empty($data['jobId'])) {
            $job = Job::find($data['jobId']);
            $position = $job?->title ?? $position;
            $jobId = (int) $data['jobId'];
        } elseif (! empty($data['internshipId'])) {
            $internship = Internship::find($data['internshipId']);
            $position = $internship?->title ?? $position;
            $internshipId = (int) $data['internshipId'];
        }

        return [$position, $jobId, $internshipId];
    }

    private function applicationsQuery(FacultyUser $user)
    {
        $query = Application::orderByDesc('date_applied');

        if ($user->role === 'student') {
            $query->whereRaw('LOWER(email) = ?', [strtolower((string) $user->email)]);
        }

        return $query;
    }

    private function formatJob(Job $job): array
    {
        return [
            'id' => $job->id,
            'title' => $job->title,
            'companyName' => $job->company_name,
            'location' => $job->location,
            'jobType' => $job->job_type,
            'workSetup' => $job->work_setup,
            'description' => $job->description,
            'status' => $job->status,
        ];
    }

    private function formatInternship(Internship $internship): array
    {
        return [
            'id' => $internship->id,
            'title' => $internship->title,
            'companyName' => $internship->company_name,
            'location' => $internship->location,
            'workSetup' => $internship->work_setup,
            'durationWeeks' => $internship->duration_weeks,
            'description' => $internship->description,
            'status' => $internship->status,
        ];
    }

    private function formatApplication(Application $application): array
    {
        return [
            'id' => $application->id,
            'studentName' => $application->student_name,
            'email' => $application->email,
            'phone' => $application->phone,
            'resume' => $application->resume,
            'position' => $application->position,
            'status' => $application->status,
            'dateApplied' => $application->date_applied?->format('Y-m-d'),
            'submittedBy' => $application->submitted_by ?? 'student',
            'notes' => $application->notes,
            'jobId' => $application->job_id,
            'internshipId' => $application->internship_id,
        ];
    }
}
