<?php

namespace App\Features\Profile\User\Services;

use App\Core\BaseService;

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use App\Features\Notification\Events\NotificationCreated;
use App\Features\ActivityLog\Events\ActivityLogged;

class ProfileService extends BaseService
{
    public function updateProfile($user, array $data)
    {
        if (isset($data['profile_image']) && $data['profile_image'] instanceof \Illuminate\Http\UploadedFile) {
            // Delete old image if exists
            if ($user->profile_image) {
                $oldFilename = basename($user->profile_image);
                Storage::disk('local')->delete('profile-image/' . $oldFilename);
            }

            $path = $data['profile_image']->store('profile-image', 'local');
            $data['profile_image'] = basename($path); // Store only filename
        } else {
            // No new upload — never touch the existing image
            unset($data['profile_image']);
        }

        $user->update($data);

        event(new NotificationCreated($user, 'Profile Updated', 'Your profile details have been successfully updated.', 'system'));
        event(new ActivityLogged($user, 'profile_updated', 'User profile details updated.', 'system'));

        return $user;
    }

    public function updatePassword($user, string $newPassword)
    {
        $user->update([
            'password' => Hash::make($newPassword),
        ]);

        event(new NotificationCreated($user, 'Password Changed', 'Your account password has been successfully updated.', 'system'));
        event(new ActivityLogged($user, 'password_changed', 'User account password changed.', 'security'));

        return $user;
    }
}
