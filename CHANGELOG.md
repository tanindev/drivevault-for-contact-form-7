# Changelog

All notable changes to **DriveVault for Contact Form 7** will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2026-11-10

### Added
- **⚡ Real-Time Google Sheets Sync**: Instant submission appending directly into Google Spreadsheets with auto-formatting.
- **📁 Google Drive File Upload Vault**: Automatic streaming of Contact Form 7 file attachments into dedicated Google Drive folders.
- **🗺️ Visual Field-to-Column Mapper**: Intuitive mapping interface supporting CF7 tags, mail tags, and WordPress submission metadata.
- **📊 Auto Sheet & Header Creator**: One-click generation of new Google Spreadsheets and automated header column configuration.
- **📑 Multi-Form Routing**: Route multiple Contact Form 7 forms to distinct spreadsheets or custom tabs within a single workbook.
- **💾 Hosting Storage Saver**: Automatic local temporary upload cleanup after successful Google Drive transfer to protect hosting disk space.
- **🔒 Secure OAuth 2.0 Management**: AES-256-CBC token encryption utilizing WordPress security salts (`AUTH_KEY`, `SECURE_AUTH_KEY`).
- **📜 Comprehensive Audit Logs**: Detailed submission logging with latency tracking, payload inspection modal, and 1-click retry.
- **🚀 Zero Bloat Architecture**: Lightweight native WordPress HTTP client implementation (`wp_remote_post`, `wp_remote_get`) without heavy 3rd-party vendor SDK dependencies.
- **⚛️ Modern React Admin SPA**: Clean UI built on `@wordpress/components` and `@wordpress/data` with responsive layout and system health diagnostics.
