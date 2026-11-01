<?php
declare(strict_types=1);

function login_captcha_check(bool $ok, string $message): void
{
    if (!$ok) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$login = file_get_contents(__DIR__ . '/../htdocs/login.php') ?: '';
$lowerLogin = strtolower($login);
login_captcha_check(!str_contains($lowerLogin, 'recaptcha') && !str_contains($login, 'g-recaptcha-response') && !str_contains($login, 'google.com/recaptcha'), 'login page no longer renders or calls reCAPTCHA');
login_captcha_check(str_contains($login, 'verify_csrf();') && str_contains($login, 'password_verify('), 'CSRF validation and password verification remain enabled');
login_captcha_check(str_contains($login, 'WHERE email=:email AND active=1') && str_contains($login, 'session_regenerate_id(true)'), 'login still checks active accounts and rotates the session ID');

echo "OK: reCAPTCHA is disabled while login CSRF and credential checks remain enabled\n";
