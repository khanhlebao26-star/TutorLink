<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <meta name="supported-color-schemes" content="light">
    <title>Xác minh email | TutorLink</title>
    <style>
        @media only screen and (max-width: 620px) {
            .outer-padding { padding: 24px 12px !important; }
            .content-padding { padding-left: 24px !important; padding-right: 24px !important; }
            .email-title { font-size: 29px !important; line-height: 37px !important; }
            .verify-button { display: block !important; }
        }
    </style>
</head>
<body style="margin:0;padding:0;width:100%;background-color:#f4f2fc;color:#101b4c;font-family:Arial,Helvetica,sans-serif;-webkit-text-size-adjust:100%;">
    <div style="display:none;font-size:1px;line-height:1px;color:#f4f2fc;max-height:0;max-width:0;opacity:0;overflow:hidden;mso-hide:all;">Hoàn tất xác minh email để bắt đầu cùng TutorLink. Liên kết có hiệu lực trong {{ $expiresInMinutes }} phút.</div>
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color:#f4f2fc;">
        <tr>
            <td class="outer-padding" align="center" style="padding:44px 20px;">
                <!--[if mso]><table role="presentation" width="580" cellspacing="0" cellpadding="0" border="0"><tr><td><![endif]-->
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="max-width:580px;">
                    <tr>
                        <td align="center" style="padding:0 0 25px;">
                            <span style="font-size:30px;line-height:36px;font-weight:700;letter-spacing:-1.4px;color:#101b4c;">Tutor<span style="color:#ed168d;">Link</span><span style="color:#ed168d;font-size:20px;">✦</span></span>
                            <p style="margin:6px 0 0;font-size:12px;line-height:18px;color:#656b86;">Kết nối tri thức. Cùng nhau tiến xa.</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="background-color:#ffffff;border:1px solid #e5e3f0;border-radius:20px;overflow:hidden;">
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
                                <tr><td style="height:5px;background-color:#ed168d;border-radius:20px 20px 0 0;font-size:1px;line-height:5px;">&nbsp;</td></tr>
                                <tr>
                                    <td class="content-padding" style="padding:38px 40px 0;">
                                        <table role="presentation" cellspacing="0" cellpadding="0" border="0"><tr><td style="padding:7px 12px;background-color:#fff0f8;border-radius:8px;color:#b21068;font-size:10px;line-height:15px;font-weight:700;letter-spacing:1.4px;">CHÀO MỪNG ĐẾN TUTORLINK</td></tr></table>
                                        <h1 class="email-title" style="margin:22px 0 16px;font-size:34px;line-height:43px;font-weight:700;letter-spacing:-1px;color:#101b4c;">Chỉ còn một bước<br>để bắt đầu.</h1>
                                        <p style="margin:0 0 12px;font-size:16px;line-height:26px;color:#101b4c;">Xin chào <strong>{{ $name ?: 'bạn' }}</strong>,</p>
                                        <p style="margin:0;font-size:15px;line-height:25px;color:#62677f;">Cảm ơn bạn đã tham gia TutorLink. Hãy xác minh địa chỉ email để hoàn tất bước đăng ký tài khoản của bạn.</p>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="content-padding" style="padding:24px 40px 0;">
                                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color:#f7f7fc;border:1px solid #ebebf4;border-radius:12px;">
                                            <tr><td style="padding:16px 18px;"><p style="margin:0 0 5px;font-size:11px;line-height:16px;font-weight:700;letter-spacing:1px;color:#757a91;">EMAIL CỦA BẠN</p><p style="margin:0;font-size:15px;line-height:23px;color:#101b4c;word-break:break-all;">{{ $email }}</p></td></tr>
                                        </table>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="content-padding" style="padding:26px 40px 0;">
                                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0"><tr><td align="center" bgcolor="#ed168d" style="background-color:#ed168d;border-radius:10px;mso-padding-alt:17px 24px;"><a class="verify-button" href="{{ $verificationUrl }}" style="display:block;padding:17px 24px;font-size:15px;line-height:22px;font-weight:700;text-decoration:none;color:#ffffff;border-radius:10px;">Xác minh email của tôi &rarr;</a></td></tr></table>
                                        <p style="margin:13px 0 0;text-align:center;font-size:12px;line-height:20px;color:#757a91;">Liên kết có hiệu lực trong <strong style="color:#3d4464;">{{ $expiresInMinutes }} phút</strong>.</p>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="content-padding" style="padding:27px 40px 0;">
                                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0"><tr><td style="border-top:1px solid #eeeef5;padding-top:23px;">
                                            <p style="margin:0 0 9px;font-size:12px;line-height:20px;color:#62677f;">Nút không hoạt động? Sao chép liên kết dưới đây và mở trong trình duyệt:</p>
                                            <p style="margin:0;font-size:11px;line-height:19px;word-break:break-all;overflow-wrap:anywhere;"><a href="{{ $verificationUrl }}" style="color:#a40c60;text-decoration:underline;word-break:break-all;overflow-wrap:anywhere;">{{ $verificationUrl }}</a></p>
                                        </td></tr></table>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="content-padding" style="padding:23px 40px 34px;"><p style="margin:0;font-size:12px;line-height:20px;color:#757a91;">Nếu bạn không đăng ký TutorLink, bạn có thể bỏ qua email này. Vui lòng không chia sẻ liên kết xác minh với người khác.</p></td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr><td align="center" style="padding:23px 18px 0;"><p style="margin:0;font-size:11px;line-height:19px;color:#7c8096;">Email tự động từ TutorLink · Vui lòng không trả lời email này.</p></td></tr>
                </table>
                <!--[if mso]></td></tr></table><![endif]-->
            </td>
        </tr>
    </table>
</body>
</html>
