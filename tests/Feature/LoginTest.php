<?php

namespace Tests\Feature;

use App\Filament\Pages\Auth\Login;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class LoginTest extends TestCase
{
    public function test_login_page_asks_for_username(): void
    {
        $this->get('/admin/login')
            ->assertOk()
            ->assertSee('Username');
    }

    public function test_admin_logs_in_with_their_phprunner_password(): void
    {
        $user = $this->makeLegacyUser('Admin');

        Livewire::test(Login::class)
            ->set('data.username', $user->username)
            ->set('data.password', 'secret-pass')
            ->call('authenticate')
            ->assertHasNoErrors();

        $this->assertAuthenticatedAs($user);
    }

    public function test_login_writes_access_log_and_last_login_like_phprunner(): void
    {
        $user = $this->makeLegacyUser('Entry');

        Livewire::test(Login::class)
            ->set('data.username', $user->username)
            ->set('data.password', 'secret-pass')
            ->call('authenticate');

        $this->assertDatabaseHas('access_log', ['userid' => $user->username, 'AccessStatus' => 'In']);
        $this->assertNotNull($user->fresh()->LastLoginDateTime);
    }

    public function test_login_does_not_rewrite_the_shared_password_hash(): void
    {
        $user = $this->makeLegacyUser('Admin');
        $hashBefore = DB::table('MyAthensTransfersAPP_users')->where('ID', $user->ID)->value('password');

        Livewire::test(Login::class)
            ->set('data.username', $user->username)
            ->set('data.password', 'secret-pass')
            ->call('authenticate');

        $this->assertSame($hashBefore, DB::table('MyAthensTransfersAPP_users')->where('ID', $user->ID)->value('password'));
    }

    public function test_wrong_password_is_refused(): void
    {
        $user = $this->makeLegacyUser('Admin');

        Livewire::test(Login::class)
            ->set('data.username', $user->username)
            ->set('data.password', 'wrong')
            ->call('authenticate')
            ->assertHasErrors(['data.username']);

        $this->assertGuest();
    }

    public function test_inactive_user_is_refused(): void
    {
        $user = $this->makeLegacyUser('Admin', ['active' => 0]);

        Livewire::test(Login::class)
            ->set('data.username', $user->username)
            ->set('data.password', 'secret-pass')
            ->call('authenticate')
            ->assertHasErrors(['data.username']);

        $this->assertGuest();
    }

    public function test_partner_driver_and_supplier_logins_do_not_get_into_the_back_office(): void
    {
        foreach (['Partner', 'Drivers', 'Supplier', 'Coach'] as $group) {
            $user = $this->makeLegacyUser($group);

            Livewire::test(Login::class)
                ->set('data.username', $user->username)
                ->set('data.password', 'secret-pass')
                ->call('authenticate')
                ->assertHasErrors(['data.username']);

            $this->assertGuest();
        }
    }

    public function test_a_non_bcrypt_hash_is_refused_instead_of_crashing(): void
    {
        $user = $this->makeLegacyUser('Admin', ['password' => md5('secret-pass')]);

        Livewire::test(Login::class)
            ->set('data.username', $user->username)
            ->set('data.password', 'secret-pass')
            ->call('authenticate')
            ->assertHasErrors(['data.username']);

        $this->assertGuest();
    }

    public function test_refusals_say_why_once_the_password_is_right(): void
    {
        $driver = $this->makeLegacyUser('Drivers');
        Livewire::test(Login::class)->set('data.username', $driver->username)->set('data.password', 'secret-pass')
            ->call('authenticate')->assertSee('please sign in to the portal');

        $stranger = $this->makeLegacyUser('Guest');
        Livewire::test(Login::class)->set('data.username', $stranger->username)->set('data.password', 'secret-pass')
            ->call('authenticate')->assertSee('Only Admin and Entry users');

        $partner = $this->makeLegacyUser('Partner');
        Livewire::test(Login::class)->set('data.username', $partner->username)->set('data.password', 'secret-pass')
            ->call('authenticate')->assertSee('please sign in to the portal');

        $inactive = $this->makeLegacyUser('Entry', ['active' => 0]);
        Livewire::test(Login::class)->set('data.username', $inactive->username)->set('data.password', 'secret-pass')
            ->call('authenticate')->assertSee('not active');

        // A wrong password never reveals anything about the account.
        Livewire::test(Login::class)->set('data.username', $driver->username)->set('data.password', 'wrong')
            ->call('authenticate')->assertDontSee('please sign in to the portal');
        $this->assertGuest();
    }

    public function test_group_names_match_like_phprunner_whatever_the_case(): void
    {
        $user = $this->makeLegacyUser('Entry', ['groupid' => 'entry ']);
        Livewire::test(Login::class)->set('data.username', $user->username)->set('data.password', 'secret-pass')
            ->call('authenticate')->assertHasNoErrors();
        $this->assertAuthenticated();
        $this->assertTrue($user->fresh()->may('transfers', 'edit'));
    }
}
