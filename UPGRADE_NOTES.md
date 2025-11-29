# Upgrade Notes: Version 0.1.3

## Overview
Version 0.1.3 adds significant UX improvements to the code editor and settings management. This release includes automatic CSS snippet IDs, enhanced CodeMirror editor features, and a user-friendly GitHub settings interface.

---

## New Features & Improvements (v0.1.3)

### 1. Automatic CSS Snippet IDs
**Problem:** Users needed to manually add `<style id="custom-id">` tags to identify their CSS in the browser inspector.

**Solution:** CSS snippets now automatically include an ID attribute in the format `id="ccs-snippet-{id}"`.

**Benefits:**
- No need to wrap CSS in `<style>` tags
- Easy identification in browser DevTools
- Cleaner snippet code
- Consistent naming convention

**Example:**
```css
/* Your CSS snippet code (type: CSS) */
ol { margin-left: 0em; }
```
Outputs as:
```html
<style id="ccs-snippet-123">
ol { margin-left: 0em; }
</style>
```

### 2. Enhanced Code Editor

**New Features:**
- **Linting**: Real-time syntax checking with visual warnings/errors in the left gutter
- **Autocomplete**: Press `Ctrl+Space` (or `Cmd+Space` on Mac) for code suggestions
- **Syntax Warnings**: Orange/red markers indicate errors as you type
- **Line Wrapping**: Long lines wrap automatically for better readability
- **Bracket Matching**: Highlights matching brackets/parentheses
- **Auto-Close**: Automatically closes brackets, tags, and quotes
- **Active Line Highlighting**: Current line is highlighted
- **Resizable Editor**: Drag the bottom edge to resize (minimum 300px height)

**Keyboard Shortcuts:**
- `Ctrl+Space` / `Cmd+Space` - Trigger autocomplete
- `Ctrl+/` / `Cmd+/` - Toggle line/block comments

**Visual Improvements:**
- Help text displayed below editor with tips
- Better syntax highlighting
- Cleaner border around editor

### 3. GitHub Settings UI

**Problem:** Users had to edit the main PHP file to configure GitHub auto-update settings.

**Solution:** New settings panel in Tools page allows configuration via web interface.

**Location:** Code Snippets → Tools → GitHub Auto-Update Settings

**Features:**
- **GitHub Username**: Configure repository owner
- **Repository Name**: Set repository to check for updates
- **Access Token**: Optional field for private repositories (password-protected)
- **Current Configuration Display**: Shows active settings and token status
- **Automatic Cache Clearing**: Clears update cache when settings change
- **Database Storage**: Settings persist in database, not code files
- **Secure Token Storage**: Access tokens stored securely

**Benefits:**
- No more editing PHP files
- Settings survive plugin updates
- Easier for non-technical users
- Token is password-protected in UI
- Immediate feedback on configuration

### 4. New Files Added

```
includes/admin/class-settings.php - Settings manager class
```

**Updated Files:**
- `includes/core/class-snippet-executor.php` - Added auto-ID for CSS
- `includes/admin/class-admin-ui.php` - Enhanced CodeMirror configuration
- `includes/admin/class-tools-page.php` - Added GitHub settings UI
- `includes/core/class-github-updater.php` - Reads from database settings
- `ccs-code-snippets.php` - Loads new settings class

---

## Critical Bugfix (v0.1.2)

### Issues Fixed

**Problem:** Snippets were not executing on the frontend in v0.1.1

**Root Causes:**
1. **CSS content was being stripped** - `wp_strip_all_tags()` removed all CSS code, outputting empty `<style></style>` tags
2. **PHP snippets required admin privileges** - Added `current_user_can('manage_options')` check that prevented execution for non-admin visitors
3. **PHP output was being filtered** - `wp_kses_post()` stripped JavaScript and other code from snippet output

**The Fix:**
Updated `includes/core/class-snippet-executor.php` to output code directly without filtering:
- CSS: `echo '<style>' . $code . '</style>';` (no stripping)
- HTML: `echo $code;` (no filtering)
- PHP: `eval( '?>' . $code );` (no output filtering, no admin check during execution)

**Security Note:**
Security is maintained by enforcing `manage_options` permission when **creating/editing** snippets in the admin UI, not during execution. Once an admin activates a snippet, it executes for all site visitors (as intended).

**Impact:**
- All snippet types now work correctly on the frontend
- CSS snippets render properly
- JavaScript in PHP/HTML snippets executes correctly
- Analytics scripts and other third-party code works as expected

---

## Major Refactor (v0.1.1)

### File Structure
The plugin has been completely reorganized from a single 450+ line file into a modular architecture:

```
ccs-code-snippets/
├── ccs-code-snippets.php          (Main plugin file - now ~183 lines)
├── includes/
│   ├── core/
│   │   ├── class-snippet-executor.php    (Handles snippet execution)
│   │   ├── class-post-types.php          (Post type registration)
│   │   └── class-github-updater.php      (GitHub auto-updates)
│   └── admin/
│       ├── class-admin-ui.php            (Admin interface)
│       └── class-tools-page.php          (Import/Export tools)
```

### Security Improvements (HIGH PRIORITY)

1. **Input Sanitization**
   - All user inputs are now properly sanitized using WordPress functions
   - `sanitize_text_field()`, `sanitize_textarea_field()`, `wp_unslash()`
   - File upload validation with `wp_check_filetype_and_ext()`

2. **Output Escaping**
   - All output properly escaped with `esc_html()`, `esc_attr()`, `esc_url()`
   - HTML snippets use `wp_kses_post()` to allow safe HTML only
   - CSS snippets use `wp_strip_all_tags()`

3. **Permission Checks**
   - Enhanced capability checks - `manage_options` required for all snippet operations
   - AJAX endpoints verify nonce and permissions
   - Duplication feature checks user capabilities

4. **Error Handling**
   - Errors are now logged via `error_log()` instead of displayed to users
   - Admin users see sanitized error messages in HTML comments
   - Better error context including snippet ID and title

### Code Quality Improvements (MEDIUM PRIORITY)

1. **PHPDoc Comments**
   - Every class, method, and property now has complete documentation
   - @since tags track when features were added
   - @param and @return tags describe all inputs/outputs

2. **Input Validation**
   - JSON import validates structure before processing
   - Type validation ensures only 'html', 'css', 'php' are allowed
   - File type verification for uploads
   - Comprehensive error messages

3. **Better Organization**
   - Separation of concerns - each class has a single responsibility
   - Component-based architecture
   - Admin code only loads in admin area
   - Cleaner, more maintainable codebase

4. **Performance**
   - Added caching for active snippets (1 hour cache)
   - Cache clearing when snippets are saved/toggled
   - Constants defined for cache durations

### New Features

1. **Component Architecture**
   - Access components via `ccs_code_snippets()->get_component('executor')`
   - Easier to extend and customize
   - Better for developers building on top of the plugin

2. **Improved Safe Mode**
   - Enhanced visual banner
   - Shows in both frontend and admin
   - Better user feedback

3. **Better Error Logging**
   - Snippet errors include ID, title, file, and line number
   - Makes debugging much easier
   - Doesn't expose sensitive info to non-admins

## Breaking Changes

**None!** These updates are fully backward compatible. All existing snippets will continue to work exactly as before.

**v0.1.1 Note:** If you installed v0.1.1, snippets may not have been executing on the frontend. Upgrade to v0.1.2 immediately to resolve this issue.

## Migration Guide

### For Users (v0.1.3)
1. Update the plugin as normal through WordPress admin or by replacing files
2. All your existing snippets will continue to work
3. **CSS Snippets**: If you have CSS snippets with `<style>` tags, consider:
   - Option A: Change type from "CSS" to "HTML" (keeps existing code as-is)
   - Option B: Remove `<style>` tags and keep type as "CSS" (gets auto ID)
4. **GitHub Settings** (Optional): Visit Tools page to configure GitHub settings in the UI
5. No database migration required - settings are created automatically

### For Developers
If you've customized the plugin, note these changes:

**v0.1.3 - New Classes:**
- `CCS_Settings` - Settings management class
- New method: `CCS_Settings::get_settings()` - Get all plugin settings
- New method: `CCS_Settings::get( $key, $default )` - Get specific setting

**Accessing Settings:**
```php
// Get GitHub settings
$settings = CCS_Settings::get_settings();
$github_user = CCS_Settings::get( 'github_user', 'default-user' );

// GitHub updater now reads from database first, falls back to constants
```

**v0.1.1 - Architecture Changes:**
```php
// Old
new CCS_Code_Snippets_016();

// New
ccs_code_snippets(); // Returns plugin instance
ccs_code_snippets()->get_component('executor'); // Access specific component
```

**Class Names Changed:**
- `CCS_Code_Snippets_016` → `CCS_Code_Snippets` (main class)
- New classes: `CCS_Snippet_Executor`, `CCS_Admin_UI`, `CCS_Tools_Page`, `CCS_Post_Types`, `CCS_Settings`

## Security Notes

### Important: eval() Still Used
While we've made significant security improvements, the plugin still uses `eval()` for PHP snippet execution. This is:
- **By design** - it's the core functionality
- **Protected** by requiring `manage_options` capability
- **Safer now** with better error handling and logging
- **Documented** with warnings in code comments

**Recommendations:**
- Only allow trusted administrators to create snippets
- Use Safe Mode (`?ccs_safe_mode=1`) if a snippet crashes the site
- Consider limiting PHP snippets to specific users
- Review all snippets before activating them

### XSS Protection
- HTML snippets now filtered through `wp_kses_post()`
- Allows standard WordPress post HTML
- Blocks dangerous scripts and attributes
- CSS snippets strip all HTML tags

## Testing Checklist

Before deploying to production, test:

- [ ] Existing snippets still execute correctly
- [ ] Creating new snippets (HTML, CSS, PHP)
- [ ] Toggle snippets on/off (both in list view and edit screen)
- [ ] Shortcode `[ccs_snippet id=X]` works
- [ ] Import/Export functionality
- [ ] Duplicate snippet feature
- [ ] Safe Mode (`?ccs_safe_mode=1`)
- [ ] Tags assignment and display
- [ ] Different hooks (wp_head, wp_footer, init, etc.)
- [ ] Priority settings

## Performance Impact

**Positive Changes:**
- Admin assets only load on snippet screens (not all admin pages)
- Active snippets are cached for 1 hour
- Better conditional loading of admin components

**Negligible Impact:**
- File structure doesn't affect runtime performance
- Component loading is minimal overhead
- Same execution model as before

## Version History Summary

- **v0.1.3** (Current) - UX improvements: auto CSS IDs, enhanced editor, GitHub settings UI
- **v0.1.2** - Critical bugfix for snippet execution on frontend
- **v0.1.1** - Major refactor with security improvements (had execution bug)
- **v0.0.16** - Previous stable release

**Recommended:** Update directly to v0.1.3 for the best experience

## Support

If you encounter any issues after upgrading:

1. Enable Safe Mode: `yoursite.com/wp-admin/?ccs_safe_mode=1`
2. Check error logs for detailed error messages
3. Report issues on GitHub: https://github.com/ianthompson/ccs-code-snippets/issues

### Known Issues in v0.1.1
- ❌ CSS snippets output empty style tags
- ❌ PHP/HTML snippets don't execute for non-admin users
- ❌ JavaScript in snippets gets stripped
- ✅ **All fixed in v0.1.2**

## Credits

This refactoring addressed:
- Security vulnerabilities (XSS, code injection risks)
- Code quality issues (documentation, organization)
- Architecture improvements (separation of concerns)
- Developer experience enhancements

All changes maintain backward compatibility while significantly improving code quality and security.
