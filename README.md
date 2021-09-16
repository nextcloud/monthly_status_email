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

## Licensing

This project is licensed under AGPL-3.0-or-later.
