<?php

namespace App\Models;

use App\Enums\UserGroup;
use App\Support\Permissions;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasName;
use Filament\Panel;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;

/**
 * A login from the PHPRunner users table, shared with the old app.
 *
 * @property int $ID
 * @property string|null $username
 * @property string|null $password
 * @property string|null $email
 * @property string|null $fullname
 * @property string|null $groupid
 * @property int|null $active
 * @property int|null $SupplierID
 * @property Carbon|null $LastLoginDateTime
 */
class User extends Authenticatable implements FilamentUser, HasName
{
    use Notifiable;

    protected $table = 'MyAthensTransfersAPP_users';

    protected $primaryKey = 'ID';

    public $timestamps = false;

    protected $fillable = [
        'username',
        'password',
        'email',
        'fullname',
        'groupid',
        'active',
        'SupplierID',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'apikey',
        'userpic',
    ];

    protected function casts(): array
    {
        return [
            'ID' => 'integer',
            'active' => 'integer',
            'SupplierID' => 'integer',
            'LastLoginDateTime' => 'datetime',
            // bcrypt, the same format PHPRunner writes, so both apps accept it.
            'password' => 'hashed',
        ];
    }

    public function group(): ?UserGroup
    {
        // PHPRunner matches group names loosely: tolerate case and stray spaces ("entry ", "ADMIN").
        $id = trim((string) $this->groupid);

        return UserGroup::tryFrom($id)
            ?? collect(UserGroup::cases())->first(fn (UserGroup $g): bool => strcasecmp($g->value, $id) === 0);
    }

    public function isActive(): bool
    {
        return (int) $this->active === 1;
    }

    /** PHPRunner-style permission check, e.g. `$user->may('transfers', 'edit')`. */
    public function may(string $table, string $ability): bool
    {
        return Permissions::allows($this, $table, $ability);
    }

    public function canAccessPanel(Panel $panel): bool
    {
        if (! $this->isActive()) {
            return false;
        }

        return $panel->getId() === 'portal'
            ? (bool) $this->group()?->usesPortal()
            : (bool) $this->group()?->usesBackOffice();
    }

    public function getFilamentName(): string
    {
        return $this->fullname ?: (string) $this->username;
    }
}
