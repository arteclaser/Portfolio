<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public const MASTER = 'master';
    public const EDITOR = 'editor';
    public const COLLABORATOR = 'collaborator';

    public const ROLES = [
        self::MASTER => 'Administrador Master',
        self::EDITOR => 'Editor',
        self::COLLABORATOR => 'Colaborador',
    ];

    protected $fillable = [
        'name', 'email', 'password', 'role', 'is_active', 'can_manage_team', 'can_archive',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_active' => 'boolean',
            'can_manage_team' => 'boolean',
            'can_archive' => 'boolean',
            'invited_at' => 'datetime',
            'password_set_at' => 'datetime',
            'last_login_at' => 'datetime',
            'deactivated_at' => 'datetime',
        ];
    }

    public function pages(): BelongsToMany
    {
        return $this->belongsToMany(Page::class);
    }

    public function teamMember(): HasOne
    {
        return $this->hasOne(TeamMember::class);
    }

    public function isMaster(): bool
    {
        return $this->role === self::MASTER;
    }

    public function isEditor(): bool
    {
        return $this->role === self::EDITOR;
    }

    public function isCollaborator(): bool
    {
        return $this->role === self::COLLABORATOR;
    }

    public function roleLabel(): string
    {
        return self::ROLES[$this->role] ?? $this->role;
    }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new \App\Notifications\ResetPasswordNotification($token));
    }

    public function hasPendingInvite(): bool
    {
        return $this->password === null;
    }
}
