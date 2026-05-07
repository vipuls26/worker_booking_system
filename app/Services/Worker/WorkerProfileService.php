<?php

namespace App\Services\Worker;

use App\Models\User;
use App\Models\WorkerProfile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class WorkerProfileService
{
    public function getOrCreate(User $worker): WorkerProfile
    {
        return $worker->workerProfile()
            ->firstOrCreate(['user_id' => $worker->id], [
                'experience_years' => 0,
                'skills' => [],
                'is_verified' => false,
            ])
            ->load('user.role');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(User $worker, array $data): WorkerProfile
    {
        return DB::transaction(function () use ($worker, $data): WorkerProfile {
            $profile = $this->getOrCreate($worker);

            if (($data['profile_photo'] ?? null) instanceof UploadedFile) {
                if ($profile->profile_photo) {
                    Storage::disk('public')->delete($profile->profile_photo);
                }

                $data['profile_photo'] = $data['profile_photo']->store("worker-profiles/{$worker->id}", 'public');
            } else {
                unset($data['profile_photo']);
            }

            $worker->update([
                'phone' => $data['phone'],
            ]);

            $profile->update(Arr::only($data, [
                'profile_photo',
                'bio',
                'experience_years',
                'address',
                'city',
                'skills',
            ]));

            return $profile->refresh()->load('user.role');
        });
    }
}
