<?php

namespace App\Services\Elorus;

/** What one invoicing button did: documents created and what went wrong. */
class InvoiceRun
{
    /** @var list<string> */
    public array $created = [];

    /** @var list<string> */
    public array $failed = [];

    public int $pdfs = 0;

    public function summary(): string
    {
        $lines = [];

        if ($this->created !== []) {
            $lines[] = count($this->created).' document(s) created: '.implode(', ', $this->created);
        }
        if ($this->pdfs > 0) {
            $lines[] = "{$this->pdfs} PDF(s) saved to Files";
        }

        return implode('. ', [...$lines, ...$this->failed]);
    }
}
