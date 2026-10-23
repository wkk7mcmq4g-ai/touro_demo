<?php

namespace App\Services\Elorus;

use App\Models\Transfer;
use App\Models\TransferCharge;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * The PHPRunner "Τιμολόγηση" buttons (spec/buttons): Elorus invoices and receipts for
 * transfers and charges. Each button's rules are kept; the results are written with the
 * same UPDATE/INSERT statements, so PHPRunner and the triggers see the same data.
 */
class TransferInvoicing
{
    public const PDF_ALL = 'all';

    public const PDF_AUTO = 'auto';

    public const PDF_NO = 'no';

    public function __construct(private ElorusClient $elorus) {}

    /**
     * "One invoice with 1 product total amount" (New_Button13): one line, the total of the
     * sale prices, with a free description.
     *
     * @param  Collection<int, Transfer>  $transfers
     */
    public function oneInvoiceTotal(Collection $transfers, string $date, string $description, bool $draft): InvoiceRun
    {
        return $this->run(function (InvoiceRun $run) use ($transfers, $date, $description, $draft): void {
            $billing = $this->customerBilling($this->singleCustomer($transfers));
            $total = $transfers->sum(fn (Transfer $t): float => (float) $t->SalePrice);

            $this->issueInvoice($run, $transfers, $billing, $date, $draft, [$this->line($billing, $description, $total)]);
        });
    }

    /**
     * "One invoice with many products" (New_Button27): one line per transfer.
     *
     * @param  Collection<int, Transfer>  $transfers
     */
    public function oneInvoicePerLines(Collection $transfers, string $date, bool $draft): InvoiceRun
    {
        return $this->run(function (InvoiceRun $run) use ($transfers, $date, $draft): void {
            $billing = $this->customerBilling($this->singleCustomer($transfers));

            $this->issueInvoice($run, $transfers, $billing, $date, $draft, $this->transferLines($transfers, $billing));
        });
    }

    /**
     * "Many invoices per lead name" (New_Button30): one invoice per lead passenger, a line
     * per transfer in date order.
     *
     * @param  Collection<int, Transfer>  $transfers
     */
    public function invoicesPerLeadName(Collection $transfers, string $date, bool $draft): InvoiceRun
    {
        return $this->run(function (InvoiceRun $run) use ($transfers, $date, $draft): void {
            foreach ($transfers->sortBy('TransferDate')->groupBy(fn (Transfer $t): string => self::leadKey($t->LeadName)) as $group) {
                $lead = trim((string) $group->first()->LeadName);
                $this->attempt($run, 'Lead '.($lead ?: '(empty)'), function () use ($run, $group, $date, $draft): void {
                    $billing = $this->customerBilling($this->singleCustomer($group));
                    $this->issueInvoice($run, $group, $billing, $date, $draft, $this->transferLines($group, $billing));
                });
            }
        });
    }

    /**
     * "Many receipts with 1 product per line" (New_Button28) and the taxi version
     * (New_Button80): one receipt per transfer to the private-individual customer; the taxi
     * version replaces the driver's taxi receipt and can issue the credit note for it.
     *
     * @param  Collection<int, Transfer>  $transfers
     */
    public function receipts(Collection $transfers, bool $draft, bool $platformPrice, string $pdf, bool $taxi = false, bool $creditNote = false): InvoiceRun
    {
        return $this->run(function (InvoiceRun $run) use ($transfers, $draft, $platformPrice, $pdf, $taxi, $creditNote): void {
            $billing = $this->privateIndividualBilling();
            $today = Carbon::today()->format('Y-m-d');

            foreach ($transfers as $transfer) {
                $this->attempt($run, "Transfer {$transfer->TransferID}", function () use ($run, $transfer, $billing, $draft, $platformPrice, $pdf, $taxi, $creditNote, $today): void {
                    $value = $transfer->Platform === 'Ναι' && $platformPrice ? (float) $transfer->PlatformPrice : (float) $transfer->SalePrice;
                    $supplier = config('elorus.supplier_labels')[(int) $transfer->cID_Customer] ?? null;
                    $description = 'Lead Name: '.$transfer->LeadName.' - Booking Reference :'.$transfer->SupplierCode
                        .($supplier ? " / {$supplier} " : '')
                        .($taxi ? '  και σε αντικατάσταση της απόδειξης με AA  '.$transfer->TaxiReceipt : '');

                    $doc = $this->elorus->createInvoice($this->document($billing, $today, $draft, [$this->line($billing, $description, $value)]));
                    $run->created[] = $doc['number'] ?: $doc['id'];
                    $this->markInvoiced([$transfer->TransferID], $doc);

                    $credit = null;
                    if ($taxi && $creditNote) {
                        // Before any PDF download, so a PDF problem never leaves the taxi receipt uncancelled.
                        $this->attempt($run, "Transfer {$transfer->TransferID} credit note", function () use ($run, $transfer, $billing, $draft, $today, &$credit): void {
                            $credit = $this->elorus->createCreditNote(array_merge(
                                $this->document($billing, $today, $draft, [$this->line($billing, 'ΑΑ'.$transfer->TaxiReceipt, (float) $transfer->TaxiReceiptAmount)]),
                                ['documenttype' => config('elorus.taxi_document_type'), 'mydata_document_type' => config('elorus.taxi_mydata_document_type')],
                            ));
                            $run->created[] = $credit['number'] ?: $credit['id'];
                            DB::table('transfers')->where('TransferID', $transfer->TransferID)->update([
                                'TaxiReceipt_Issued' => 'Ναι',
                                'ElorusTaxiNumber' => $this->prepend('ElorusTaxiNumber', $credit['number']),
                            ]);
                        });
                    }

                    if ($this->wantsPdf($transfer, $pdf, withCustomers: true)) {
                        $this->attempt($run, "Transfer {$transfer->TransferID} PDF", function () use ($run, $transfer, $doc): void {
                            $this->savePdf($run, $transfer->TransferID, $doc['id'], $doc['number'].'.pdf');
                            DB::table('transfers')->where('TransferID', $transfer->TransferID)->update(['FLNM' => 'ΑΠΥ']);
                        });
                        if ($credit) {
                            $this->attempt($run, "Transfer {$transfer->TransferID} credit note PDF", fn () => $this->savePdf($run, $transfer->TransferID, $credit['id'], $credit['number'].'.pdf', 'creditnotes'));
                        }
                    }
                });
            }
        });
    }

    /**
     * "Get Invoice PDF": downloads the PDF of the transfers' existing Elorus documents.
     *
     * @param  Collection<int, Transfer>  $transfers
     */
    public function downloadPdfs(Collection $transfers, string $pdf): InvoiceRun
    {
        return $this->run(function (InvoiceRun $run) use ($transfers, $pdf): void {
            foreach ($transfers as $transfer) {
                if (! $this->wantsPdf($transfer, $pdf, withCustomers: false)) {
                    continue;
                }
                if (blank($transfer->ElorusID)) {
                    $run->failed[] = "Transfer {$transfer->TransferID}: no Elorus document yet";

                    continue;
                }
                $this->attempt($run, "Transfer {$transfer->TransferID}", fn () => $this->savePdf($run, $transfer->TransferID, $transfer->ElorusID, $transfer->SalesInvoiceNum.'.pdf'));
            }
        });
    }

    /**
     * "Issue Receipt" on charges (New_Button29): one receipt per charge.
     *
     * @param  Collection<int, TransferCharge>  $charges
     */
    public function chargeReceipts(Collection $charges, bool $draft): InvoiceRun
    {
        return $this->run(function (InvoiceRun $run) use ($charges, $draft): void {
            $billing = $this->privateIndividualBilling();

            foreach ($charges as $charge) {
                $this->attempt($run, "Charge {$charge->ID}", function () use ($run, $charge, $billing, $draft): void {
                    $doc = $this->elorus->createInvoice($this->document($billing, Carbon::today()->format('Y-m-d'), $draft, [
                        $this->line($billing, '', (float) $charge->ChargeValue),
                    ]));
                    $run->created[] = $doc['number'] ?: $doc['id'];

                    DB::table('transfer_charges')->where('ID', $charge->ID)->update([
                        'Invoiced' => 1,
                        'ElorusID' => $doc['id'],
                        'InvoiceNum' => $this->prepend('InvoiceNum', $doc['number']),
                    ]);
                });
            }
        });
    }

    // ---------------------------------------------------------------------------------

    /** Same lead name the way MariaDB compares it (case, accents, trailing spaces ignored). */
    private static function leadKey(?string $name): string
    {
        $name = mb_strtolower(rtrim((string) $name));
        $decomposed = \Normalizer::normalize($name, \Normalizer::FORM_D);

        return (string) preg_replace('/\p{Mn}/u', '', $decomposed === false ? $name : $decomposed);
    }

    private function run(callable $work): InvoiceRun
    {
        $run = new InvoiceRun;

        if (! config('elorus.enabled')) {
            $run->failed[] = 'Elorus is switched off on this copy of the app (ELORUS_ENABLED=false in .env).';

            return $run;
        }

        $this->attempt($run, null, fn () => $work($run));

        return $run;
    }

    private function attempt(InvoiceRun $run, ?string $what, callable $work): void
    {
        try {
            $work();
        } catch (ElorusException $e) {
            $run->failed[] = ($what ? "{$what}: " : '').$e->getMessage();
        } catch (ConnectionException) {
            $run->failed[] = ($what ? "{$what}: " : '').'Elorus did not answer in time. Check in Elorus whether the document was created before trying again.';
        } catch (Throwable $e) {
            report($e);
            $run->failed[] = ($what ? "{$what}: " : '').'unexpected error ('.class_basename($e).')';
        }
    }

    /** @param  Collection<int, Transfer>  $transfers */
    private function issueInvoice(InvoiceRun $run, Collection $transfers, object $billing, string $date, bool $draft, array $lines): void
    {
        $doc = $this->elorus->createInvoice($this->document($billing, $date, $draft, $lines));
        $run->created[] = $doc['number'] ?: $doc['id'];
        $this->markInvoiced($transfers->pluck('TransferID')->all(), $doc);
    }

    /** @param  Collection<int, Transfer>  $transfers */
    private function transferLines(Collection $transfers, object $billing): array
    {
        $clean = fn (?string $text): string => (string) preg_replace('/[^\PCc^\PCn^\PCs]/u', '', (string) $text);

        return $transfers->map(fn (Transfer $t): array => $this->line(
            $billing,
            $clean($t->From).' - '.$clean($t->To).', '.$t->TransferDate?->format('Y-m-d').', '.$t->TransferTime.', '.$t->Pax.' pax, Name: '.$t->LeadName,
            (float) $t->SalePrice,
        ))->values()->all();
    }

    private function document(object $billing, string $date, bool $draft, array $lines): array
    {
        return [
            'client' => $billing->ElorusID,
            'date' => $date,
            'draft' => $draft,
            'documenttype' => $billing->DocumentType,
            'calculator_mode' => 'total',
            'items' => $lines,
            'mydata_document_type' => $billing->MydataDocumentType,
        ];
    }

    private function line(object $billing, string $description, float $value): array
    {
        $amount = (string) round($value, 2);

        return [
            'product' => $this->elorus->productId(),
            'description' => $description,
            'quantity' => '1',
            'accounts' => [[
                'mydata_classification_category' => $billing->MydataClassCategory,
                'mydata_classification_type' => $billing->MydataClassType,
            ]],
            'unit_value' => $amount,
            'unit_total' => $amount,
        ];
    }

    /** @param  Collection<int, Transfer>  $transfers */
    private function singleCustomer(Collection $transfers): int
    {
        $customers = $transfers->pluck('cID_Customer')->unique();

        if ($customers->count() !== 1) {
            throw new ElorusException('One invoice can only be for one customer; the selected transfers belong to '.$customers->count().' customers.');
        }

        return (int) $customers->first();
    }

    private function customerBilling(int $customerId): object
    {
        return $this->billing(DB::table('counterparties')->where('CID', $customerId)->first(), "customer {$customerId}");
    }

    private function privateIndividualBilling(): object
    {
        $name = config('elorus.private_individual');

        return $this->billing(DB::table('counterparties')->where('CounterShortName', $name)->first(), "\"{$name}\"");
    }

    private function billing(?object $counterparty, string $label): object
    {
        if (! $counterparty || blank($counterparty->ElorusID) || $counterparty->ElorusID === 'NotRegistered') {
            throw new ElorusException("No Elorus contact for {$label} (counterparties.ElorusID).");
        }

        return $counterparty;
    }

    /** Get invoice? All / Auto (platform tours and long transfers) / No. */
    private function wantsPdf(Transfer $transfer, string $mode, bool $withCustomers): bool
    {
        return match ($mode) {
            self::PDF_ALL => true,
            self::PDF_AUTO => $transfer->Platform === 'Ναι' && (
                in_array($transfer->TransferType, ['Tour', 'Long Transfer'], true)
                || ($withCustomers && in_array((int) $transfer->cID_Customer, config('elorus.auto_pdf_customers', []), true))
            ),
            default => false,
        };
    }

    private function savePdf(InvoiceRun $run, int $transferId, string $documentId, string $filename, string $kind = 'invoices'): void
    {
        DB::table('myat_transfers_files')->insert([
            'TransferID' => $transferId,
            'Attachment' => $this->elorus->pdf($documentId, $kind),
            'Flnm' => $filename,
            'Type' => config('elorus.pdf_file_type'),
        ]);
        $run->pdfs++;
    }

    /** @param  list<int>  $transferIds */
    private function markInvoiced(array $transferIds, array $doc): void
    {
        DB::table('transfers')->whereIn('TransferID', $transferIds)->update([
            'InvoicedIssued' => 'Ναι',
            'ElorusID' => $doc['id'],
            'SalesInvoiceNum' => $this->prepend('SalesInvoiceNum', $doc['number']),
        ]);
    }

    /** PHPRunner: new number first, then what was already there. */
    private function prepend(string $column, string $number): mixed
    {
        return DB::raw('if(`'.$column.'` is not null, concat('.DB::getPdo()->quote($number).", ' ', `{$column}`), ".DB::getPdo()->quote($number).')');
    }
}
