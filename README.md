# Montly Status email

Apps allowing to send monthly status emails to cloud customers. This is a internal apps.

## Editing the messages sent

All the messages sent to the users are located inside `lib/Service/MessageProvider.php`.
To overwrite then for internal usage, you need to create a new class inheriting from the
MessageProvider and then overwrite any methods with your own messages.

You can then edit your config.php to tell this app to use your MessageProvider instead.

```php
[
   ...,
   'status-email-message-provider' => '\OCA\MyCustomApp\MyMessageProvider',
]
```

## Mail sending limits

This app will try to spread out as much as possible the amount of mails sent at the same
time and will send them per batch each hour with a maximum of 1000 per hour. This limits
can be changed with the `status-email-max-mail-sent` in app config:


```bash
php occ config:app:set monthly_status_email status-email-max-mail-sent --value=2500
```

## Sending welcome status emails

By default users will get initial mail when they log in for the first time. 
This can be disabled through the following switch in config.php:
```php
[
    ...,
    'status-email-send-first-login-mail' => true
]
```

## Licensing

This project is licensed under AGPL-3.0-or-later.
