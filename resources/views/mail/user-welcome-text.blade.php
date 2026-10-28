{{ $user->fullname ?: $user->username }}, δημιουργήθηκε ο λογαριασμός σας στο {{ config('business.name') }}.

Username: {{ $user->username }}
Password: {{ $password }}

Είσοδος: {{ $loginUrl }}
