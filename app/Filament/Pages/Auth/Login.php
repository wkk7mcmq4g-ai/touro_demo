<?php

namespace App\Filament\Pages\Auth;

use App\Models\User;
use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use SensitiveParameter;

/**
 * Same credentials as the PHPRunner app: username + password.
 *
 * One login for everyone: whichever login page is used (/login, /admin/login or /portal/login),
 * office users end up in the back office and drivers, partners, suppliers and hotels in the portal.
 */
class Login extends BaseLogin
{
    public function authenticate(): ?LoginResponse
    {
        // Right username and password for an account of the other panel: sign in there instead.
        $provider = Auth::guard(Filament::getAuthGuard())->getProvider();
        $user = $provider->retrieveByCredentials(['username' => $this->data['username'] ?? null]);

        if ($user instanceof User && $provider->validateCredentials($user, ['password' => $this->data['password'] ?? null])
            && ($home = $user->homePanelId()) && $home !== Filament::getCurrentPanel()?->getId()) {
            Filament::setCurrentPanel(Filament::getPanel($home));
            // A page of the other panel asked before signing in would only refuse them.
            session()->forget('url.intended');
        }

        return parent::authenticate();
    }

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

        return "This account (group \"{$group}\") has no access to this app yet.";
    }
}
