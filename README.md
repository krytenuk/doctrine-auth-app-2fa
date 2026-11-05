FwsDoctrineAuth authenticator app
=================================

Two factor authentication with an authenticator app, such as Google Authenticator, Microsoft Authenticator or 1Password,
for [`krytenuk/doctrine-auth`](https://github.com/krytenuk/doctrine-auth). Like that module it has no pages, forms or
routes. It checks codes and reports the outcome, and your application decides what to show.

Users scan a QR code once, and from then on enter the 6 digit code from their app after their password. They also get
10 single use recovery codes, for when they do not have their phone.

Installation
------------

```bash
composer require krytenuk/doctrine-auth-app-2fa
composer require endroid/qr-code # Optional, to draw the QR code
```

Add `FwsDoctrineAuth\AppTwoFactor\ConfigProvider` to your config aggregator, if the component installer has not done so
already. Copy `config/doctrine-auth-app-2fa.local.php.dist` to your application's
`config/autoload/doctrine-auth-app-2fa.local.php` and set `issuer` to your site's name.

With `roave/psr-container-doctrine`, the plugin adds its entities to the `orm_default` attribute driver. Otherwise, add
`src/Entity` to your mapping paths. Then update your database schema, which adds the `two_factor_app` and
`two_factor_recovery_code` tables.

Logging in
----------

The core module's `TwoFactorLogin` handles this, so follow its README. This plugin's method is called `app`, which is
also `ConfigProvider::METHOD_NAME`. The code field takes either a code from the app or a recovery code, so one form does
for both. Tell the user that.

Each code works only once, so one seen over someone's shoulder cannot be used again. Wrong codes count as failed logins.

Pairing an app
--------------

Use `FwsDoctrineAuth\AppTwoFactor\AuthenticatorAppService` on a page for logged-in users. Send a
`Cache-Control: no-store` header on it, as the page holds the secret.

```php
// Show the page
$pairing = $apps->startPairing($session, $user);
$svg     = $qrCode->svg($pairing->uri); // FwsDoctrineAuth\AppTwoFactor\QrCode, or draw $pairing->uri yourself
// Show $svg, and $pairing->secret for users who cannot scan it. Ask for the code the app then shows

// The code is submitted
$recoveryCodes = $apps->confirmPairing($session, $user, $code);
if ($recoveryCodes === null) {
    // Wrong code. Show the page again: startPairing() gives the same secret, so the user does not have to scan again
} else {
    // Paired. Show the recovery codes once, and ask the user to keep them somewhere safe
}
```

Nothing is saved until the code is right, so a failed scan changes nothing. Call `startPairing($session, $user, true)` for
a new secret, such as when the user wants to start again. Pairing again later replaces the old app and its recovery codes,
for when someone gets a new phone.

Managing it
-----------

```php
$apps->isPaired($user);
$apps->countRecoveryCodes($user);           // Unused codes left
$apps->regenerateRecoveryCodes($user);      // New codes to show once. The old ones stop working
$apps->unpair($user);                       // Turns two factor authentication off for the app
```

**Ask for the user's password or a current code before `unpair()` or `regenerateRecoveryCodes()`,** so that someone at a
computer left logged in cannot turn the protection off. `AuthenticatorAppMethod::verify($user, $code)` checks a code.

Events
------

Listen for these to tell the user by email, in case it was not them. Add listeners to the `doctrine-auth.listeners`
config, as the core module's README describes.

| Event | When |
|---|---|
| `Event\AuthenticatorAppPaired` | An app was paired, including one that replaced an earlier app |
| `Event\AuthenticatorAppRemoved` | The app was removed |
| `Event\RecoveryCodesRegenerated` | New recovery codes replaced the old ones |
| `Event\RecoveryCodeUsed` | A recovery code was used to log in. `$remaining` says how many are left |

Configuration
-------------

```php
'doctrine-auth' => [
    'app_2fa' => [
        'issuer'         => 'My Site', // Required. Shown next to the codes in the user's app
        'window'         => 1,         // 30 second steps either side of now to accept, for phone clocks that are out
        'recovery_codes' => 10,
    ],
    'two_factor' => [
        'methods' => ['app' => null], // Turns the method off without uninstalling it
    ],
],
```

The secrets are stored as they are, as they are needed to check codes. Keep the database private, and to store them some
other way, such as encrypted, point `AuthenticatorAppStoreInterface` at your own class. Recovery codes are stored as
hashes.

Upgrading from version 1
------------------------

Version 1 kept the secret in the `settings` column of `auth_methods`, under `secret`, for rows whose `method` is
`google-auth`. Copy those into `two_factor_app`, and users can keep using their app, as long as their user IDs are the
same. Recovery codes are new, so ask users to make some. For MySQL:

```sql
INSERT INTO two_factor_app (user_id, secret, last_timestep, created_at)
SELECT user_id, JSON_UNQUOTE(JSON_EXTRACT(settings, '$.secret')), NULL, date_created
FROM auth_methods
WHERE method = 'google-auth' AND JSON_EXTRACT(settings, '$.secret') IS NOT NULL;
```

Installs from before version 1.0 kept the secret in the `google_auth` table instead, joined to `auth_methods` by
`auth_method_id`.

Testing
-------

Some tests need `pdo_sqlite`, and are skipped without it. To run them all with Docker, with the core module checked out
next to this one:

```bash
docker run --rm --user "$(id -u):$(id -g)" -v "$PWD":/app -v "$PWD/../doctrine-auth":/doctrine-auth -w /app \
    php:8.4-cli vendor/bin/phpunit
```
