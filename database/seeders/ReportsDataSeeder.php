<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Room;
use App\Models\DormitoryApplication;
use App\Models\RoomAssignment;
use Carbon\Carbon;

class ReportsDataSeeder extends Seeder
{
    public function run()
    {
        // Create additional students for better report data
        $additionalStudents = [
            ['name' => 'Emma Thompson', 'email' => 'emma.thompson@student.university.edu', 'student_id' => 'STU006', 'department' => 'Computer Science', 'year_level' => 1],
            ['name' => 'James Rodriguez', 'email' => 'james.rodriguez@student.university.edu', 'student_id' => 'STU007', 'department' => 'Engineering', 'year_level' => 2],
            ['name' => 'Sophia Chen', 'email' => 'sophia.chen@student.university.edu', 'student_id' => 'STU008', 'department' => 'Business', 'year_level' => 3],
            ['name' => 'Michael Johnson', 'email' => 'michael.johnson@student.university.edu', 'student_id' => 'STU009', 'department' => 'Medicine', 'year_level' => 4],
            ['name' => 'Isabella Garcia', 'email' => 'isabella.garcia@student.university.edu', 'student_id' => 'STU010', 'department' => 'Arts', 'year_level' => 1],
            ['name' => 'William Lee', 'email' => 'william.lee@student.university.edu', 'student_id' => 'STU011', 'department' => 'Computer Science', 'year_level' => 2],
            ['name' => 'Olivia Martinez', 'email' => 'olivia.martinez@student.university.edu', 'student_id' => 'STU012', 'department' => 'Engineering', 'year_level' => 3],
            ['name' => 'Alexander Davis', 'email' => 'alexander.davis@student.university.edu', 'student_id' => 'STU013', 'department' => 'Business', 'year_level' => 4],
            ['name' => 'Ava Wilson', 'email' => 'ava.wilson@student.university.edu', 'student_id' => 'STU014', 'department' => 'Medicine', 'year_level' => 1],
            ['name' => 'Ethan Brown', 'email' => 'ethan.brown@student.university.edu', 'student_id' => 'STU015', 'department' => 'Arts', 'year_level' => 2],
        ];

        foreach ($additionalStudents as $student) {
            // Check if user already exists
            if (!User::where('email', $student['email'])->exists()) {
                User::create([
                    'name' => $student['name'],
                    'email' => $student['email'],
                    'password' => bcrypt('student123'),
                    'user_type' => 'student',
                    'student_id' => $student['student_id'],
                    'department' => $student['department'],
                    'year_level' => $student['year_level'],
                    'phone' => '+1234567890',
                    'status' => 'active',
                    'created_at' => Carbon::now()->subDays(rand(1, 90)),
                ]);
            }
        }

        // Get all students
        $students = User::where('user_type', 'student')->get();
        
        // Create dormitory applications with various statuses and dates
        $applicationStatuses = ['pending', 'approved', 'rejected'];
        
        foreach ($students as $index => $student) {
            // Create applications for most students (some recent, some older)
            if ($index < 12) { // Create applications for first 12 students
                // Check if student already has an application
                if (!DormitoryApplication::where('student_id', $student->id)->exists()) {
                    $status = $applicationStatuses[array_rand($applicationStatuses)];
                    $createdAt = Carbon::now()->subDays(rand(1, 120));
                    
                    DormitoryApplication::create([
                        'student_id' => $student->id,
                        'preferred_block' => ['A', 'B', 'C'][rand(0, 2)],
                        'room_type_preference' => ['four', 'six'][rand(0, 1)],
                        'application_date' => $createdAt->format('Y-m-d'),
                        'special_requirements' => json_encode(rand(0, 1) ? [] : ['Ground floor preferred']),
                        'emergency_contact_name' => 'Parent Name',
                        'emergency_contact_phone' => '+1234567890',
                        'medical_conditions' => rand(0, 1) ? null : 'No known medical conditions',
                        'status' => $status,
                        'priority_score' => rand(50, 100),
                        'created_at' => $createdAt,
                        'updated_at' => $status !== 'pending' ? $createdAt->addDays(rand(1, 7)) : $createdAt,
                    ]);
                }
            }
        }

        // Get available rooms and assign some students to them
        $availableRooms = Room::where('status', 'available')->take(8)->get();
        $approvedApplications = DormitoryApplication::where('status', 'approved')->with('student')->get();
        
        foreach ($availableRooms as $index => $room) {
            if (isset($approvedApplications[$index])) {
                $student = $approvedApplications[$index]->student;
                
                // Check if student already has an active room assignment
                if (!RoomAssignment::where('student_id', $student->id)
                                  ->where('status', 'active')
                                  ->exists()) {
                    // Create room assignment
                    RoomAssignment::create([
                        'student_id' => $student->id,
                        'room_id' => $room->id,
                        'assigned_at' => Carbon::now()->subDays(rand(1, 60)),
                        'status' => 'active',
                        'assigned_by' => 1, // Admin user ID
                    ]);
                    
                    // Update room occupancy
                    $room->increment('current_occupancy');
                    if ($room->current_occupancy >= $room->capacity) {
                        $room->update(['status' => 'occupied']);
                    }
                }
            }
        }

        // Add some historical room assignments for utilization trends
        $historicalDates = [];
        for ($i = 1; $i <= 12; $i++) {
            $historicalDates[] = Carbon::now()->subMonths($i);
        }

        foreach ($historicalDates as $date) {
            // Create some historical assignments
            $randomRooms = Room::inRandomOrder()->take(rand(3, 8))->get();
            foreach ($randomRooms as $room) {
                if (rand(0, 1)) { // 50% chance to create historical assignment
                    $randomStudent = $students->random();
                    
                    // Check if this student already has a completed assignment
                    if (!RoomAssignment::where('student_id', $randomStudent->id)
                                      ->where('status', 'completed')
                                      ->exists()) {
                        RoomAssignment::create([
                            'student_id' => $randomStudent->id,
                            'room_id' => $room->id,
                            'assigned_at' => $date,
                            'status' => 'completed',
                            'assigned_by' => 1,
                            'unassigned_at' => $date->copy()->addMonths(rand(1, 4)),
                            'created_at' => $date,
                            'updated_at' => $date,
                        ]);
                    }
                }
            }
        }

        // Set some rooms to different statuses
        Room::where('room_number', 'LIKE', 'C%')->take(2)->update(['status' => 'maintenance']);
        Room::where('room_number', 'LIKE', 'B3%')->take(1)->update(['status' => 'reserved']);
    }
}