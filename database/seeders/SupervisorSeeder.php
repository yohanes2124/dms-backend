<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class SupervisorSeeder extends Seeder
{
    public function run()
    {
        $supervisors = [
            [
                'name' => 'Block A Supervisor',
                'email' => 'supervisor.a@dormitory.edu',
                'password' => Hash::make('password123'),
                'user_type' => 'supervisor',
                'assigned_block' => 'A',
                'phone' => '+251911234567',
                'status' => 'active'
            ],
            [
                'name' => 'Block B Supervisor',
                'email' => 'supervisor.b@dormitory.edu',
                'password' => Hash::make('password123'),
                'user_type' => 'supervisor',
                'assigned_block' => 'B',
                'phone' => '+251922345678',
                'status' => 'active'
            ],
            [
                'name' => 'Block C Supe