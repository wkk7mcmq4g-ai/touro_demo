{{-- PHPRunner users BeforeAdd: welcome e-mail with the login details. --}}
<x-mail.layout
    title="Καλώς ήρθατε"
    eyebrow="Λογαριασμός χρήστη"
    :preheader="'Ο λογαριασμός σας στο '.config('business.name').' είναι έτοιμος'"
>
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
        <tr><td class="mx-t" style="font-size:15px;line-height:1.6;color:#0f172a;padding-bottom:14px">
            {{ $user->fullname ?: $user->username }}, δημιουργήθηκε ο λογαριασμός σας στο {{ config('business.name') }}.
        </td></tr>
    </table>
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" class="mx-soft" style="background:#f8fafc;border-radius:10px">
        <tr><td class="mx-m" style="padding:14px 16px;font-size:14px;line-height:1.9;color:#475569">
            <strong class="mx-t" style="color:#0f172a">Username:</strong> {{ $user->username }}<br>
            <strong class="mx-t" style="color:#0f172a">Password:</strong> {{ $password }}
        </td></tr>
    </table>
    <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin-top:18px">
        <tr><td style="border-radius:8px;background:{{ config('business.colors.primary') }}">
            <a href="{{ $loginUrl }}" target="_blank" style="display:inline-block;padding:11px 22px;font-size:14px;font-weight:600;color:#ffffff;text-decoration:none">Είσοδος</a>
        </td></tr>
    </table>
</x-mail.layout>
