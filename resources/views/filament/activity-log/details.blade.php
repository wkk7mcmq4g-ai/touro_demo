{{-- Ιστορικό ενεργειών: one row in full (ActivityLogResource "details"). --}}
@php
    $changes = $log->Changes ?? [];
    $show = fn ($v): string => $v === null || $v === '' ? '—' : (is_scalar($v) ? (string) $v : json_encode($v, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
@endphp
<div class="myat-activity">
    <dl class="myat-activity-meta">
        <div><dt>Πότε</dt><dd>{{ $log->CreatedAt?->format('d/m/Y H:i:s') }}</dd></div>
        <div><dt>Χρήστης</dt><dd>{{ $log->Username ?: 'σύστημα' }}</dd></div>
        <div><dt>Έκδοση</dt><dd>{{ $log->AppVersion ?: '—' }}</dd></div>
        <div><dt>IP</dt><dd>{{ $log->IP ?: '—' }}</dd></div>
        <div class="is-wide"><dt>Οθόνη</dt><dd>{{ $log->Url ?: '—' }}</dd></div>
    </dl>

    @if (isset($changes['sql']))
        <p class="myat-activity-h">Εντολή στη βάση</p>
        <pre class="myat-activity-sql">{{ $changes['sql'] }}</pre>
        @if (! empty($changes['bindings']))
            <p class="myat-activity-h">Τιμές</p>
            <ol class="myat-activity-bindings">
                @foreach ($changes['bindings'] as $value)
                    <li>{{ $show($value) }}</li>
                @endforeach
            </ol>
        @endif
    @elseif ($changes !== [])
        <table class="myat-activity-table">
            <thead>
                <tr>
                    <th>Πεδίο</th>
                    @if ($log->Action === 'update')
                        <th>Πριν</th><th>Μετά</th>
                    @else
                        <th>Τιμή</th>
                    @endif
                </tr>
            </thead>
            <tbody>
                @foreach ($changes as $field => $value)
                    <tr>
                        <td class="is-field">{{ $field }}</td>
                        @if ($log->Action === 'update' && is_array($value) && count($value) === 2)
                            <td class="is-old">{{ $show($value[0]) }}</td>
                            <td class="is-new">{{ $show($value[1]) }}</td>
                        @else
                            <td>{{ $show($value) }}</td>
                        @endif
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>
