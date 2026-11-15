<x-mail::message>
# Reset Your Password

You are receiving this email because we received a password reset request for your Korak Digitals account. Click the button below to choose a new password:

<x-mail::button :url="$url">
Reset Password
</x-mail::button>

<div style="background-color: #F9FAFB; border: 1px solid #E5E7EB; border-radius: 5px; padding: 14px 18px; margin: 20px 0; font-size: 13px; color: #4B5563;">
    <table cellpadding="0" cellspacing="0" border="0" width="100%" style="font-size: 13px;">
        <tr>
            <td style="color: #6B7280; padding: 4px 0; width: 110px;">Expires:</td>
            <td style="color: #111827; font-weight: 500; padding: 4px 0;">In {{ $expireMinutes }} minutes</td>
        </tr>
        <tr>
            <td style="color: #6B7280; padding: 4px 0; width: 110px;">Security note:</td>
            <td style="color: #4B5563; padding: 4px 0;">This single-use link will be invalidated once used.</td>
        </tr>
    </table>
</div>

If you did not request a password reset, no further action is required. Your account remains secure and your current password will not be changed.

<x-mail::subcopy>
If you're having trouble clicking the "Reset Password" button, copy and paste the URL below into your web browser:  
[{{ $url }}]({{ $url }})
</x-mail::subcopy>

Thanks,<br>
The Korak Digitals Team
</x-mail::message>
