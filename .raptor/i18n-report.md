=== RELATORIO DE i18n ===
Projeto: /home/call/projects/plannerate-v1
Pacotes: nenhum (somente app principal)
Total: 19 arquivos | 44 ocorrencias

━━━ APP PRINCIPAL ━━━

[resources/js/components/DeleteUser.vue](resources/js/components/DeleteUser.vue) (3 ocorrencias)
- linha 28: "Delete account" -> t('app.delete_account')
- linha 29: "Delete your account and all of its resources" -> t('app.delete_account_description')
- linha 79: "Password" [placeholder] -> t('app.password')

[resources/js/components/ui/spinner/Spinner.vue](resources/js/components/ui/spinner/Spinner.vue) (1 ocorrencia)
- linha 14: "Loading" [aria-label] -> t('app.loading')

[resources/js/components/ui/sidebar/SidebarRail.vue](resources/js/components/ui/sidebar/SidebarRail.vue) (2 ocorrencias)
- linha 17: "Toggle sidebar" [aria-label] -> t('app.toggle_sidebar')
- linha 19: "Toggle sidebar" [title] -> t('app.toggle_sidebar')

[resources/js/pages/auth/ForgotPassword.vue](resources/js/pages/auth/ForgotPassword.vue) (1 ocorrencia)
- linha 25: "Forgot password" -> t('app.auth.forgot_password')

[resources/js/pages/auth/Login.vue](resources/js/pages/auth/Login.vue) (2 ocorrencias)
- linha 30: "Log in" -> t('app.auth.login')
- linha 79: "Password" [placeholder] -> t('app.password')

[resources/js/pages/auth/VerifyEmail.vue](resources/js/pages/auth/VerifyEmail.vue) (1 ocorrencia)
- linha 23: "Email verification" -> t('app.auth.verify_email')

[resources/js/pages/auth/TwoFactorChallenge.vue](resources/js/pages/auth/TwoFactorChallenge.vue) (2 ocorrencias)
- linha 52: "Two-factor authentication" -> t('app.auth.two_factor_authentication')
- linha 112: "Enter recovery code" [placeholder] -> t('app.auth.enter_recovery_code')

[resources/js/pages/auth/ResetPassword.vue](resources/js/pages/auth/ResetPassword.vue) (3 ocorrencias)
- linha 28: "Reset password" -> t('app.auth.reset_password')
- linha 59: "Password" [placeholder] -> t('app.password')
- linha 71: "Confirm password" [placeholder] -> t('app.auth.confirm_password')

[resources/js/pages/auth/ConfirmPassword.vue](resources/js/pages/auth/ConfirmPassword.vue) (1 ocorrencia)
- linha 20: "Confirm password" -> t('app.auth.confirm_password')

[resources/js/pages/auth/Register.vue](resources/js/pages/auth/Register.vue) (4 ocorrencias)
- linha 22: "Register" -> t('app.auth.register')
- linha 41: "Full name" [placeholder] -> t('app.full_name')
- linha 68: "Password" [placeholder] -> t('app.password')
- linha 81: "Confirm password" [placeholder] -> t('app.auth.confirm_password')

[resources/js/pages/landlord/Dashboard.vue](resources/js/pages/landlord/Dashboard.vue) (1 ocorrencia)
- linha 19: "Dashboard" -> t('app.dashboard')

[resources/js/pages/Welcome.vue](resources/js/pages/Welcome.vue) (1 ocorrencia)
- linha 16: "Welcome" -> t('app.welcome')

[resources/js/pages/Dashboard.vue](resources/js/pages/Dashboard.vue) (1 ocorrencia)
- linha 19: "Dashboard" -> t('app.dashboard')

[resources/js/pages/settings/Security.vue](resources/js/pages/settings/Security.vue) (8 ocorrencias)
- linha 47: "Security settings" -> t('app.security_settings')
- linha 54: "Update password" -> t('app.update_password')
- linha 55: "Ensure your account is using a long, random password to stay secure" -> t('app.update_password_description')
- linha 79: "Current password" [placeholder] -> t('app.auth.current_password')
- linha 91: "New password" [placeholder] -> t('app.auth.new_password')
- linha 103: "Confirm password" [placeholder] -> t('app.auth.confirm_password')
- linha 122: "Two-factor authentication" -> t('app.auth.two_factor_authentication')
- linha 123: "Manage your two-factor authentication settings" -> t('app.auth.two_factor_description')

[resources/js/pages/settings/Profile.vue](resources/js/pages/settings/Profile.vue) (5 ocorrencias)
- linha 37: "Profile settings" -> t('app.profile_settings')
- linha 44: "Profile information" -> t('app.profile_information')
- linha 45: "Update your name and email address" -> t('app.profile_information_description')
- linha 62: "Full name" [placeholder] -> t('app.full_name')
- linha 77: "Email address" [placeholder] -> t('app.email')

[resources/js/pages/settings/Appearance.vue](resources/js/pages/settings/Appearance.vue) (3 ocorrencias)
- linha 20: "Appearance settings" -> t('app.appearance_settings')
- linha 27: "Appearance settings" -> t('app.appearance_settings')
- linha 28: "Update your account's appearance settings" -> t('app.appearance_settings_description')

[resources/js/layouts/settings/Layout.vue](resources/js/layouts/settings/Layout.vue) (3 ocorrencias)
- linha 34: "Settings" -> t('app.settings')
- linha 35: "Manage your profile and account settings" -> t('app.settings_description')
- linha 42: "Settings" [aria-label] -> t('app.settings')

[app/Http/Controllers/Settings/ProfileController.php](app/Http/Controllers/Settings/ProfileController.php) (1 ocorrencia)
- linha 41: __('Profile updated.') -> __('app.updated_success', ['resource' => __('app.profile')])

[app/Http/Controllers/Settings/SecurityController.php](app/Http/Controllers/Settings/SecurityController.php) (1 ocorrencia)
- linha 56: __('Password updated.') -> __('app.updated_success', ['resource' => __('app.password')])

━━━ BLADE / ENUM / SERVICES / MODELS ━━━
- Nenhuma ocorrencia de hardcoded relevante encontrada no escopo definido.

━━━ PRE-REQUISITOS i18n (BLOQUEADORES PARA CORRECAO) ━━━
1) Pasta de traducoes ausente: nao existe [lang/](lang)
2) Arquivo base ausente: [lang/pt_BR/app.php](lang/pt_BR/app.php)
3) Locale atual em ingles: [config/app.php](config/app.php#L83) usa APP_LOCALE=en, fallback=en, faker=en_US, timezone=UTC
4) Compartilhamento de traducoes ausente: [app/Http/Middleware/HandleInertiaRequests.php](app/Http/Middleware/HandleInertiaRequests.php) nao expõe `translations`/`locale` em props
5) Composable ausente: [resources/js/composables/useT.ts](resources/js/composables/useT.ts)

━━━━━━━━━━━━━━━━━━━━
Chaves novas a criar em lang/pt_BR/: 36 (estimado)
Chaves reutilizadas de app.php: 8 (estimado)
