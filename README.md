# DriveVault for Contact Form 7

> **Real-time Contact Form 7 submission sync to Google Sheets and secure file uploads to Google Drive.**

[![WordPress](https://img.shields.io/badge/WordPress-5.8%2B-blue.svg)](https://wordpress.org)
[![PHP](https://img.shields.io/badge/PHP-7.4%2B-purple.svg)](https://php.net)
[![License: GPL-2.0-or-later](https://img.shields.io/badge/License-GPL--2.0--or--later-green.svg)](https://www.gnu.org/licenses/gpl-2.0.html)

---

## 🌟 Overview

**DriveVault for Contact Form 7** seamlessly integrates Contact Form 7 forms with Google Workspace (Google Sheets and Google Drive). Submissions stream directly into Google Spreadsheets in real time, and file uploads are saved into Google Drive folders instead of exhausting your WordPress hosting disk space.

Built with a fast, modern React user interface powered by WordPress core packages (`@wordpress/components`, `@wordpress/data`, `@wordpress/api-fetch`), you can visually map form fields to spreadsheet columns, auto-generate headers, organize cloud folders, and audit submission activity.

---

## ✨ Features

- **⚡ Real-Time Google Sheets Sync**: Writes Contact Form 7 submissions to Google Sheets synchronously upon form submission.
- **📁 Google Drive File Upload Vault**: Ingests file attachments (`[file* your-file]`) straight into organized Google Drive folders and records direct Drive links in the spreadsheet.
- **💾 Hosting Storage Saver**: Optional toggle to automatically delete temporary local upload files from your web hosting server after successful cloud upload to Google Drive.
- **🗺️ Visual Field-to-Column Mapper**: Easily match form tags (`[your-name]`, `[your-email]`) and metadata (`_date`, `_time`, `_post_id`, `_post_title`, `_user_ip`) to spreadsheet columns.
- **📊 Auto-Create Spreadsheets & Headers**: Create new Google Sheets directly from the admin panel and auto-format header columns with one click.
- **📑 Multi-Form & Multi-Tab Routing**: Route different Contact Form 7 forms to distinct spreadsheets or individual tabs within the same sheet.
- **🔒 Enterprise-Grade Security**: Google OAuth 2.0 with AES-256-CBC token encryption using WordPress security salts.
- **📜 Submission Audit Logs & Resync**: Paginated log showing sync status, duration, mapped payloads, and a 1-click retry button for failed dispatches.
- **🚀 Zero Bloat**: Pure native `wp_remote_*()` HTTP client with exponential backoff and retry. Zero heavy 20MB third-party SDK dependencies.

---

## 📋 Requirements

- WordPress 5.8 or higher (fully tested up to WordPress 6.7)
- Contact Form 7 5.8+ or 6.0+
- PHP 7.4 or higher (fully compatible with PHP 8.0, 8.1, 8.2, 8.3, 8.4)
- Google Cloud Console Project (with Google Sheets API & Google Drive API enabled)

---

## 🛠️ Development & Build

```bash
# Install dependencies
npm install

# Build production assets
npm run build

# Build production release package (.zip)
npm run build:prod
```

---

## 📄 License

GPL-2.0-or-later. See the [LICENSE](LICENSE) file for details.
