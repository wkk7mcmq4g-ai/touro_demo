<?php

namespace Tests\Feature;

use App\Filament\Pages\MyMenu;
use App\Support\PersonalMenu;
use Filament\Facades\Filament;
use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationItem;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/** Το μενού μου: each user's favourites, order, own groups, renamed groups and hidden items. */
class PersonalMenuTest extends TestCase
{
    private string $username;

    protected function setUp(): void
    {
        parent::setUp();
        $user = $this->makeLegacyUser('Admin');
        $this->username = $user->username;
        $this->actingAs($user);
        Filament::setCurrentPanel('admin');
    }

    protected function tearDown(): void
    {
        DB::table('MyAthensTransfersAPP_settings')->where('TABLENAME', PersonalMenu::KEY)->where('USERNAME', $this->username)->delete();
        parent::tearDown();
    }

    /** @return array<string, list<string>> group label => item labels, as the side menu draws them */
    private function sidebar(): array
    {
        app()->forgetScopedInstances();

        return collect(filament()->getNavigation())->mapWithKeys(fn (NavigationGroup $g): array => [
            (string) $g->getLabel() => array_map(fn (NavigationItem $i): string => (string) $i->getLabel(), collect($g->getItems())->all()),
        ])->all();
    }

    public function test_arrangement_is_saved_per_user_and_drawn_in_the_side_menu(): void
    {
        $standard = $this->sidebar();
        $this->assertArrayHasKey('Operations', $standard);
        $this->assertArrayNotHasKey(PersonalMenu::FAVOURITES, $standard);

        $page = Livewire::test(MyMenu::class)->assertOk()->assertSee('Αγαπημένα');
        $page->call('toggleFavourite', '/admin/transfers')
            ->callAction('addGroup', ['label' => 'Καθημερινά']);

        $own = collect(PersonalMenu::config()['groups'])->firstWhere('label', 'Καθημερινά');
        $order = collect(PersonalMenu::config()['groups'])->map(fn (array $g): array => [
            'id' => $g['id'],
            'items' => $g['id'] === $own['id'] ? ['/admin/timeline', '/admin/dispatch'] : array_values(array_diff($g['items'], ['/admin/timeline', '/admin/dispatch'])),
        ]);
        // the new group dragged to the top of the groups
        $page->call('saveOrder', [$order->last(), ...$order->slice(0, -1)->all()])
            ->call('renameGroup', collect(PersonalMenu::config()['groups'])->firstWhere('label', 'Finance')['id'], 'Λογιστήριο')
            ->call('toggleHidden', '/admin/driver-calendar');

        $menu = $this->sidebar();
        $labels = array_keys($menu);
        $this->assertSame(['', PersonalMenu::FAVOURITES, 'Καθημερινά'], array_slice($labels, 0, 3)); // Dashboard first
        $this->assertSame(['Transfers'], $menu[PersonalMenu::FAVOURITES]);
        $this->assertSame(['Ημερήσιο πλάνο', 'Dispatch'], $menu['Καθημερινά']);
        $this->assertNotContains('Dispatch', $menu['Operations']);
        $this->assertContains('Transfers', $menu['Operations']); // favourites are shortcuts, the item stays in its group
        $this->assertArrayHasKey('Λογιστήριο', $menu);
        $this->assertArrayNotHasKey('Finance', $menu);
        $this->assertNotContains('Calendar', $menu['Fleet & drivers']);

        // stored in the PHPRunner settings table, read back on the next sign-in
        $row = DB::table('MyAthensTransfersAPP_settings')->where(['TYPE' => 100, 'USERNAME' => $this->username, 'TABLENAME' => PersonalMenu::KEY])->value('SEARCH');
        $this->assertStringContainsString('"/admin/transfers"', $row);
        session()->forget('myat.menu');
        $this->assertSame($menu, $this->sidebar());

        // deleting an own group sends its pages home; reset brings the standard menu back
        Livewire::test(MyMenu::class)->call('deleteGroup', $own['id']);
        $this->assertContains('Dispatch', $this->sidebar()['Operations']);
        Livewire::test(MyMenu::class)->callAction('reset');
        $this->assertSame($standard, $this->sidebar());
        $this->assertNull(DB::table('MyAthensTransfersAPP_settings')->where('USERNAME', $this->username)->where('TABLENAME', PersonalMenu::KEY)->value('SEARCH'));
    }

    public function test_a_damaged_setting_never_breaks_the_menu_and_unknown_pages_are_ignored(): void
    {
        session()->put('myat.menu', PersonalMenu::normalise([
            'groups' => ['junk', ['id' => 'x', 'label' => 'Mine', 'items' => ['/admin/nope', 5, '/admin/transfers']]],
            'favourites' => ['/admin/users-i-cannot-see'],
        ]));

        $menu = $this->sidebar();
        $this->assertSame(['Transfers'], $menu['Mine']);
        $this->assertArrayNotHasKey(PersonalMenu::FAVOURITES, $menu);
        $this->assertArrayHasKey('Operations', $menu); // everything else still in its usual place
    }
}
