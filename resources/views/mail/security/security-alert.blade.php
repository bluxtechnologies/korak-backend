<x-mail::message>
# {{ $title }}

{{ $intro }}

@if(!empty($details))
<div style="background-color: #F9FAFB; border: 1px solid #E5E7EB; border-radius: 5px; padding: 14px 18px; margin: 20px 0; font-size: 13px; color: #4B5563;">
    <table cellpadding="0" cellspacing="0" border="0" width="100%" style="font-size: 13px;">
@foreach($details as $label => $val)
        <tr>
            <td style="color: #6B7280; padding: 4px 0; width: 130px;">{{ $label }}:</td>
            <td style="color: #111827; font-weight: 500; padding: 4px 0;">{{ $val }}</td>
        </tr>
@endforeach
    </table>
</div>
@endif

@if(!empty($actionText) && !empty($actionUrl))
<x-mail::button :url="$actionUrl">
{{ $actionText }}
</x-mail::button>
@endif

@if(!empty($warning))
<x-mail::panel>
**Security Advisory**  
{{ $warning }}
</x-mail::panel>
@endif

Thanks,<br>
The Korak Digitals Team
</x-mail::message>
