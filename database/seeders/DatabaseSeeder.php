<?php

namespace Database\Seeders;

use App\Models\Announcement;
use App\Models\BoardPost;
use App\Models\CareerResource;
use App\Models\CommunicationBoard;
use App\Models\Department;
use App\Models\FacultyUser;
use App\Models\ResourceCategory;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $cs = Department::firstOrCreate(
            ['code' => 'CS'],
            [
                'name' => 'Computer Science',
                'description' => 'Department of Computer Science and Engineering',
                'contact_email' => 'cs@university.edu',
                'location' => 'Engineering Building A',
            ]
        );

        $biz = Department::firstOrCreate(
            ['code' => 'BIZ'],
            [
                'name' => 'Business Administration',
                'description' => 'College of Business Administration',
                'contact_email' => 'biz@university.edu',
                'location' => 'Business Building B',
            ]
        );

        $admin = FacultyUser::firstOrCreate(
            ['sso_id' => 'admin-001'],
            [
                'email' => 'admin@university.edu',
                'name' => 'Admin User',
                'role' => 'admin',
                'is_active' => true,
            ]
        );

        $instructor = FacultyUser::firstOrCreate(
            ['sso_id' => 'instructor-001'],
            [
                'email' => 'dr.smith@university.edu',
                'name' => 'Dr. John Smith',
                'role' => 'instructor',
                'department' => 'CS',
                'is_active' => true,
            ]
        );

        $librarian = FacultyUser::firstOrCreate(
            ['sso_id' => 'librarian-001'],
            [
                'email' => 'lib@university.edu',
                'name' => 'Maria Lopez',
                'role' => 'librarian',
                'department' => 'BIZ',
                'is_active' => true,
            ]
        );

        FacultyUser::firstOrCreate(
            ['sso_id' => 'student-001'],
            [
                'email' => 'student@university.edu',
                'name' => 'Student User',
                'role' => 'student',
                'is_active' => true,
            ]
        );

        FacultyUser::firstOrCreate(
            ['sso_id' => 'admission-001'],
            [
                'email' => 'admissions@university.edu',
                'name' => 'Admission Officer',
                'role' => 'admission_officer',
                'department' => 'BIZ',
                'is_active' => true,
            ]
        );

        FacultyUser::firstOrCreate(
            ['sso_id' => 'career-001'],
            [
                'email' => 'career@university.edu',
                'name' => 'Career Officer',
                'role' => 'career_officer',
                'department' => 'Career Services',
                'is_active' => true,
            ]
        );

        ResourceCategory::firstOrCreate(
            ['slug' => 'career-guides'],
            [
                'name' => 'Career Guides',
                'description' => 'Comprehensive guides for career development',
                'icon' => '📖',
            ]
        );

        ResourceCategory::firstOrCreate(
            ['slug' => 'interview-tips'],
            [
                'name' => 'Interview Tips',
                'description' => 'Interview preparation and tips',
                'icon' => '💼',
            ]
        );

        Announcement::firstOrCreate(
            ['title' => 'Faculty Career Fair Coordination'],
            [
                'content' => 'All department leads should submit booth requirements by May 30. CareerConnect will publish the consolidated schedule once approved.',
                'author_id' => $admin->id,
                'priority' => 'high',
                'visibility' => 'all',
                'published_at' => now()->subDays(2),
                'is_active' => true,
                'is_pinned' => true,
            ]
        );

        Announcement::firstOrCreate(
            ['title' => 'CS Internship Mentorship Sign-up'],
            [
                'content' => 'Instructors in Computer Science may volunteer as internship mentors. Submit your availability through the department board.',
                'author_id' => $instructor->id,
                'department_id' => $cs->id,
                'priority' => 'normal',
                'visibility' => 'department',
                'published_at' => now()->subDay(),
                'is_active' => true,
            ]
        );

        $board = CommunicationBoard::firstOrCreate(
            ['name' => 'CS Faculty Collaboration'],
            [
                'description' => 'Internal coordination for CS faculty on career programs and student support.',
                'creator_id' => $instructor->id,
                'department_id' => $cs->id,
                'visibility' => 'department',
                'is_active' => true,
            ]
        );

        BoardPost::firstOrCreate(
            [
                'board_id' => $board->id,
                'title' => 'Resume clinic schedule draft',
            ],
            [
                'author_id' => $instructor->id,
                'content' => 'Proposed slots: Tue/Thu 2–4 PM in Career Lab. Please comment with conflicts before Friday.',
                'status' => 'approved',
                'is_pinned' => true,
            ]
        );

        $guides = ResourceCategory::where('slug', 'career-guides')->first();

        CareerResource::firstOrCreate(
            ['title' => 'Faculty Guide: Advising on Internships'],
            [
                'description' => 'Institutional guidance for faculty supporting internship placements.',
                'resource_type' => 'document',
                'external_url' => 'https://intranet.university.edu/career/faculty-internship-guide.pdf',
                'category_id' => $guides?->id ?? 1,
                'author_id' => $librarian->id,
                'is_approved' => true,
                'is_featured' => true,
            ]
        );
    }
}
