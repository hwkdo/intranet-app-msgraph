@component('mail::message')
# Ablaufwarnung Azure App Secret

Diese E-Mail informiert Sie darüber, dass ein Azure-App-Secret bald abläuft oder bereits abgelaufen ist.

@component('mail::panel')
**App-Name:** {{ $appDisplayName }}

**App-ID (Client-ID):** `{{ $appId }}`

**Secret-Name:** {{ $secretDisplayName }}

**Status:**
@if($daysUntilExpiry < 0)
Das Secret ist seit {{ abs($daysUntilExpiry) }} Tag(en) abgelaufen.
@elseif($daysUntilExpiry === 0)
Das Secret läuft heute ab.
@elseif($daysUntilExpiry === 1)
Das Secret läuft in 1 Tag ab.
@else
Das Secret läuft in {{ $daysUntilExpiry }} Tagen ab.
@endif

@if($endDateTime)
**Ablaufdatum:** {{ $endDateTime->format('d.m.Y') }}
@endif
@endcomponent

Bitte erneuern Sie das Secret in der Azure-Portal-App-Registrierung, um Unterbrechungen zu vermeiden.

@component('mail::button', ['url' => 'https://entra.microsoft.com/#view/Microsoft_AAD_RegisteredApps/ApplicationsList'])
Azure Portal – App-Registrierungen
@endcomponent

{{ config('app.name') }}
@endcomponent
