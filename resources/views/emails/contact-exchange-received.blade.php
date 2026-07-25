<x-mail::message>
# New contact

Card: **{{ $lead->card->full_name }}**

@foreach (array_filter([
    'Name' => $lead->name,
    'Phone' => $lead->phone,
    'Email' => $lead->email,
    'Company' => $lead->company,
    'Comment' => $lead->comment,
]) as $label => $value)
**{{ $label }}:** {{ $value }}

@endforeach
@foreach ($lead->custom_data ?: [] as $label => $value)
**{{ $label }}:** {{ $value }}

@endforeach
</x-mail::message>
