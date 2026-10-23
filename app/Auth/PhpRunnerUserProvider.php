<?php

namespace App\Auth;

use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Contracts\Auth\Authenticatable;
use SensitiveParameter;

/**
 * Logs users in against the PHPRunner users table.
 *
 * - Users sign in with their username (not e-mail).
 * - Hashes are PHPRunner's bcrypt (password_hash); anything else is refused
 *   instead of throwing.
 * - Hashes are never rewritten here, so the old app keeps working side by side.
 */
class PhpRunnerUserProvider extends EloquentUserProvider
{
    public function retrieveByCredentials(#[SensitiveParameter] array $credentials)
    {
        $username = $credentials['username'] ?? null;

        if (! is_string($username) || trim($username) === '') {
            return null;
        }

        return $this->newModelQuery()
            ->where('username', trim($username))
            ->first();
    }

    public function validateCredentials(Authenticatable $user, #[SensitiveParameter] array $credentials): bool
    {
        $plain = $credentials['password'] ?? null;
        $hash = $user->getAuthPassword();

        if (! is_string($plain) || $plain === '' || ! is_string($hash) || $hash === '') {
            return false;
        }

        if (! str_starts_with($hash, '$2y$') && ! str_starts_with($hash, '$2a$') && ! str_starts_with($hash, '$2b$')) {
            return false;
        }

        return password_verify($plain, $hash);
    }

    public function rehashPasswordIfRequired(Authenticatable $user, #[SensitiveParameter] array $credentials, bool $force = false): void
    {
        // Intentionally a no-op: the hash is shared with the PHPRunner app.
    }
}
