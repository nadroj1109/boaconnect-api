<?php

namespace App\Services\Profile;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProfileService
{
    public function update(
        User $user,
        ?string $displayName,
        ?string $email,
    ): User {
        if ($displayName !== null) {
            $user->display_name = $displayName;
        }

        $user->email = $email;

        $user->save();

        return $user->fresh();
    }

    public function updatePassword(
        User $user,
        string $password,
    ): User {
        $user->password = Hash::make($password);

        $user->save();

        return $user->fresh();
    }

    public function updateSignature(
        User $user,
        UploadedFile $signature,
    ): User {
        $disk = config(
            'filesystems.document_disk',
            'local'
        );

        /*
         * Delete the previous signature.
         */
        if (
            $user->signature_path &&
            Storage::disk($disk)->exists(
                $user->signature_path
            )
        ) {
            Storage::disk($disk)->delete(
                $user->signature_path
            );
        }

        /*
         * Store the new signature.
         */
        $extension =
            $signature->extension() ?: 'png';

        $filename =
            Str::uuid() . '.' . $extension;

        $path = $signature->storeAs(
            'signatures/' . $user->id,
            $filename,
            $disk
        );

        $user->signature_path = $path;

        $user->save();

        return $user->fresh();
    }

    public function hasSignature(
        User $user,
    ): bool {
        if (! $user->signature_path) {
            return false;
        }

        $disk = config(
            'filesystems.document_disk',
            'local'
        );

        return Storage::disk($disk)->exists(
            $user->signature_path
        );
    }

    public function getSignature(
        User $user,
    ): ?string {
        if (! $this->hasSignature($user)) {
            return null;
        }

        $disk = config(
            'filesystems.document_disk',
            'local'
        );

        return Storage::disk($disk)->get(
            $user->signature_path
        );
    }
}