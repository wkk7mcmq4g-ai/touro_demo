<?php

namespace Tests\Feature;

use App\Filament\Widgets\TransfersOverview;
use App\Support\Permissions;
use Livewire\Livewire;
use Tests\TestCase;

class PermissionsTest extends TestCase
{
    public function test_admin_can_manage_users(): void
    {
        $this->actingAs($this->makeLegacyUser('Admin'))
            ->get('/admin/users')
            ->assertOk()
            ->assertSee('Users');
    }

    public function test_office_staff_cannot_manage_users(): void
    {
        $this->actingAs($this->makeLegacyUser('Entry'))
            ->get('/admin/users')
            ->assertForbidden();
    }

    public function test_masks_follow_the_phprunner_project(): void
    {
        $entry = $this->makeLegacyUser('Entry');
        $supplier = $this->makeLegacyUser('Supplier');

        // Entry: full rights on transfers. Supplier: edit/search/print only ("ESP").
        $this->assertTrue($entry->may('transfers', 'create'));
        $this->assertTrue($supplier->may('transfers', 'edit'));
        $this->assertFalse($supplier->may('transfers', 'create'));
        $this->assertFalse($supplier->may('transfers', 'delete'));

        // PHPRunner table names are case-insensitive.
        $this->assertSame(Permissions::mask('Admin', 'PaymentsReceived'), Permissions::mask('Admin', 'paymentsreceived'));
    }

    public function test_admin_dashboard_shows_sales(): void
    {
        $this->actingAs($this->makeLegacyUser('Admin'));

        Livewire::test(TransfersOverview::class)
            ->assertSee('Today')
            ->assertSee('Sales this month');
    }

    public function test_office_dashboard_hides_sales(): void
    {
        $this->actingAs($this->makeLegacyUser('Entry'));

        Livewire::test(TransfersOverview::class)
            ->assertSee('Today')
            ->assertDontSee('Sales this month');
    }
}
