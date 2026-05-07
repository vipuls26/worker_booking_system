<?php

namespace App\Models;

use Database\Factories\WorkerProfileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'profile_photo', 'bio', 'experience_years', 'address', 'city', 'skills', 'is_verified'])]
class WorkerProfile extends Model
{
    /** @use HasFactory<WorkerProfileFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'experience_years' => 'integer',
            'skills' => 'array',
            'is_verified' => 'boolean',
        ];
    }
}
