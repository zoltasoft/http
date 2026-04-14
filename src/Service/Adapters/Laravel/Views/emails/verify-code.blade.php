<!DOCTYPE html>
<html lang="en" style="background-color:#f4f6f8; font-family: Arial, sans-serif; margin:0; padding:0;">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>{{ $subject }}</title>
</head>
<body style="margin:0; padding:0; background-color:#f4f6f8;">
  <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="background-color:#f4f6f8; padding:40px 0;">
    <tr>
      <td align="center">
        <table width="600" cellpadding="0" cellspacing="0" role="presentation" style="background:#ffffff; border-radius:8px; box-shadow:0 2px 6px rgba(0,0,0,0.1); padding:30px;">
          <tr>
            <td style="text-align:center; padding-bottom:20px;">
              <h1 style="color:#333333; font-weight:600; margin:0; font-size:24px;">Verify Your Email Address</h1>
            </td>
          </tr>
          <tr>
            <td style="color:#555555; font-size:16px; line-height:1.5; padding-bottom:20px;">
              <p>Hello,</p>
              <p>Thank you for registering with <strong>{{ $companyName }}</strong>. To complete your registration, please use the verification code below:</p>
            </td>
          </tr>
          <tr>
            <td align="center" style="padding:20px 0;">
              <span style="display:inline-block; font-size:32px; font-weight:bold; letter-spacing:6px; background:#007BFF; color:#ffffff; padding:15px 40px; border-radius:6px; font-family: monospace;">{{ $code }}</span>
            </td>
          </tr>
          <tr>
            <td style="color:#555555; font-size:14px; line-height:1.4; padding-bottom:20px; text-align:center;">
              <p>This code will expire in <strong>10 minutes</strong>.</p>
              <p>If you did not request this verification, please ignore this email.</p>
            </td>
          </tr>
          <tr>
            <td style="border-top:1px solid #e0e0e0; padding-top:15px; font-size:14px; color:#999999; text-align:center;">
              <p>Thank you,<br><strong>{{ CompanyName }} Team</strong></p>
            </td>
          </tr>
        </table>
      </td>
    </tr>
  </table>
</body>
</html>
