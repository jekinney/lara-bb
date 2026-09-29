<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['name', 'slug', 'description', 'color', 'is_hidden'])]
class Group extends Model
{
    /** @return BelongsToMany<User, $this> */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withPivot(['is_leader', 'is_pending'])->withTimestamps();
    }

    public function isSystem(): bool
    {
        return $this->type === 'system';
    }

    public static function bySlug(string $slug): self
    {
        return static::query()->where('slug', $slug)->firstOrFail();
    }
}
