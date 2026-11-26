<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
</head>
<body>
    <table width="100%" cellpadding="0" cellspacing="0"> 
        <tr> 
            <td align="center"> 
                <table width="100%" max-width="600px" cellpadding="0" cellspacing="0" style="background:#ffffff; padding:24px; border-radius:6px;">
                    <tr> 
                        <td> 
                            <h2>Hello {{ $user->name }},</h2>
                            <p>Your account has been created.</p> <p>Please click the button below to activate your account:</p> 
                            <table cellpadding="0" cellspacing="0" style="margin:24px 0;"> 
                                <tr> 
                                    <td align="center"> 
                                        <a href="{{ $user->activation_url }}" style=" display:inline-block; padding:14px 24px; background-color:#11c14d; color:#ffffff; text-decoration:none; font-size:16px; font-weight:bold; border-radius:6px; "> Activate Account </a>
                                    </td> 
                                </tr> 
                            </table> 
                            <p style="font-size:14px; color:#555;"> If you did not register, please ignore this email. </p> 
                            <p style="font-size:12px; color:#999;"> If the button does not work, copy and paste this link into your browser:<br> <span style="word-break:break-all;"> {{ $user->activation_url }} </span> </p> 
                        </td> 
                    </tr> 
                </table> 
            </td> 
        </tr> 
    </table>
</body>
</html>
