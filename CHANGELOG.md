# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.0.0] - 2026-05-23

Initial release of shared must-use plugins for WordPress sites.

### Added

- **Remote uploads mu-plugin** (`local-remote-uploads.php`): prefer a remote uploads base URL with local fallback when the remote file is missing; configured via `REMOTE_UPLOADS_BASE` in `wp-config.php`.
