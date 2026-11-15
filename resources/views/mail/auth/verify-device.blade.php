<x-mail::message>
# Verify Your Device

A sign-in attempt was detected from an unrecognized device on your Korak Digitals account. To complete sign-in, enter the 6-digit verification code below:

<div style="background-color: #FDF2F4; border: 1px solid #F3C6D1; border-radius: 5px; text-align: center; padding: 22px 16px; margin: 24px 0;">
    <div style="font-family: 'Courier New', Courier, monospace; font-size: 34px; font-weight: 700; letter-spacing: 8px; color: #7A1F3D; margin-left: 8px;">
        {{ $code }}
    </div>
    <div style="font-size: 12px; color: #7A1F3D; margin-top: 8px; font-weight: 500;">
        Valid for 10 minutes &bull; Single-use
    </div>
</div>

<div style="background-color: #F9FAFB; border: 1px solid #E5E7EB; border-radius: 5px; padding: 14px 18px; margin: 20px 0; font-size: 13px; color: #4B5563;">
    <table cellpadding="0" cellspacing="0" border="0" width="100%" style="font-size: 13px;">
        <tr>
            <td style="color: #6B7280; padding: 4px 0; width: 110px;">Device:</td>
            <td style="color: #111827; font-weight: 500; padding: 4px 0;">{{ $deviceName }}</td>
        </tr>
@if($ipAddress)
        <tr>
            <td style="color: #6B7280; padding: 4px 0; width: 110px;">IP Address:</td>
            <td style="color: #111827; font-weight: 500; padding: 4px 0;">{{ $ipAddress }}</td>
        </tr>
@endif
        <tr>
            <td style="color: #6B7280; padding: 4px 0; width: 110px;">Expires:</td>
            <td style="color: #111827; font-weight: 500; padding: 4px 0;">In 10 minutes</td>
        </tr>
    </table>
</div>

<x-mail::panel>
**Did not attempt this sign in?**  
Someone may have entered your password. Please reset your password immediately to protect your account.
</x-mail::panel>

Thanks,<br>
The Korak Digitals Team
</x-mail::message>
