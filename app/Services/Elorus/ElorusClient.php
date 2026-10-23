<?php

namespace App\Services\Elorus;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * Elorus REST API (v1.2), with the credentials PHPRunner uses (elorus_Settings),
 * unless ELORUS_API_TOKEN / ELORUS_ORGANIZATION_ID are set in .env.
 */
class ElorusClient
{
    /** @var array{organization: string, token: string, product: string}|null */
    private ?array $settings = null;

    /** @return array{id: string, number: string} */
    public function createInvoice(array $document): array
    {
        return $this->created($this->request()->post('invoices/', $document));
    }

    /** @return array{id: string, number: string} */
    public function createCreditNote(array $document): array
    {
        return $this->created($this->request()->post('creditnotes/', $document));
    }

    /** @return array{id: string, number: string} */
    public function createContact(array $contact): array
    {
        return $this->created($this->request()->post('contacts/', $contact));
    }

    /**
     * One page of the Elorus contacts (PHPRunner "Elorus Contacts New", a REST datasource).
     *
     * @return array{count: int, results: list<array<string, mixed>>}
     */
    public function contacts(int $page = 1, int $perPage = 50, ?string $search = null): array
    {
        $response = $this->request()->get('contacts/', array_filter([
            'page' => $page, 'page_size' => $perPage, 'search' => $search,
        ]));

        if ($response->failed()) {
            throw new ElorusException('Elorus did not return the contacts: '.$this->error($response));
        }

        return ['count' => (int) $response->json('count'), 'results' => (array) $response->json('results')];
    }

    /** The PDF of an invoice ('invoices') or credit note ('creditnotes'). */
    public function pdf(string $id, string $kind = 'invoices'): string
    {
        $response = $this->request()->accept('application/pdf')->get("{$kind}/{$id}/pdf/");
        $body = $response->body();

        if ($response->failed() || ! str_starts_with($body, '%PDF')) {
            throw new ElorusException("No PDF for Elorus document {$id}: ".$this->error($response));
        }

        return $body;
    }

    public function productId(): string
    {
        return $this->settings()['product'];
    }

    private function request(): PendingRequest
    {
        if (! config('elorus.enabled')) {
            throw new ElorusException('Elorus is switched off on this copy of the app (ELORUS_ENABLED=false in .env).');
        }

        $settings = $this->settings();

        return Http::baseUrl(config('elorus.url'))
            ->withHeaders([
                'X-Elorus-Organization' => $settings['organization'],
                'Authorization' => 'Token '.$settings['token'],
            ])
            ->acceptJson()
            ->asJson()
            ->connectTimeout(15)
            ->timeout(180);
    }

    /** @return array{organization: string, token: string, product: string} */
    private function settings(): array
    {
        if ($this->settings === null) {
            $row = DB::table('elorus_Settings')->first();
            $this->settings = [
                'organization' => (string) (config('elorus.organization') ?: $row?->OrgID),
                'token' => (string) (config('elorus.token') ?: $row?->apikey),
                'product' => (string) $row?->DefaultProductID,
            ];

            if ($this->settings['organization'] === '' || $this->settings['token'] === '') {
                throw new ElorusException('Elorus credentials are missing (elorus_Settings or .env).');
            }
        }

        return $this->settings;
    }

    /** @return array{id: string, number: string} */
    private function created(Response $response): array
    {
        $id = (string) $response->json('id');

        if ($response->failed() || ! ctype_digit($id)) {
            throw new ElorusException('Elorus refused the document: '.$this->error($response));
        }

        return ['id' => $id, 'number' => (string) $response->json('representation')];
    }

    private function error(Response $response): string
    {
        $json = $response->json();

        if (is_array($json)) {
            return collect($json)
                ->map(fn ($messages, $field) => (is_string($field) ? "{$field}: " : '').implode(' ', (array) (is_array($messages) ? array_map(fn ($m) => is_scalar($m) ? $m : json_encode($m, JSON_UNESCAPED_UNICODE), $messages) : [$messages])))
                ->implode('; ');
        }

        return 'HTTP '.$response->status();
    }
}
