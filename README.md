# mu-plugins

Shared [must-use plugins](https://developer.wordpress.org/advanced-administration/plugins/mu-plugins/) for WordPress sites.

## Requirements

- WordPress 6.0 or later (uses `str_starts_with()`)
- PHP 8.0 or later

## Installation

Copy the plugin file(s) you need into `wp-content/mu-plugins/` on the target site. Must-use plugins load automatically; there is no activation step.

## Plugins

### Remote uploads (`local-remote-uploads.php`)

Serves media from a remote uploads directory when the file exists there, and falls back to the local copy when the remote returns 404.

Add to `wp-config.php`:

```php
define( 'REMOTE_UPLOADS_BASE', 'https://example.com/wp-content/uploads' );
```

When `REMOTE_UPLOADS_BASE` is not defined, the mu-plugin is inert and upload URLs are unchanged. Administrators see a dashboard notice until the constant is set.

## Licence

GPL-2.0-or-later. See [license.txt](license.txt).
