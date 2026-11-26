<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
</head>

<body>

<h2>BOAConnect</h2>

<p>Your verification code is:</p>

<h1 style="letter-spacing:8px">
    {{ $code }}
</h1>

<p>
This code will expire in
{{ config('verification.otp.expires') }}
minutes.
</p>

<p>
If you did not request this verification code,
please ignore this email.
</p>

</body>

</html>