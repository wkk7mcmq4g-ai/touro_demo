<?php

namespace Tests\Feature;

use App\Filament\Pages\Help as AdminHelp;
use App\Filament\Portal\Pages\Help as PortalHelp;
use App\Filament\Resources\Transfers\Pages\EditTransfer;
use App\Filament\Resources\Transfers\Pages\ListTransfers;
use App\Support\Help;
use Illuminate\Support\Facades\File;
use Livewire\Livewire;
use Tests\TestCase;

/** Βοήθεια: the manual's articles, the "?" link per screen and the side panel's content. */
class HelpTest extends TestCase
{
    public function test_every_article_is_well_formed_and_its_links_and_screens_exist(): void
    {
        $classes = collect(File::allFiles(app_path('Filament')))->map(fn ($f) => $f->getFilenameWithoutExtension())->all();

        foreach (Help::PANELS as $panel) {
            $this->actingAs($this->makeLegacyUser($panel === 'admin' ? 'Admin' : 'Drivers'));
            $articles = Help::articles($panel);
            $this->assertNotEmpty($articles, $panel);

            foreach (glob(resource_path("help/{$panel}/*.md")) as $file) {
                $raw = (string) file_get_contents($file);
                $this->assertMatchesRegularExpression('/\A---\R.*?title: .+?\R---\R/su', $raw, basename($file).' needs a header with a title');
                preg_match_all('/\]\(help:([a-z0-9-]+)\)/', $raw, $m);
                foreach ($m[1] as $slug) {
                    $this->assertFileExists(resource_path("help/{$panel}/{$slug}.md"), basename($file)." links to help:{$slug}");
                }
            }

            foreach ($articles as $a) {
                $this->assertStringNotContainsString("\u{FFFD}", $a['title'].$a['body'], $a['slug']);
                foreach ($a['pages'] as $page) {
                    $this->assertContains($page, $classes, "{$a['slug']}: no screen called {$page}");
                }
                $this->assertStringNotContainsString('{business.', Help::html($panel, $a)->toHtml(), $a['slug']);
            }
        }
    }

    public function test_manual_pages_the_question_button_and_the_side_panel(): void
    {
        $this->actingAs($this->makeLegacyUser('Admin'));

        Livewire::test(AdminHelp::class)->assertOk()->assertSee('Πρώτα βήματα')
            ->call('show', 'transfers')->assertSet('topic', 'transfers')->assertSee('Edit in list')
            ->call('show', 'nope')->assertSet('topic', 'transfers');
        $this->assertSame('transfers', Help::forPage('admin', ListTransfers::class)['slug']);
        $this->assertSame('transfer-form', Help::forPage('admin', EditTransfer::class)['slug']);

        $this->get('/admin/transfers')->assertOk()->assertSee('myat-help-btn', false);
        $this->getJson(route('help.content', ['panel' => 'admin', 'slug' => 'tasks']))->assertOk()
            ->assertJsonPath('title', 'Εργασίες')->assertJsonStructure(['title', 'html', 'url']);
        $this->getJson('/help-content/admin/nope')->assertNotFound();
        $this->getJson(route('help.content', ['panel' => 'portal', 'slug' => 'start']))->assertForbidden(); // the office isn't in the portal

        // portal: drivers get the drivers' articles, customers theirs
        $this->actingAs($this->makeLegacyUser('Drivers'));
        $this->assertTrue(Help::articles('portal')->has('fuel'));
        $this->assertFalse(Help::articles('portal')->has('customer-bookings'));
        Livewire::test(PortalHelp::class)->assertOk()->assertSee('Σύνδεση');
        $this->actingAs($this->makeLegacyUser('Partner'));
        $this->assertFalse(Help::articles('portal')->has('fuel'));
        $this->assertTrue(Help::articles('portal')->has('customer-bookings'));
    }
}
