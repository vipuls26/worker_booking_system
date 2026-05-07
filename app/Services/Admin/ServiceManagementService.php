<?php

namespace App\Services\Admin;

use App\Models\Service;
use App\Models\User;
use App\Support\Filters\ServiceFilter;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ServiceManagementService
{
    public function __construct(private readonly ServiceFilter $filter) {}

    public function paginate(Request $request): LengthAwarePaginator
    {
        $query = Service::query()
            ->with('creator:id,name,email,role_id')
            ->latest();

        return $this->filter
            ->apply($query, $request)
            ->paginate($request->integer('per_page', 15));
    }

    /**
     * @param  array{name: string, description?: string|null, icon?: string|null, is_active?: bool}  $data
     */
    public function create(array $data, User $admin): Service
    {
        return Service::create([
            ...$data,
            'slug' => $this->uniqueSlug($data['name']),
            'is_active' => $data['is_active'] ?? true,
            'created_by' => $admin->id,
        ])->load('creator');
    }

    /**
     * @param  array{name: string, description?: string|null, icon?: string|null, is_active: bool}  $data
     */
    public function update(Service $service, array $data): Service
    {
        $service->update([
            ...$data,
            'slug' => $service->name === $data['name'] ? $service->slug : $this->uniqueSlug($data['name'], $service->id),
        ]);

        return $service->refresh()->load('creator');
    }

    public function delete(Service $service): void
    {
        $service->delete();
    }

    public function toggleStatus(Service $service): Service
    {
        $service->update([
            'is_active' => ! $service->is_active,
        ]);

        return $service->refresh()->load('creator');
    }

    private function uniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $baseSlug = Str::slug($name);
        $slug = $baseSlug;
        $counter = 2;

        while (Service::withTrashed()
            ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
            ->where('slug', $slug)
            ->exists()) {
            $slug = "{$baseSlug}-{$counter}";
            $counter++;
        }

        return $slug;
    }
}
