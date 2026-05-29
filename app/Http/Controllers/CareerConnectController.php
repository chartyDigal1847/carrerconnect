<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Announcement;
use App\Models\Application;
use App\Models\Internship;
use App\Models\Job;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class CareerConnectController extends Controller
{
    // ─── Role helpers ────────────────────────────────────────────────────────

    private function getRole(Request $request): string
    {
        $role = $request->header('X-CareerConnect-Role')
            ?? $request->query('role', 'student');

        return in_array($role, ['admin', 'hr', 'student']) ? $role : 'student';
    }

    private function canEdit(string $role): bool
    {
        return in_array($role, ['admin', 'hr']);
    }

    private function canDelete(string $role): bool
    {
        return $role === 'admin';
    }

    private function canApprove(string $role): bool
    {
        return in_array($role, ['admin', 'hr']);
    }

    // ─── Bootstrap ───────────────────────────────────────────────────────────

    public function bootstrap(Request $request): JsonResponse
    {
        $role = $this->getRole($request);

        $jobs = Job::orderByDesc('created_at')->get()->map(fn($j) => [
            'id'          => $j->id,
            'title'       => $j->title,
            'companyName' => $j->company_name,
            'location'    => $j->location,
            'jobType'     => $j->job_type,
            'workSetup'   => $j->work_setup,
            'description' => $j->description,
            'status'      => $j->status,
        ]);

        $internships = Internship::orderByDesc('created_at')->get()->map(fn($i) => [
            'id'            => $i->id,
            'title'         => $i->title,
            'companyName'   => $i->company_name,
            'location'      => $i->location,
            'workSetup'     => $i->work_setup,
            'durationWeeks' => $i->duration_weeks,
            'description'   => $i->description,
            'status'        => $i->status,
        ]);

        $applicationsQuery = Application::orderByDesc('date_applied');
        $applications = $applicationsQuery->get()->map(fn($a) => [
            'id'          => $a->id,
            'studentName' => $a->student_name,
            'email'       => $a->email,
            'phone'       => $a->phone,
            'resume'      => $a->resume,
            'position'    => $a->position,
            'status'      => $a->status,
            'dateApplied' => $a->date_applied?->format('Y-m-d'),
            'submittedBy' => $a->submitted_by ?? 'student',
            'notes'       => $a->notes,
        ]);

        $announcements = Announcement::orderByDesc('date')->get()->map(fn($a) => [
            'id'          => $a->id,
            'title'       => $a->title,
            'date'        => $a->date?->format('Y-m-d'),
            'category'    => $a->category,
            'description' => $a->description,
            'fullContent' => $a->full_content,
        ]);

        $activityLog = ActivityLog::orderByDesc('at')->limit(25)->get()->map(fn($l) => [
            'message' => $l->message,
            'type'    => $l->type,
            'at'      => $l->at?->toIso8601String(),
        ]);

        return response()->json([
            'role'        => $role,
            'jobs'        => $jobs,
            'internships' => $internships,
            'applications' => $applications,
            'announcements' => $announcements,
            'activityLog' => $activityLog,
        ]);
    }

    // ─── Jobs ────────────────────────────────────────────────────────────────

    public function storeJob(Request $request): JsonResponse
    {
        $role = $this->getRole($request);
        if (!$this->canEdit($role)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $data = $request->validate([
            'title'       => 'required|string|max:255',
            'companyName' => 'required|string|max:255',
            'location'    => 'required|string|max:255',
            'jobType'     => 'required|string|max:100',
            'workSetup'   => 'required|string|max:100',
            'description' => 'required|string',
        ]);

        $job = Job::create([
            'title'        => $data['title'],
            'company_name' => $data['companyName'],
            'location'     => $data['location'],
            'job_type'     => $data['jobType'],
            'work_setup'   => $data['workSetup'],
            'description'  => $data['description'],
            'status'       => 'open',
        ]);

        ActivityLog::record("New job posted: {$job->title} at {$job->company_name}", 'blue');

        return response()->json([
            'id'          => $job->id,
            'title'       => $job->title,
            'companyName' => $job->company_name,
            'location'    => $job->location,
            'jobType'     => $job->job_type,
            'workSetup'   => $job->work_setup,
            'description' => $job->description,
            'status'      => $job->status,
        ], 201);
    }

    public function updateJob(Request $request, int $id): JsonResponse
    {
        $role = $this->getRole($request);
        if (!$this->canEdit($role)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $job = Job::findOrFail($id);

        $data = $request->validate([
            'title'       => 'sometimes|required|string|max:255',
            'companyName' => 'sometimes|required|string|max:255',
            'location'    => 'sometimes|required|string|max:255',
            'jobType'     => 'sometimes|required|string|max:100',
            'workSetup'   => 'sometimes|required|string|max:100',
            'description' => 'sometimes|required|string',
        ]);

        $job->update([
            'title'        => $data['title']       ?? $job->title,
            'company_name' => $data['companyName'] ?? $job->company_name,
            'location'     => $data['location']    ?? $job->location,
            'job_type'     => $data['jobType']     ?? $job->job_type,
            'work_setup'   => $data['workSetup']   ?? $job->work_setup,
            'description'  => $data['description'] ?? $job->description,
        ]);

        ActivityLog::record("Job updated: {$job->title}", 'amber');

        return response()->json([
            'id'          => $job->id,
            'title'       => $job->title,
            'companyName' => $job->company_name,
            'location'    => $job->location,
            'jobType'     => $job->job_type,
            'workSetup'   => $job->work_setup,
            'description' => $job->description,
            'status'      => $job->status,
        ]);
    }

    public function updateJobStatus(Request $request, int $id): JsonResponse
    {
        $role = $this->getRole($request);
        if (!$this->canEdit($role)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $job = Job::findOrFail($id);
        $data = $request->validate(['status' => 'required|in:open,closed']);
        $job->update(['status' => $data['status']]);

        ActivityLog::record("Job \"{$job->title}\" marked as {$data['status']}", 'amber');

        return response()->json(['status' => $job->status]);
    }

    public function destroyJob(Request $request, int $id): Response
    {
        $role = $this->getRole($request);
        if (!$this->canDelete($role)) {
            return response('Unauthorized', 403);
        }

        $job = Job::findOrFail($id);
        $title = $job->title;
        $job->delete();

        ActivityLog::record("Job deleted: {$title}", 'red');

        return response()->noContent();
    }

    // ─── Internships ─────────────────────────────────────────────────────────

    public function storeInternship(Request $request): JsonResponse
    {
        $role = $this->getRole($request);
        if (!$this->canEdit($role)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $data = $request->validate([
            'title'         => 'required|string|max:255',
            'companyName'   => 'required|string|max:255',
            'location'      => 'required|string|max:255',
            'workSetup'     => 'required|string|max:100',
            'durationWeeks' => 'required|integer|min:1',
            'description'   => 'required|string',
        ]);

        $internship = Internship::create([
            'title'          => $data['title'],
            'company_name'   => $data['companyName'],
            'location'       => $data['location'],
            'work_setup'     => $data['workSetup'],
            'duration_weeks' => $data['durationWeeks'],
            'description'    => $data['description'],
            'status'         => 'open',
        ]);

        ActivityLog::record("New internship posted: {$internship->title} at {$internship->company_name}", 'blue');

        return response()->json([
            'id'            => $internship->id,
            'title'         => $internship->title,
            'companyName'   => $internship->company_name,
            'location'      => $internship->location,
            'workSetup'     => $internship->work_setup,
            'durationWeeks' => $internship->duration_weeks,
            'description'   => $internship->description,
            'status'        => $internship->status,
        ], 201);
    }

    public function updateInternship(Request $request, int $id): JsonResponse
    {
        $role = $this->getRole($request);
        if (!$this->canEdit($role)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $internship = Internship::findOrFail($id);

        $data = $request->validate([
            'title'         => 'sometimes|required|string|max:255',
            'companyName'   => 'sometimes|required|string|max:255',
            'location'      => 'sometimes|required|string|max:255',
            'workSetup'     => 'sometimes|required|string|max:100',
            'durationWeeks' => 'sometimes|required|integer|min:1',
            'description'   => 'sometimes|required|string',
        ]);

        $internship->update([
            'title'          => $data['title']         ?? $internship->title,
            'company_name'   => $data['companyName']   ?? $internship->company_name,
            'location'       => $data['location']      ?? $internship->location,
            'work_setup'     => $data['workSetup']     ?? $internship->work_setup,
            'duration_weeks' => $data['durationWeeks'] ?? $internship->duration_weeks,
            'description'    => $data['description']   ?? $internship->description,
        ]);

        ActivityLog::record("Internship updated: {$internship->title}", 'amber');

        return response()->json([
            'id'            => $internship->id,
            'title'         => $internship->title,
            'companyName'   => $internship->company_name,
            'location'      => $internship->location,
            'workSetup'     => $internship->work_setup,
            'durationWeeks' => $internship->duration_weeks,
            'description'   => $internship->description,
            'status'        => $internship->status,
        ]);
    }

    public function updateInternshipStatus(Request $request, int $id): JsonResponse
    {
        $role = $this->getRole($request);
        if (!$this->canEdit($role)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $internship = Internship::findOrFail($id);
        $data = $request->validate(['status' => 'required|in:open,closed']);
        $internship->update(['status' => $data['status']]);

        ActivityLog::record("Internship \"{$internship->title}\" marked as {$data['status']}", 'amber');

        return response()->json(['status' => $internship->status]);
    }

    public function destroyInternship(Request $request, int $id): Response
    {
        $role = $this->getRole($request);
        if (!$this->canDelete($role)) {
            return response('Unauthorized', 403);
        }

        $internship = Internship::findOrFail($id);
        $title = $internship->title;
        $internship->delete();

        ActivityLog::record("Internship deleted: {$title}", 'red');

        return response()->noContent();
    }

    // ─── Applications ────────────────────────────────────────────────────────

    public function storeApplication(Request $request): JsonResponse
    {
        $role = $this->getRole($request);

        // Only students can submit applications through this endpoint.
        // Admins manage applications via approve/reject/delete — not by creating them.
        if ($role !== 'student') {
            return response()->json(['message' => 'Only students can submit applications.'], 403);
        }

        $data = $request->validate([
            'jobId'        => 'nullable|integer|exists:career_jobs,id',
            'internshipId' => 'nullable|integer|exists:internships,id',
            'studentName'  => 'required|string|max:255',
            'email'        => 'required|email|max:255',
            'phone'        => 'nullable|string|max:50',
            'resume'       => 'nullable|string|max:255',
        ]);

        // Determine position name
        $position = 'Unknown Position';
        $jobId = null;
        $internshipId = null;

        if (!empty($data['jobId'])) {
            $job = Job::find($data['jobId']);
            $position = $job?->title ?? $position;
            $jobId = $data['jobId'];
        } elseif (!empty($data['internshipId'])) {
            $internship = Internship::find($data['internshipId']);
            $position = $internship?->title ?? $position;
            $internshipId = $data['internshipId'];
        }

        $application = Application::create([
            'student_name'  => $data['studentName'],
            'email'         => $data['email'],
            'phone'         => $data['phone']  ?? null,
            'resume'        => $data['resume'] ?? null,
            'job_id'        => $jobId,
            'internship_id' => $internshipId,
            'position'      => $position,
            'status'        => 'pending',
            'date_applied'  => now()->toDateString(),
            'submitted_by'  => 'student',
            'notes'         => null,
        ]);

        ActivityLog::record("New application: {$application->student_name} applied for {$position}", 'green');

        return response()->json([
            'id'          => $application->id,
            'studentName' => $application->student_name,
            'email'       => $application->email,
            'phone'       => $application->phone,
            'resume'      => $application->resume,
            'position'    => $application->position,
            'status'      => $application->status,
            'dateApplied' => $application->date_applied?->format('Y-m-d'),
            'submittedBy' => $application->submitted_by,
        ], 201);
    }

    public function updateApplicationStatus(Request $request, int $id): JsonResponse
    {
        $role = $this->getRole($request);
        if (!$this->canApprove($role)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $application = Application::findOrFail($id);
        $data = $request->validate(['status' => 'required|in:pending,accepted,rejected']);
        $application->update(['status' => $data['status']]);

        $color = match ($data['status']) {
            'accepted' => 'green',
            'rejected' => 'red',
            default    => 'amber',
        };
        ActivityLog::record("Application by {$application->student_name} for {$application->position} was {$data['status']}", $color);

        return response()->json([
            'id'     => $application->id,
            'status' => $application->status,
        ]);
    }

    public function destroyApplication(Request $request, int $id): Response
    {
        $role = $this->getRole($request);
        if (!$this->canDelete($role)) {
            return response('Unauthorized', 403);
        }

        $application = Application::findOrFail($id);
        $name = $application->student_name;
        $position = $application->position;
        $application->delete();

        ActivityLog::record("Application by {$name} for {$position} was deleted", 'red');

        return response()->noContent();
    }

    // ─── Announcements ───────────────────────────────────────────────────────

    public function storeAnnouncement(Request $request): JsonResponse
    {
        $role = $this->getRole($request);
        if (!$this->canEdit($role)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $data = $request->validate([
            'title'       => 'required|string|max:255',
            'date'        => 'required|date',
            'category'    => 'nullable|in:job-fair,deadline,workshop,news',
            'description' => 'required|string',
            'fullContent' => 'nullable|string',
        ]);

        $announcement = Announcement::create([
            'title'        => $data['title'],
            'date'         => $data['date'],
            'category'     => $data['category'] ?? 'news',
            'description'  => $data['description'],
            'full_content' => $data['fullContent'] ?? null,
        ]);

        ActivityLog::record("Announcement posted: {$announcement->title}", 'purple');

        return response()->json([
            'id'          => $announcement->id,
            'title'       => $announcement->title,
            'date'        => $announcement->date?->format('Y-m-d'),
            'category'    => $announcement->category,
            'description' => $announcement->description,
            'fullContent' => $announcement->full_content,
        ], 201);
    }

    public function updateAnnouncement(Request $request, int $id): JsonResponse
    {
        $role = $this->getRole($request);
        if (!$this->canEdit($role)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $announcement = Announcement::findOrFail($id);

        $data = $request->validate([
            'title'       => 'sometimes|required|string|max:255',
            'date'        => 'sometimes|required|date',
            'category'    => 'sometimes|nullable|in:job-fair,deadline,workshop,news',
            'description' => 'sometimes|required|string',
            'fullContent' => 'sometimes|nullable|string',
        ]);

        $announcement->update([
            'title'        => $data['title']       ?? $announcement->title,
            'date'         => $data['date']         ?? $announcement->date,
            'category'     => $data['category']    ?? $announcement->category,
            'description'  => $data['description'] ?? $announcement->description,
            'full_content' => $data['fullContent'] ?? $announcement->full_content,
        ]);

        ActivityLog::record("Announcement updated: {$announcement->title}", 'amber');

        return response()->json([
            'id'          => $announcement->id,
            'title'       => $announcement->title,
            'date'        => $announcement->date?->format('Y-m-d'),
            'category'    => $announcement->category,
            'description' => $announcement->description,
            'fullContent' => $announcement->full_content,
        ]);
    }

    public function destroyAnnouncement(Request $request, int $id): Response
    {
        $role = $this->getRole($request);
        if (!$this->canDelete($role)) {
            return response('Unauthorized', 403);
        }

        $announcement = Announcement::findOrFail($id);
        $title = $announcement->title;
        $announcement->delete();

        ActivityLog::record("Announcement deleted: {$title}", 'red');

        return response()->noContent();
    }
}
