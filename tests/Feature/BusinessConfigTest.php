<?php

namespace Tests\Feature;

use App\Filament\Theme;
use App\Mail\GuideAssignment;
use App\Models\DailyRecipient;
use App\Models\VTransfer;
use App\Services\Documents\Vouchers;
use Tests\TestCase;

/**
 * Everything that identifies the business comes from config/business.php: a copy of the app
 * for another business shows none of MyAT's details once its config is changed.
 */
class BusinessConfigTest extends TestCase
{
    private const MYAT = ['My Athens Transfers', 'MyAthens Transfers', '0261E60000721601', 'myathenstransfers.com', 'Aris Biliktsis', '6976 684 995', '#0096ff', '#08345a'];

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'business.name' => 'Demo Tours',
            'business.short_name' => 'DT',
            'business.trade_name' => 'Demo Tours Trade',
            'business.legal_name' => 'Demo Tours SA',
            'business.mhte' => 'MHTE-12345',
            'business.website' => 'www.demo.test',
            'business.email' => 'info@demo.test',
            'business.reservations_email' => 'rsv@demo.test',
            'business.operations' => ['name' => 'Ops Person', 'title' => 'Ops', 'email' => 'ops@demo.test', 'phone' => '+30 210 000 0000'],
            'business.wordmark' => ['prefix' => '', 'main' => 'Demo', 'suffix' => 'Tours'],
            'business.colors.primary' => '#112233',
            'business.colors.navy' => '#445566',
            'mail.from.name' => 'Demo Tours',
            'dispatch.reply_to' => null,
        ]);
    }

    private function job(): VTransfer
    {
        return (new VTransfer)->forceFill(['TransferID' => 1, 'TransferDate' => '2026-10-25', 'TransferTime' => '09:30', 'From' => 'Piraeus Port', 'To' => 'Athens Airport', 'Assistant' => 'Guide', 'AssistantEmail' => 'guide@example.test']);
    }

    private function assertNoMyat(string $output): void
    {
        foreach (self::MYAT as $value) {
            $this->assertStringNotContainsStringIgnoringCase($value, $output);
        }
    }

    public function test_vouchers_print_the_business_name_and_mhte(): void
    {
        $vouchers = new Vouchers;

        $olp = $vouchers->html('VoucherOLP', $this->job()->getAttributes());
        $this->assertStringContainsString('Demo Tours Trade', $olp);
        $this->assertStringContainsString('MHTE-12345', $olp);
        $this->assertNoMyat($olp);

        // The images still resolve through config (logo, header, stamp are inlined).
        $this->assertStringContainsString('data:image/png;base64,', $vouchers->html('Voucher', $this->job()->getAttributes()));
    }

    public function test_emails_carry_the_business_details(): void
    {
        $mail = new GuideAssignment($this->job());
        $html = $mail->render();

        $this->assertStringContainsString('Demo Tours SA', $html);
        $this->assertStringContainsString('+30 210 000 0000', $html);
        $this->assertNoMyat($html);
        $this->assertSame('Demo Tours', $mail->envelope()->from->name);
        $this->assertSame('ops@demo.test', $mail->envelope()->replyTo[0]->address);
    }

    public function test_driver_message_header_uses_the_legal_name(): void
    {
        $text = (new DailyRecipient)->forceFill(['Type' => 'Driver', 'TransferDate' => '2026-10-25', 'Fullname' => 'Driver'])->telegramText();

        $this->assertStringContainsString('*Demo Tours SA: MHTE-12345*', $text);
        $this->assertNoMyat($text);
    }

    public function test_theme_colours_and_portal_manifest(): void
    {
        $css = Theme::colorVariables();
        $this->assertStringContainsString('--myat-azure:#112233', $css);
        $this->assertStringContainsString('--myat-azure-rgb:17 34 51', $css);
        $this->assertStringContainsString('--myat-navy:#445566', $css);

        $this->get('/portal.webmanifest')
            ->assertOk()
            ->assertJson(['name' => 'Demo Tours', 'short_name' => 'DT', 'theme_color' => '#445566']);
    }

    public function test_panel_logo_shows_the_wordmark(): void
    {
        $html = view('filament.brand.logo')->render();

        $this->assertStringContainsString('Demo', $html);
        $this->assertStringContainsString('<svg class="myat-brand-mark"', $html);
        $this->assertStringNotContainsString('Athens', $html);
    }
}
