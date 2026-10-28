<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Mail\UserWelcome;
use App\Models\User;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Mail;
use Throwable;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    /** The password as typed, for the welcome e-mail only (it is saved hashed). */
    private ?string $typedPassword = null;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->typedPassword = $data['password'] ?? null;

        return $data;
    }

    /** PHPRunner's users BeforeAdd: e-mail the new user their login details. */
    protected function afterCreate(): void
    {
        /** @var User $user */
        $user = $this->getRecord();

        if (blank($user->email) || blank($this->typedPassword)) {
            return;
        }

        try {
            Mail::to($user->email)->send(new UserWelcome($user, $this->typedPassword));
            Notification::make()->success()->title('Welcome e-mail sent to '.$user->email)->send();
        } catch (Throwable $e) {
            report($e);
            Notification::make()->warning()->title('User saved, but the welcome e-mail was not sent')->body($e->getMessage())->persistent()->send();
        } finally {
            $this->typedPassword = null;
        }
    }
}
