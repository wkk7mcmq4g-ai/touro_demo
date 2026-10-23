<?php

namespace Tests\Feature;

use App\Filament\Resources\Transfers\Tables\TransferCellColors as C;
use App\Models\Transfer;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** PHPRunner's transfers list events (before/after record processed) as cell colours. */
class TransferCellColorsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->emptyTables(['optionvalues']);
        DB::table('optionvalues')->insert([
            ['Option' => 'Status', 'Value' => 'Cancelled', 'Color' => '#ff0000'],
            ['Option' => 'TransferType', 'Value' => 'Arrival', 'Color' => '#7dd3fc'],
        ]);
    }

    private function row(array $values): Transfer
    {
        return (new Transfer)->forceFill($values + ['Status' => 'Pending', 'From' => 'Airport', 'To' => 'Hotel', 'TobeInvoiced' => 'Ναι']);
    }

    public function test_tbd_values_are_red_and_the_id_turns_yellow(): void
    {
        $row = $this->row(['Pax' => 'TBD', 'TransferType' => 'Arrival']);

        $this->assertSame(C::ALERT, C::background($row, 'Pax'));
        $this->assertSame(C::ISSUE_ID, C::background($row, 'TransferID'));
        $this->assertSame('#7dd3fc', C::background($row, 'TransferType'));
        $this->assertNull(C::background($this->row([]), 'TransferID'));
        $this->assertSame(C::ALERT, C::background($this->row(['To' => '']), 'To'));
        $this->assertSame(C::ALERT, C::background($this->row(['Platform' => 'Ναι', 'PlatformPrice' => 0]), 'PlatformPrice'));
        $this->assertSame(C::ALERT, C::background($this->row(['Assistant' => 'TBA guide']), 'Assistant'));
        $this->assertSame(C::ALERT, C::background($this->row(['TobeInvoiced' => '']), 'TobeInvoiced'));
    }

    public function test_cancelled_rows_only_colour_the_status(): void
    {
        $row = $this->row(['Status' => 'Cancelled', 'Pax' => 'TBD', 'TransferType' => 'Arrival']);

        $this->assertSame('#ff0000', C::background($row, 'Status'));
        $this->assertNull(C::background($row, 'Pax'));
        $this->assertNull(C::background($row, 'TransferType'));
    }

    public function test_voucher_times_fall_back_to_the_calculated_value(): void
    {
        $calculated = $this->row(['Voucher_StartDt' => null, 'Voucher_Start_Date_Calc' => '2026-10-23']);
        $this->assertSame('2026-10-23', Carbon::parse(C::voucherValue($calculated, 'Voucher_StartDt'))->toDateString());
        $this->assertSame(C::CALCULATED, C::background($calculated, 'Voucher_StartDt'));
        $this->assertSame(C::SET, C::background($this->row(['Voucher_StartDt' => '2026-10-23']), 'Voucher_StartDt'));
        $this->assertSame(C::ALERT, C::background($this->row([]), 'Voucher_EndTime'));
    }

    public function test_vito_van_for_customers_51_and_44_is_flagged(): void
    {
        $row = $this->row(['cID_Customer' => 51, 'CoachCompanyID' => 'VITO 2022', 'CarTypeID' => 'VAN']);
        $this->assertSame(C::ALERT, C::background($row, 'CoachCompanyID'));
        $this->assertNull(C::background($this->row(['cID_Customer' => 7, 'CoachCompanyID' => 'VITO 2022', 'CarTypeID' => 'VAN']), 'CoachCompanyID'));
    }
}
