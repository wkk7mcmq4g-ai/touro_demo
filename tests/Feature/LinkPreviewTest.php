<?php

namespace Tests\Feature;

use Tests\TestCase;

/** Links shared in WhatsApp & co. preview the page's name and the logo, not "Login" — and no data. */
class LinkPreviewTest extends TestCase
{
    public function test_link_preview_robots_get_the_page_name_and_logo(): void
    {
        $robot = ['User-Agent' => 'WhatsApp/2.23.20.0 A'];

        $this->get('/admin/reports/driver-sales', $robot)->assertOk()
            ->assertSee('<meta property="og:title" content="Πωλήσεις ανά οδηγό">', false)
            ->assertSee(config('business.name'))
            ->assertSee(asset(config('business.logo.icon_512')), false);

        $this->get('/admin/transfers/123/edit', $robot)->assertOk()
            ->assertSee('content="Transfers · Επεξεργασία"', false);

        // People (and robots on unknown pages) still go to the login.
        $this->get('/admin/reports/driver-sales', ['User-Agent' => 'Mozilla/5.0'])->assertRedirect();
        $this->get('/admin/nothing-here', $robot)->assertNotFound();
    }
}
