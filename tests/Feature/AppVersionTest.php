<?php

namespace Tests\Feature;

use App\Support\AppVersion;
use Tests\TestCase;

class AppVersionTest extends TestCase
{
    public function test_the_version_from_version_json_is_in_the_user_menu(): void
    {
        $file = base_path('version.json');
        $had = is_file($file) ? file_get_contents($file) : null;
        file_put_contents($file, '{"number":134,"commit":"abc1234","date":"2026-10-27T09:15:00+00:00"}');

        try {
            $this->assertSame('Έκδοση 134 · 27/10/26 11:15', AppVersion::label());
            $this->assertSame('Commit abc1234 · 27/10/2026 11:15', AppVersion::details());

            $this->actingAs($this->makeLegacyUser('Admin'));
            $this->get('/admin')->assertOk()->assertSee('Έκδοση 134 · 27/10/26 11:15');
        } finally {
            $had === null ? unlink($file) : file_put_contents($file, $had);
        }
    }
}
