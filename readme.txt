=== DriveVault for Contact Form 7 – Google Sheets & Drive Sync ===
Contributors: tanindev
Tags: contact form 7, google sheets, google drive, cf7, spreadsheet
Requires at least: 6.2
Tested up to: 7.1
Requires PHP: 8.3
Requires Plugins: contact-form-7
Stable tag: 1.0.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Connect Contact Form 7 to Google Sheets & Google Drive. Sync submissions to spreadsheets in real time and store file uploads in Google Drive.

== Description ==

**DriveVault for Contact Form 7** connects your Contact Form 7 forms with Google Workspace (Google Sheets and Google Drive). Submissions stream directly into your Google Spreadsheets in real time, and file uploads are saved directly into your Google Drive folders instead of exhausting your WordPress hosting disk space.

Built with a fast, modern React user interface powered by WordPress core packages (`@wordpress/components`, `@wordpress/data`, `@wordpress/api-fetch`), you can visually map form fields to spreadsheet columns, auto-generate headers, organize cloud folders, and audit submission activity.

### Key Features

* **Real-Time Google Sheets Sync**: Writes Contact Form 7 submissions to Google Sheets synchronously upon submission.
* **Google Drive File Upload Vault**: Ingests file attachments (`[file* your-file]`) straight into organized Google Drive folders and records direct Drive links in both the spreadsheet and email notifications.
* **Hosting Storage Saver**: Option to automatically delete temporary local files from your server after successful upload to Google Drive.
* **Visual Column & Meta Field Mapper**: Easily match form tags (`[your-name]`, `[your-email]`) and metadata (Submission Timestamp, User IP, Page URL, Submission ID) to spreadsheet columns.
* **Auto-Create & Format Sheet Headers**: Automatically formats row 1 headers with a single click.
* **Multi-Form & Multi-Tab Routing**: Map different Contact Form 7 forms to distinct spreadsheets or individual tabs within the same sheet.
* **Secure OAuth 2.0 with AES-256 Encryption**: Connects directly via Google OAuth 2.0 without requiring manual `wp-config.php` edits. Access and refresh tokens are encrypted using AES-256-CBC with WordPress security salts.
* **Submission Audit Logs & Resync**: Paginated log showing sync status, duration, mapped payloads, and a 1-click retry button for failed dispatches.
* **Zero SDK Bloat**: Pure native `wp_remote_*()` HTTP client with backoff and retry. No heavy 20MB third-party SDK dependencies.

== External Services ==

This plugin connects to external 3rd-party services provided by Google to enable Google Sheets and Google Drive integration for Contact Form 7 submissions.

= Google OAuth 2.0 API =
* **What the service is and what it is used for**: Authenticates the site administrator's Google account via OAuth 2.0 to obtain authorized API access and refresh tokens.
* **What data is sent and when**:
    * When connecting your Google account in plugin settings, your OAuth Client ID, Client Secret, authorization code, and redirect URI are transmitted to Google's token endpoint (`https://oauth2.googleapis.com/token`) to exchange for API access and refresh tokens.
    * When viewing connection status, basic user profile information (email address, display name, and avatar picture) is retrieved from `https://www.googleapis.com/oauth2/v2/userinfo` to display the connected account in the admin dashboard.
    * When disconnecting your account, a revocation request is sent to `https://oauth2.googleapis.com/revoke` to invalidate tokens.
* **Service Provider**: Google LLC
* **Terms of Service**: [Google Terms of Service](https://policies.google.com/terms) (https://policies.google.com/terms)
* **Privacy Policy**: [Google Privacy Policy](https://policies.google.com/privacy) (https://policies.google.com/privacy)

= Google Sheets API v4 =
* **What the service is and what it is used for**: Creates spreadsheets, reads headers, and appends rows containing form submission data into designated Google Sheets.
* **What data is sent and when**:
    * When browsing or creating spreadsheets in the admin feed mapper, requests are sent to `https://sheets.googleapis.com/v4/spreadsheets`.
    * When visitors submit a mapped Contact Form 7 form on the front-end, the mapped form field values and configured metadata are sent to the Google Sheets append endpoint to insert a new row.
* **Service Provider**: Google LLC
* **Terms of Service**: [Google APIs Terms of Service](https://developers.google.com/terms) (https://developers.google.com/terms)
* **Privacy Policy**: [Google Privacy Policy](https://policies.google.com/privacy) (https://policies.google.com/privacy)

= Google Drive API v3 =
* **What the service is and what it is used for**: Lists folders, creates target upload folders, and streams form file attachments into your Google Drive storage.
* **What data is sent and when**:
    * When managing folders or selecting upload destinations in the admin interface, requests are sent to `https://www.googleapis.com/drive/v3/files`.
    * When visitors upload files through a mapped Contact Form 7 form, the uploaded binary file data and file name are transmitted via multipart request to `https://www.googleapis.com/upload/drive/v3/files?uploadType=multipart`.
* **Service Provider**: Google LLC
* **Terms of Service**: [Google APIs Terms of Service](https://developers.google.com/terms) (https://developers.google.com/terms)
* **Privacy Policy**: [Google Privacy Policy](https://policies.google.com/privacy) (https://policies.google.com/privacy)

== Third-Party Assets ==

* **Google Material Symbols / Icons Font**
    * File: `assets/fonts/drivevault-icon.woff2`
    * Source: https://fonts.google.com/icons
    * Copyright: Google LLC
    * License: Apache License 2.0 (https://www.apache.org/licenses/LICENSE-2.0)

== Source Code & Development ==

DriveVault for Contact Form 7 is free, open-source software built with React and `@wordpress/scripts`. The unminified source code, build configuration, SCSS stylesheets, and development history are openly maintained on GitHub:
* **Source Code Repository**: https://github.com/tanindev/drivevault-for-contact-form-7.git
* **Clone URL**: `git clone https://github.com/tanindev/drivevault-for-contact-form-7.git`

== Installation ==

1. Upload the `drivevault-for-contact-form-7` folder to your `/wp-content/plugins/` directory, or install directly through the WordPress Plugins dashboard.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Ensure Contact Form 7 is installed and active.
4. Navigate to **Contact** &gt; **DriveVault** in your WordPress admin menu.
5. In the **Google Account** tab, enter your Google Cloud OAuth Client ID and Client Secret, then click **Sign In with Google**.
6. Switch to the **Form Feeds** tab and click **Configure Feed** to map your form fields to a Google Sheet and Drive folder.

== Frequently Asked Questions ==

= Do I need a Google Cloud account? =
Yes. You need a free Google Cloud Console project with the Google Sheets API and Google Drive API enabled. Step-by-step instructions with direct links are built directly into the plugin's Google Account tab.

= Are file uploads stored on my WordPress hosting server? =
By default, Contact Form 7 temporarily stores files in `wp-content/uploads/wpcf7_uploads/`. DriveVault uploads them to your Google Drive and gives you the option to automatically delete the local file after upload, preventing your WordPress web server from running out of disk space.

= How are OAuth tokens secured? =
Tokens are encrypted using AES-256-CBC encryption combined with your site's unique `AUTH_KEY` and `SECURE_AUTH_KEY` salts defined in `wp-config.php`. Plain-text tokens are never stored in your database.

= Can I map different forms to different spreadsheets? =
Yes. Each Contact Form 7 form can be connected to its own separate Google Spreadsheet or to a specific worksheet tab inside an existing spreadsheet.

= Can I resync failed submissions? =
Yes. The **Submission Logs** tab displays the status of all submissions and includes a 1-click **Retry** button to re-dispatch any failed submission.

== Screenshots ==

1. Dashboard with sync statistics and recent submission activity.
2. Visual Field-to-Column Mapper with Google Sheet and Drive folder selector.
3. Google Account OAuth 2.0 configuration with 1-click copy redirect URI.
4. Submission & Sync Audit Logs with payload inspector and retry functionality.
5. System diagnostics and sync trigger settings.

== Changelog ==

= 1.0.0 =
* Initial release of DriveVault for Contact Form 7.
* Real-time Google Sheets row appending on form submissions.
* Google Drive multipart file upload routing for Contact Form 7 attachments.
* Visual field-to-column mapper with auto-header creation.
* OAuth 2.0 connection with AES-256 token encryption.
* Audit logs with latency metrics, payload inspector modal, and retry button.
* DriveVault React SPA interface with capsule tabs and modern design.
