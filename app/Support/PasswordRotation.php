<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class PasswordRotation
{
    public function __construct(private SingleDeviceSession $sessions) {}

    public function change(User $user, string $currentPassword, string $newPassword): void
    {
        DB::transaction(function () use ($user, $currentPassword, $newPassword): void {
            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->getKey());

            if (! Hash::check($currentPassword, $lockedUser->password)) {
                $validator = Validator::make([], []);
                $validator->errors()->add('current_password', __('validation.current_password'));
                $exception = new ValidationException($validator);
                $exception->errorBag = 'updatePassword';

                throw $exception;
            }

            $lockedUser->forceFill(['password' => Hash::make($newPassword)])->save();
            $this->sessions->revoke($lockedUser);
        });
    }

    public function reset(User $user, string $newPassword): void
    {
        DB::transaction(function () use ($user, $newPassword): void {
            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->getKey());
            $lockedUser->forceFill(['password' => Hash::make($newPassword)])->save();
            $this->sessions->revoke($lockedUser);
        });
    }
}
