<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Auth\MustVerifyEmail as MustVerifyEmailBehaviour;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

// Status, founder flag and primary group are deliberately not fillable: only code sets them.
#[Fillable(['name', 'email', 'password', 'timezone', 'preferred_theme', 'preferred_mode', 'preferred_editor', 'signature', 'about', 'location', 'website'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, MustVerifyEmailBehaviour, Notifiable;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_AWAITING_EMAIL = 'awaiting_email';

    public const STATUS_INACTIVE = 'inactive';

    /** Mirrors the column defaults, so a freshly built model behaves like one loaded from the database. */
    protected $attributes = [
        'status' => self::STATUS_ACTIVE,
        'is_founder' => false,
        'preferred_mode' => 'auto',
    ];

    protected static function booted(): void
    {
        static::saving(fn (User $user) => $user->username_clean = mb_strtolower($user->name));
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_visit_at' => 'datetime',
            'password' => 'hashed',
            'is_founder' => 'boolean',
        ];
    }

    /** @return BelongsToMany<Group, $this> */
    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(Group::class)->withPivot(['is_leader', 'is_pending'])->withTimestamps();
    }

    /** @return BelongsTo<Group, $this> */
    public function primaryGroup(): BelongsTo
    {
        return $this->belongsTo(Group::class, 'primary_group_id');
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function initials(): string
    {
        return mb_strtoupper(mb_substr($this->name, 0, 2));
    }

    /** A stable colour class (1 to 6) so each member's avatar keeps the same hue. */
    public function avatarTone(): int
    {
        return (crc32($this->username_clean ?? $this->name) % 6) + 1;
    }
}
