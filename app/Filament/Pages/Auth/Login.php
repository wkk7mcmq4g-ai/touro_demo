<?php

namespace App\Filament\Pages\Auth;

use App\Models\User;
use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use SensitiveParameter;

/**
 * Same credentials as the PHPRunner app: username + password.
 */
class Login extends BaseLogin
{
    protected function getEmailFormComponent(): Component
    {
        return TextInput::make('username')
            ->label('Username')
            ->required()
            ->autocomplete('username')
            ->autofocus();
    }

    protected function getCredentialsFromFormData(#[SensitiveParameter] array $data): array
    {
        return [
            'username' => $data['username'],
            'password' => $data['password'],
        ];
    }

    protected function throwFailureValidationException(): never
    {
        throw ValidationException::withMessages([
            'data.username' => $this->refusalReason() ?? __('filament-panels::auth/pages/login.messages.failed'),
        ]);
    }

    /**
     * When the username and password are right but the account may not use this app, say why
     * instead of "These credentials do not match our records".
     */
    private function refusalReason(): ?string
    {
        $username = $this->data['username'] ?? null;
        $password = $this->data['password'] ?? null;
        $provider = Auth::guard(Filament::getAuthGuard())->getProvider();
        $user = $provider->retrieveByCredentials(['username' => $username]);

        if (! $user instanceof User || ! $provider->validateCredentials($user, ['password' => $password])) {
            return null;
        }

        if (! $user->isActive()) {
            return 'This account is not active. An admin can activate it in the PHPRunner users list (active = 1).';
        }

        $group = $user->group()?->getLabel() ?? ($user->groupid ?: 'no group');

        if (Filament::getCurrentPanel()?->getId() === 'portal') {
            return $user->group()?->usesBackOffice()
                ? 'Office accounts sign in to the back office at '.url('/admin').'.'
                : "This account (group \"{$group}\") cannot use the portal yet.";
        }

        return $user->group()?->usesPortal()
            ? "This account is in the group \"{$group}\": please sign in to the portal at ".url('/portal').'.'
            : "Only Admin and Entry users can use the back office. This account is in the group \"{$group}\".";
    }
}
