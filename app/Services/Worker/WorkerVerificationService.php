<?php

namespace App\Services\Worker;

use App\Models\User;
use App\Models\WorkerVerification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class WorkerVerificationService
{
    public function get(User $worker): ?WorkerVerification
    {
        return WorkerVerification::query()
            ->where('user_id', $worker->id)
            ->with(['user.role', 'verifier.role'])
            ->first();
    }

    /**
     * @param  array{id_proof?: UploadedFile, certificates?: array<int, UploadedFile>, experience_years: int, mobile_verified?: bool}  $data
     */
    public function submit(User $worker, array $data): WorkerVerification
    {
        return DB::transaction(function () use ($worker, $data): WorkerVerification {
            $verification = WorkerVerification::query()->firstOrNew(['user_id' => $worker->id]);

            if (($data['id_proof'] ?? null) instanceof UploadedFile && $verification->exists && $verification->id_proof) {
                Storage::disk('public')->delete($verification->id_proof);
            }

            $certificateFiles = collect($data['certificates'] ?? [])
                ->filter(fn (mixed $certificate): bool => $certificate instanceof UploadedFile)
                ->values();

            if ($certificateFiles->isNotEmpty()) {
                collect($verification->certificates ?? [])
                    ->filter()
                    ->each(fn (string $path): bool => Storage::disk('public')->delete($path));
            }

            $idProofPath = ($data['id_proof'] ?? null) instanceof UploadedFile
                ? $data['id_proof']->store("worker-verifications/{$worker->id}", 'public')
                : $verification->id_proof;
            $certificatePaths = $certificateFiles
                ->map(fn (UploadedFile $certificate): string => $certificate->store("worker-verifications/{$worker->id}/certificates", 'public'))
                ->values()
                ->all();

            $verification->fill([
                'id_proof' => $idProofPath,
                'certificates' => $certificateFiles->isNotEmpty() ? $certificatePaths : ($verification->certificates ?? []),
                'experience_years' => $data['experience_years'],
                'mobile_verified' => $data['mobile_verified'] ?? false,
                'status' => WorkerVerification::STATUS_PENDING,
                'rejection_reason' => null,
                'verified_by' => null,
                'verified_at' => null,
            ])->save();

            $worker->workerProfile()->updateOrCreate(['user_id' => $worker->id], [
                'experience_years' => $data['experience_years'],
                'is_verified' => false,
            ]);

            return $verification->refresh()->load(['user.role', 'verifier.role']);
        });
    }
}
