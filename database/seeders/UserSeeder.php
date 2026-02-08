<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run()
    {
        // Create Admin User
        User::create([
            'name' => 'System Administrator',
            'email' => 'admin@university.edu',
            'password' => Hash::make('admin123'),
            'user_type' => User::TYPE_ADMIN,
            'status' => User::STATUS_ACTIVE,
        ]);

        // Create Supervisors
        $supervisors = [
            [
                'name' => 'John Supervisor',
                'email' => 'supervisor.a@university.edu',
                'assigned_block' => 'A',
            ],
            [
                'name' => 'Jane Supervisor',
                'email' => 'supervisor.b@university.edu',
                'assigned_block' => 'B',
            ],
            [
                'name' => 'Mike Supervisor',
                'email' => 'supervisor.c@university.edu',
                'assigned_block' => 'C',
            ],
        ];

        foreach ($supervisors as $supervisor) {
            User::create([
                'name' => $supervisor['name'],
                'email' => $supervisor['email'],
                'password' => Hash::make('supervisor123'),
                'user_type' => User::TYPE_SUPERVISOR,
                'assigned_block' => $supervisor['assigned_block'],
                'status' => User::STATUS_ACTIVE,
            ]);
        }

        // Create Sample Students
        $students = [
            [
                'name' => 'Alice Johnson',
                'email' => 'alice.johnson@student.university.edu',
                'student_id' => 'STU001',
                'department' => 'Computer Science',
                'year_level' => 2,
            ],
            [
                'name' => 'Bob Smith',
                'email' => 'bob.smith@student.university.edu',
                'student_id' => 'STU002',
                'department' => 'Engineering',
                'year_level' => 1,
            ],
            [
                'name' => 'Carol Davis',
                'email' => 'carol.davis@student.university.edu',
                'student_id' => 'STU003',
                'department' => 'Business',
                'year_level' => 3,
            ],
            [
                'name' => 'David Wilson',
                'email' => 'david.wilson@student.university.edu',
                'student_id' => 'STU004',
                'department' => 'Medicine',
                'year_level' => 4,
            ],
            [
                'name' => 'Eva Brown',
                'email' => 'eva.brown@student.university.edu',
                'student_id' => 'STU005',
                'department' => 'Arts',
                'year_level' => 2,
            ],
        ];

        foreach ($students as $student) {
            User::create([
                'name' => $student['name'],
                'email' => $student['email'],
                'password' => Hash::make('student123'),
                'user_type' => User::TYPE_STUDENT,
                'student_id' => $student['student_id'],
                'department' => $student['department'],
                'year_level' => $student['year_level'],
                'phone' => '+1234567890',
                'status' => User::STATUS_ACTIVE,
            ]);
        }
    }
}