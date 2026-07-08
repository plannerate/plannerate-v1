@php
    /**
     * E-mail de credenciais de acesso / definição de senha — marca Plannerate.
     *
     * Layout table-based com estilos inline para máxima compatibilidade entre
     * clientes de e-mail. O logo é embutido via CID ($message->embed) quando há
     * mensagem em construção (envio real); em contexto de render sem mensagem,
     * cai para a URL absoluta hospedada no host do sistema.
     */
    $green      = '#A8FF2E'; // verde da marca (amostrado do logo)
    $headerBg   = '#0d0d0d';
    $outerBg    = '#f3f4f6';
    $heading    = '#111827';
    $text       = '#374151';
    $muted      = '#6b7280';
    $border     = '#e5e7eb';

    $logoSrc = isset($message)
        ? $message->embed(public_path('img/marcadark.png'))
        : rtrim($systemUrl, '/').'/img/marcadark.png';
@endphp
<!DOCTYPE html>
<html lang="pt-BR" xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="color-scheme" content="light only">
    <meta name="supported-color-schemes" content="light only">
    <title>{{ __('app.password_setup.mail.subject') }}</title>
    <style>
        /* Reset básico e responsividade */
        body { margin: 0; padding: 0; width: 100% !important; background-color: {{ $outerBg }}; }
        table { border-collapse: collapse; }
        img { border: 0; line-height: 100%; outline: none; text-decoration: none; -ms-interpolation-mode: bicubic; }
        a { text-decoration: none; }
        @media only screen and (max-width: 620px) {
            .container { width: 100% !important; }
            .px { padding-left: 24px !important; padding-right: 24px !important; }
            .header-sub { display: none !important; }
        }
    </style>
</head>
<body style="margin:0; padding:0; background-color:{{ $outerBg }};">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:{{ $outerBg }};">
        <tr>
            <td align="center" style="padding: 32px 16px;">

                <!-- Cartão principal -->
                <table role="presentation" class="container" width="600" cellpadding="0" cellspacing="0" style="width:600px; max-width:600px; background-color:#ffffff; border-radius:10px; overflow:hidden; box-shadow:0 1px 3px rgba(0,0,0,0.08); font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;">

                    <!-- Header escuro com logo + subtítulo -->
                    <tr>
                        <td style="background-color:{{ $headerBg }}; padding: 22px 32px;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td valign="middle" style="white-space:nowrap;">
                                        <img src="{{ $logoSrc }}" height="26" alt="Plannerate" style="height:26px; width:auto; display:inline-block; vertical-align:middle;">
                                    </td>
                                    <td valign="middle" align="right" class="header-sub" style="border-left:1px solid #3a3a3a; padding-left:16px; color:#a1a1aa; font-size:13px; font-weight:500;">
                                        {{ __('app.password_setup.mail.header_subtitle') }}
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <!-- Corpo -->
                    <tr>
                        <td class="px" style="padding: 36px 40px 8px 40px;">
                            <p style="margin:0 0 18px; color:{{ $heading }}; font-size:16px; font-weight:600;">
                                {{ __('app.password_setup.mail.greeting', ['name' => $name]) }}
                            </p>
                            <p style="margin:0 0 16px; color:{{ $text }}; font-size:15px; line-height:1.6;">
                                {{ $isResend ? __('app.password_setup.mail.resend_intro') : __('app.password_setup.mail.intro') }}
                            </p>
                            <p style="margin:0 0 24px; color:{{ $text }}; font-size:15px; line-height:1.6;">
                                {{ __('app.password_setup.mail.instructions') }}
                            </p>
                        </td>
                    </tr>

                    <!-- Cartão de credenciais -->
                    <tr>
                        <td class="px" style="padding: 0 40px;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid {{ $border }}; border-radius:8px; background-color:#fbfbfb;">
                                <tr>
                                    <td style="padding:16px 20px; border-bottom:1px solid {{ $border }};">
                                        <div style="color:{{ $muted }}; font-size:11px; font-weight:700; letter-spacing:0.6px; text-transform:uppercase; margin-bottom:5px;">
                                            {{ __('app.password_setup.mail.label_system') }}
                                        </div>
                                        <a href="{{ $systemUrl }}" style="color:{{ $heading }}; font-size:14px; word-break:break-all;">{{ $systemUrl }}</a>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding:16px 20px; border-bottom:1px solid {{ $border }};">
                                        <div style="color:{{ $muted }}; font-size:11px; font-weight:700; letter-spacing:0.6px; text-transform:uppercase; margin-bottom:5px;">
                                            {{ __('app.password_setup.mail.label_username') }}
                                        </div>
                                        <span style="color:#4d7c0f; font-size:14px; font-weight:600; word-break:break-all;">{{ $username }}</span>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding:16px 20px;">
                                        <div style="color:{{ $muted }}; font-size:11px; font-weight:700; letter-spacing:0.6px; text-transform:uppercase; margin-bottom:5px;">
                                            {{ __('app.password_setup.mail.label_password') }}
                                        </div>
                                        <span style="color:{{ $text }}; font-size:14px;">{{ __('app.password_setup.mail.password_value') }}</span>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <!-- Botão de ação -->
                    <tr>
                        <td class="px" style="padding: 28px 40px 8px 40px;">
                            <table role="presentation" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td align="center" style="border-radius:8px; background-color:{{ $green }};">
                                        <a href="{{ $actionUrl }}" target="_blank" style="display:inline-block; padding:14px 30px; color:#0d0d0d; font-size:15px; font-weight:700; border-radius:8px;">
                                            {{ __('app.password_setup.mail.action') }}
                                        </a>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <!-- Avisos de segurança -->
                    <tr>
                        <td class="px" style="padding: 20px 40px 8px 40px;">
                            <p style="margin:0 0 16px; color:{{ $text }}; font-size:14px; line-height:1.6;">
                                {{ __('app.password_setup.mail.security_notice') }}
                            </p>
                            <p style="margin:0 0 16px; color:{{ $text }}; font-size:14px; line-height:1.6;">
                                {{ __('app.password_setup.mail.expiry', ['days' => $expiryDays]) }}
                            </p>
                            <p style="margin:0 0 20px; color:{{ $text }}; font-size:14px; line-height:1.6;">
                                {{ __('app.password_setup.mail.support') }}
                            </p>
                        </td>
                    </tr>

                    <!-- Assinatura -->
                    <tr>
                        <td class="px" style="padding: 0 40px 32px 40px;">
                            <p style="margin:0; color:{{ $text }}; font-size:14px; line-height:1.6;">
                                {{ __('app.password_setup.mail.salutation') }}<br>
                                <strong style="color:{{ $heading }};">{{ __('app.password_setup.mail.team') }}</strong>
                            </p>
                        </td>
                    </tr>

                    <!-- Rodapé legal -->
                    <tr>
                        <td style="padding: 22px 40px 28px 40px; background-color:#fafafa; border-top:1px solid {{ $border }};">
                            <p style="margin:0 0 10px; color:{{ $muted }}; font-size:11px; line-height:1.55;">
                                <strong style="color:#4b5563;">{{ __('app.password_setup.mail.footer.confidentiality_title') }}</strong>
                                {{ __('app.password_setup.mail.footer.confidentiality') }}
                            </p>
                            <p style="margin:0; color:{{ $muted }}; font-size:11px; line-height:1.55;">
                                <strong style="color:#4b5563;">{{ __('app.password_setup.mail.footer.security_title') }}</strong>
                                {{ __('app.password_setup.mail.footer.security') }}
                            </p>
                        </td>
                    </tr>

                </table>
                <!-- /Cartão principal -->

            </td>
        </tr>
    </table>
</body>
</html>
