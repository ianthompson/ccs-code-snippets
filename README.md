# CCS Code Snippets

A lightweight, professional WordPress plugin that allows you to add PHP, CSS, and HTML snippets to your website. It features a native code editor, AJAX toggles, GitHub auto-updates, and crash protection.

## 🚀 Features

*   **Native Code Editor:** Uses WordPress's built-in CodeMirror for syntax highlighting and linting.
*   **Zero-Setup Execution:** Just write `add_action(...)` — opening PHP tags are handled automatically.
*   **Early Execution Engine:** Snippets load at `plugins_loaded` (Priority 1), ensuring your custom hooks (Priority 10) fire correctly without race conditions.
*   **GitHub Auto-Updater:** The plugin checks your GitHub repository for releases and updates automatically via the WordPress Dashboard.
*   **Safe Mode:** A "Panic Button" to disable snippets if you accidentally crash your site.
*   **Shortcode Support:** Place snippets anywhere using `[ccs_snippet id=123]`.
*   **Management:**
    *   AJAX On/Off Toggles in the list view.
    *   Tags for organization.
    *   Duplicate Snippets with one click.
    *   Import/Export snippets via JSON.

## ⚙️ Installation & Configuration

1.  Download the latest release from the [GitHub Releases page](../../releases).
2.  Upload the zip file to your WordPress Plugins area.
3.  **Important:** Ensure the plugin folder is named `ccs-code-snippets` on your server for the updater to work correctly.

### Configuring Auto-Updates
To enable the plugin to update itself from *your* GitHub repository, open `ccs-code-snippets.php` and edit the configuration block at the top:

```php
// CONFIGURATION
define( 'CCS_GITHUB_USER', 'ianthompson' );  // Your GitHub Username
define( 'CCS_GITHUB_REPO', 'ccs-code-snippets' ); // Your Repo Name
define( 'CCS_ACCESS_TOKEN', '' ); // Leave empty for Public repos. Fill for Private.
```

## 📖 Usage

### Adding a Snippet
1.  Go to **Code Snippets > Add New**.
2.  **Type:** Select HTML, CSS, or PHP.
3.  **Code:** Enter your code.
    *   *Note:* For PHP, you can skip the `<?php` tag; the plugin adds it intelligently.
4.  **Hook:**
    *   **Run Everywhere:** Equivalent to `init` / `functions.php`. Best for `add_action` or `add_filter`.
    *   **Standard Hooks:** Header (`wp_head`), Footer (`wp_footer`), etc.
    *   **Custom:** Type any theme hook (e.g., `astra_header_before`).
5.  **Priority:** Standard WordPress priority (Default: 10).

### Shortcodes
You can output the result of a snippet inside a Post, Page, or Widget using:
```
[ccs_snippet id=123]
```
*(The ID is visible in the Shortcode column in the snippet list).*

### 🆘 Safe Mode (Crash Recovery)
If a PHP snippet contains an error (e.g., an infinite loop) and you cannot access your dashboard:

1.  Add `?ccs_safe_mode=1` to your URL.
    *   Example: `https://your-website.com/wp-admin/?ccs_safe_mode=1`
2.  A red banner will appear at the bottom of the screen.
3.  All snippets are now paused. You can log in, edit the broken snippet, and then remove the URL parameter to resume normal operation.

## 📋 Changelog

**v0.0.16**
*   **Core Update:** Moved execution engine to `plugins_loaded` priority 1. This fixes race conditions where snippets with standard priority (10) failed to fire.
*   **Improvement:** Switched to direct DB queries for snippet retrieval to support early loading.

**v0.0.15**
*   **UX:** Added smart PHP tag handling. `<?php` is now auto-prepended if missing.

**v0.0.14**
*   **UI:** Renamed "Init" to "Run Everywhere (functions.php style)" for clarity.

**v0.0.13**
*   **Fix:** Finalized robust GitHub Updater regex to handle `v` prefixes and nested zip folders.

**v0.0.11**
*   **Feature:** Added "Duplicate" link to snippet list rows.

**v0.0.10**
*   **Feature:** Added Shortcode support `[ccs_snippet]`.

**v0.0.9**
*   **Feature:** Implemented GitHub Auto-Updater class.

**v0.0.8**
*   **Feature:** Added Import/Export tools (JSON).
*   **Feature:** Added Safe Mode (`?ccs_safe_mode=1`).

**v0.0.7**
*   **UX:** Added AJAX Toggle Switches for Active status.
*   **UX:** Added Description/Notes field.

**v0.0.1 - v0.0.6**
*   Initial development, CPT registration, CodeMirror integration, and Admin Columns.
