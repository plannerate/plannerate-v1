{{ __('app.password_setup.mail.header_subtitle') }}
==============================================

{{ __('app.password_setup.mail.greeting', ['name' => $name]) }}

{{ $isResend ? __('app.password_setup.mail.resend_intro') : __('app.password_setup.mail.intro') }}

{{ __('app.password_setup.mail.instructions') }}

{{ __('app.password_setup.mail.label_system') }}: {{ $systemUrl }}
{{ __('app.password_setup.mail.label_username') }}: {{ $username }}
{{ __('app.password_setup.mail.label_password') }}: {{ __('app.password_setup.mail.password_value') }}

{{ __('app.password_setup.mail.action') }}:
{{ $actionUrl }}

{{ __('app.password_setup.mail.security_notice') }}

{{ __('app.password_setup.mail.expiry', ['days' => $expiryDays]) }}

{{ __('app.password_setup.mail.support') }}

{{ __('app.password_setup.mail.salutation') }}
{{ __('app.password_setup.mail.team') }}

----------------------------------------------
{{ __('app.password_setup.mail.footer.confidentiality_title') }} {{ __('app.password_setup.mail.footer.confidentiality') }}

{{ __('app.password_setup.mail.footer.security_title') }} {{ __('app.password_setup.mail.footer.security') }}
