@php
    $card = $lead->card;
    $theme = $card->themeTokens();
    $cardName = $card->full_name ?: $card->company_name ?: 'Digital business card';
    $recipientName = $lead->name ?: 'there';
    $logoUrl = $card->storageUrl($card->logo);
    $details = array_filter([
        'Name' => $lead->name,
        'Phone' => $lead->phone,
        'Email' => $lead->email,
        'Company' => $lead->company,
        'Comment' => $lead->comment,
    ], static fn ($value) => filled($value));
@endphp
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $card->lead_confirmation_subject ?: \DigitalCardKit\Laravel\Support\Config::mailSubject('confirmation') }}</title>
</head>
<body style="margin:0;padding:0;background:{{ $theme['page_background'] }};color:{{ $theme['text'] }};font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Arial,sans-serif;">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="width:100%;background:{{ $theme['page_background'] }};">
<tr><td align="center" style="padding:28px 12px;">
    <table role="presentation" width="600" cellspacing="0" cellpadding="0" style="width:100%;max-width:600px;background:{{ $theme['surface'] }};border:1px solid {{ $theme['border'] }};border-radius:18px;box-shadow:{{ $theme['shadow'] }};">
        <tr><td align="center" style="padding:38px 40px 30px;">
            @if($logoUrl)
                <img src="{{ $logoUrl }}" alt="{{ $card->company_name ?: $cardName }}" style="display:block;max-width:160px;max-height:56px;width:auto;height:auto;margin:0 auto;">
            @else
                <div style="font-size:24px;font-weight:700;line-height:1.2;color:{{ $theme['accent'] }};">{{ $card->company_name ?: $cardName }}</div>
            @endif
        </td></tr>
        <tr><td style="padding:0 40px;"><div style="height:1px;background:{{ $theme['border'] }};"></div></td></tr>
        <tr><td style="padding:34px 40px 40px;">
            <h1 style="margin:0 0 24px;font-size:22px;line-height:1.35;color:{{ $theme['text'] }};">Hello, {{ $recipientName }}</h1>
            <p style="margin:0 0 20px;font-size:16px;line-height:1.55;color:{{ $theme['text'] }};">
                Your contact details were successfully shared with {{ $cardName }}.
            </p>
            @if($details !== [])
                <p style="margin:0 0 14px;font-size:16px;line-height:1.55;color:{{ $theme['text'] }};">Details you shared:</p>
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="width:100%;margin:0 0 24px;">
                    @foreach($details as $label => $value)
                        <tr>
                            <td style="padding:4px 12px 4px 0;width:90px;vertical-align:top;font-size:15px;line-height:1.45;color:{{ $theme['muted_text'] }};"><strong>{{ $label }}:</strong></td>
                            <td style="padding:4px 0;vertical-align:top;font-size:15px;line-height:1.45;color:{{ $theme['text'] }};word-break:break-word;">
                                @if($label === 'Email')
                                    <a href="mailto:{{ $value }}" style="color:{{ $theme['accent'] }};">{{ $value }}</a>
                                @else
                                    {{ $value }}
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </table>
            @endif
            <p style="margin:0;font-size:16px;line-height:1.55;color:{{ $theme['text'] }};">
                Keep the card close at hand:
                <a href="{{ $card->publicUrl() }}" style="color:{{ $theme['accent'] }};font-weight:600;">{{ $cardName }}</a>
            </p>
        </td></tr>
        <tr><td style="padding:0 40px;"><div style="height:1px;background:{{ $theme['border'] }};"></div></td></tr>
        <tr><td align="center" style="padding:22px 40px;color:{{ $theme['muted_text'] }};font-size:12px;line-height:1.5;">
            This message confirms a contact exchange you initiated.
        </td></tr>
    </table>
</td></tr>
</table>
</body>
</html>
