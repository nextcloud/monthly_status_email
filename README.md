# Montly Status email

Send monthly status mails to users. This app doesn't provide a user interface.

Per default it sends a summary of used storage along with some usage hints.

The default messages are in German.

## Editing the messages sent

The messages sent to users are defined at `lib/Service/MessageProvider.php`.

To overwrite the default messages, create a new class inheriting from `MessageProvider`
and overwrite the desired methods.

Then configure your `MessageProvider` in `config.php`:

```php
[
   ...,
   'status-email-message-provider' => '\OCA\MyCustomApp\MyMessageProvider',
]
```

## Mail sending limits

In order to avoid mail floods, the app sends mails in hourly batches. Default maximum
is 1000 mails per hour. This limit can be changed via `status-email-max-mail-sent` in
the app config:

```bash
php occ config:app:set monthly_status_email status-email-max-mail-sent --value=2500
```

## Sending welcome mails

By default, this app sends a welcome mail to new users after they logged in for
the first time. This can be disabled with the following switch in config.php:

```php
[
    ...,
    'status-email-send-first-login-mail' => false
]
```

## Licensing

This project is licensed under AGPL-3.0-or-later.
