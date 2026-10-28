<?php

namespace Tests\Feature;

use App\Filament\Resources\Users\Pages\CreateUser;
use App\Mail\UserWelcome;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

/** PHPRunner's users BeforeAdd: a new user is e-mailed their login details. */
class UserWelcomeTest extends TestCase
{
    public function test_a_new_user_gets_a_welcome_email_with_the_typed_password(): void
    {
        Mail::fake();
        $this->actingAs($this->makeLegacyUser('Admin'));
        $username = 'welcome_'.uniqid();

        Livewire::test(CreateUser::class)
            ->fillForm(['username' => $username, 'password' => 'S3cret-pass', 'fullname' => 'Maria K', 'email' => 'maria@example.test', 'groupid' => 'Drivers'])
            ->call('create')
            ->assertHasNoFormErrors()
            ->assertNotified('Welcome e-mail sent to maria@example.test');

        $user = User::query()->where('username', $username)->firstOrFail();
        $this->assertTrue(Hash::check('S3cret-pass', $user->password));
        $this->assertNotSame('S3cret-pass', $user->password);

        Mail::assertSent(UserWelcome::class, function (UserWelcome $mail) use ($username): bool {
            $html = $mail->render();

            return $mail->hasTo('maria@example.test') && str_contains($html, $username) && str_contains($html, 'S3cret-pass')
                && str_contains($html, url('/login')) && str_contains($html, 'Maria K');
        });

        $user->delete();
    }

    public function test_no_email_address_means_no_email(): void
    {
        Mail::fake();
        $this->actingAs($this->makeLegacyUser('Admin'));
        $username = 'welcome_'.uniqid();

        Livewire::test(CreateUser::class)
            ->fillForm(['username' => $username, 'password' => 'S3cret-pass', 'groupid' => 'Drivers'])
            ->call('create')
            ->assertHasNoFormErrors();

        Mail::assertNothingSent();
        User::query()->where('username', $username)->delete();
    }
}
