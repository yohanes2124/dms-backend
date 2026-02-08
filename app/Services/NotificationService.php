<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Collection;

class NotificationService
{
    /**
     * Create a notification for specific users
     */
    public function createForUsers(array $userIds, string $type, string $title, string $message, array $data = [], ?int $senderId = null): Collection
    {
        $notifications = collect();
        
        foreach ($userIds as $userId) {
            $notification = Notification::create([
                'type' => $type,
                'title' => $title,
                'message' => $message,
                'data' => $data,
                'user_id' => $userId,
                'sender_id' => $senderId,
            ]);
            
            $notifications->push($notification);
        }
        
        return $notifications;
    }

    /**
     * Create notification for all supervisors
     */
    public function notifySupervisors(string $type, string $title, string $message, array $data = [], ?int $senderId = null): Collection
    {
        $supervisorIds = User::where('user_type', 'supervisor')->pluck('id')->toArray();
        return $this->createForUsers($supervisorIds, $type, $title, $message, $data, $senderId);
    }

    /**
     * Create notification for all admins
     */
    public function notifyAdmins(string $type, string $title, string $message, array $data = [], ?int $senderId = null): Collection
    {
        $adminIds = User::where('user_type', 'admin')->pluck('id')->toArray();
        return $this->createForUsers($adminIds, $type, $title, $message, $data, $senderId);
    }

    /**
     * Create notification for supervisors and admins
     */
    public function notifyStaff(string $type, string $title, string $message, array $data = [], ?int $senderId = null): Collection
    {
        $staffIds = User::whereIn('user_type', ['supervisor', 'admin'])->pluck('id')->toArray();
        return $this->createForUsers($staffIds, $type, $title, $message, $data, $senderId);
    }

    /**
     * Notify about student registration
     */
    public function notifyStudentRegistered(User $student): Collection
    {
        return $this->notifyStaff(
            'student_registered',
            'New Student Registration',
            "New student {$student->name} ({$student->student_id}) has registered for dormitory services.",
            [
                'student_id' => $student->id,
                'student_name' => $student->name,
                'student_number' => $student->student_id,
                'department' => $student->department,
            ],
            $student->id
        );
    }

    /**
     * Notify about application submission
     */
    public function notifyApplicationSubmitted(User $student, $application): Collection
    {
        return $this->notifyStaff(
            'application_submitted',
            'New Dormitory Application',
            "Student {$student->name} has submitted a new dormitory application.",
            [
                'student_id' => $student->id,
                'application_id' => $application->id,
                'student_name' => $student->name,
            ],
            $student->id
        );
    }

    /**
     * Notify student about application status
     */
    public function notifyApplicationStatus(User $student, $application, string $status, ?int $staffId = null): void
    {
        $statusMessages = [
            'approved' => 'Your dormitory application has been approved! 🎉',
            'rejected' => 'Your dormitory application has been reviewed.',
            'pending' => 'Your dormitory application is under review.',
        ];

        Notification::create([
            'type' => 'application_status',
            'title' => 'Application Status Update',
            'message' => $statusMessages[$status] ?? 'Your application status has been updated.',
            'data' => [
                'application_id' => $application->id,
                'status' => $status,
            ],
            'user_id' => $student->id,
            'sender_id' => $staffId,
        ]);
    }

    /**
     * Get notifications for a user
     */
    public function getUserNotifications(int $userId, bool $unreadOnly = false, int $limit = 50): Collection
    {
        $query = Notification::forUser($userId)
            ->with('sender')
            ->orderBy('created_at', 'desc')
            ->limit($limit);

        if ($unreadOnly) {
            $query->unread();
        }

        return $query->get();
    }

    /**
     * Mark notification as read
     */
    public function markAsRead(int $notificationId, int $userId): bool
    {
        $notification = Notification::where('id', $notificationId)
            ->where('user_id', $userId)
            ->first();

        if ($notification) {
            $notification->markAsRead();
            return true;
        }

        return false;
    }

    /**
     * Mark all notifications as read for a user
     */
    public function markAllAsRead(int $userId): int
    {
        return Notification::forUser($userId)
            ->unread()
            ->update([
                'is_read' => true,
                'read_at' => now(),
            ]);
    }

    /**
     * Get unread count for a user
     */
    public function getUnreadCount(int $userId): int
    {
        return Notification::forUser($userId)->unread()->count();
    }

    /**
     * Notify when any user registers
     */
    public function notifyUserRegistered($user)
    {
        try {
            // Get all admins
            $admins = User::where('user_type', 'admin')->get();
            
            foreach ($admins as $admin) {
                Notification::create([
                    'user_id' => $admin->id,
                    'title' => 'New ' . ucfirst($user->user_type) . ' Registration',
                    'message' => $user->name . ' has registered as a ' . $user->user_type . ' and needs approval.',
                    'type' => 'user_registration',
                    'data' => json_encode([
                        'user_id' => $user->id,
                        'user_name' => $user->name,
                        'user_type' => $user->user_type,
                        'user_email' => $user->email
                    ])
                ]);
            }
        } catch (\Exception $e) {
            \Log::error('Failed to send user registration notification: ' . $e->getMessage());
        }
    }

    /**
     * Notify when user is approved
     */
    public function notifyUserApproved($user)
    {
        try {
            Notification::create([
                'user_id' => $user->id,
                'title' => 'Account Approved',
                'message' => 'Your ' . $user->user_type . ' account has been approved! You can now log in.',
                'type' => 'account_approved'
            ]);
        } catch (\Exception $e) {
            \Log::error('Failed to send approval notification: ' . $e->getMessage());
        }
    }

    /**
     * Notify when user is rejected
     */
    public function notifyUserRejected($user, $reason)
    {
        try {
            Notification::create([
                'user_id' => $user->id,
                'title' => 'Account Rejected',
                'message' => 'Your ' . $user->user_type . ' account has been rejected. Reason: ' . $reason,
                'type' => 'account_rejected'
            ]);
        } catch (\Exception $e) {
            \Log::error('Failed to send rejection notification: ' . $e->getMessage());
        }
    }
}