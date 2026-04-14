<!DOCTYPE html>
<html lang="en" style="background-color:#f9fafb; font-family: Arial, sans-serif; margin:0; padding:0;">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>{{ $subject }}</title>
</head>
<body style="margin:0; padding:0; background-color:#f9fafb;">
  <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="background-color:#f9fafb; padding:40px 0;">
    <tr>
      <td align="center">
        <table width="600" cellpadding="0" cellspacing="0" role="presentation" style="background:#ffffff; border-radius:10px; box-shadow:0 3px 8px rgba(0,0,0,0.12); padding:40px;">
          <tr>
            <td style="text-align:center; padding-bottom:30px;">
              <h1 style="color:#222222; font-weight:700; margin:0; font-size:28px;">Welcome to {{ $companyName }}!</h1>
            </td>
          </tr>
          <tr>
            <td style="color:#444444; font-size:16px; line-height:1.6; padding-bottom:25px;">
              <p>Hello <strong>{{ $username }}</strong>,</p>
              <p>We're thrilled to have you on board. At <strong>Zolta</strong>, we're committed to helping you connect with the best opportunities and grow your career.</p>
              <p>If you have any questions or need assistance, feel free to reach out to our support team anytime.</p>
            </td>
          </tr>
          <tr>
            <td align="center" style="padding:25px 0;">
              <a href="{{ url('/') }}" style="background-color:#007BFF; color:#ffffff; text-decoration:none; font-weight:600; padding:15px 40px; border-radius:6px; display:inline-block;">Get Started</a>
            </td>
          </tr>
          <tr>
            <td style="color:#777777; font-size:14px; line-height:1.4; text-align:center;">
              <p>Thank you for joining us!</p>
              <p><strong>{{ $companyName }} Team</strong></p>
            </td>
          </tr>
          <tr>
            <td style="border-top:1px solid #e0e0e0; padding-top:20px; font-size:12px; color:#999999; text-align:center;">
              <p>This is an automated message, please do not reply.</p>
            </td>
          </tr>
        </table>
      </td>
    </tr>
  </table>
</body>
</html>
