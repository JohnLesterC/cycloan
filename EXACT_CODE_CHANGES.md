# EXACT CODE CHANGES MADE

## File 1: registration.php (Lines 1-15)

### OLD CODE (BROKEN)

```php
<?php
session_start();

// SECURITY: Regenerate session ID on every page load to prevent session fixation
if (empty($_SESSION['_session_created'])) {
    session_regenerate_id(true);
    $_SESSION['_session_created'] = time();
}

// SECURITY: Generate CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
```

### NEW CODE (FIXED) ✅

```php
<?php
session_start();

// SECURITY: Regenerate session ID ONLY on first visit (on login/registration start)
// Don't regenerate on every page load to prevent CSRF token invalidation
if (empty($_SESSION['_session_initialized'])) {
    // Only regenerate if this is a brand new session
    if (empty($_SESSION['_session_created'])) {
        session_regenerate_id(true);
        $_SESSION['_session_created'] = time();
    }
    $_SESSION['_session_initialized'] = true;
}

// SECURITY: Generate CSRF token if not exists - only once per session
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
```

**What Changed:**

- Added outer `if (empty($_SESSION['_session_initialized']))` check
- Moved regeneration inside this check
- Added `$_SESSION['_session_initialized'] = true` to set flag
- Updated comments to explain the fix

---

## File 2: process_registration.php (Lines 8-22)

### OLD CODE (BROKEN)

```php
session_start();

// SECURITY: Regenerate session ID to prevent fixation attacks
if (empty($_SESSION['_session_created'])) {
    session_regenerate_id(true);
    $_SESSION['_session_created'] = time();
}

// SECURITY: Validate CSRF token
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (empty($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        http_response_code(403);
        die(json_encode([
            'success' => false,
            'message' => 'Security error: Invalid session token. Please try again.'
        ]));
    }
}
```

### NEW CODE (FIXED) ✅

```php
session_start();

// SECURITY: Regenerate session ID ONLY on first visit - not on every handler call
// Multiple regenerations cause CSRF tokens to become invalid
if (empty($_SESSION['_session_initialized'])) {
    if (empty($_SESSION['_session_created'])) {
        session_regenerate_id(true);
        $_SESSION['_session_created'] = time();
    }
    $_SESSION['_session_initialized'] = true;
}

// SECURITY: Validate CSRF token
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (empty($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        http_response_code(403);
        die(json_encode([
            'success' => false,
            'message' => 'Security error: Invalid session token. Please try again.'
        ]));
    }
}
```

**What Changed:**

- Added outer `if (empty($_SESSION['_session_initialized']))` check
- Added nested condition for regeneration
- Added `$_SESSION['_session_initialized'] = true` to set flag
- Updated comments to explain multiple regenerations cause the problem

---

## Lines of Code Changed

| File                     | Lines | Type   | Change                         |
| ------------------------ | ----- | ------ | ------------------------------ |
| registration.php         | 1-18  | Add    | Session regeneration logic fix |
| process_registration.php | 8-22  | Update | Session regeneration logic fix |

**Total Lines Modified:** ~30 lines
**Total Files Modified:** 2 files
**Complexity:** Simple (just added one flag check)

---

## The Logic Pattern Used

Both files now use this same pattern:

```php
session_start();

// Only regenerate session on FIRST visit
if (empty($_SESSION['_session_initialized'])) {
    if (empty($_SESSION['_session_created'])) {
        session_regenerate_id(true);
        $_SESSION['_session_created'] = time();
    }
    $_SESSION['_session_initialized'] = true;  // Set flag to prevent future regenerations
}
```

**How it works:**

1. First visit: `_session_initialized` is empty → Enter outer if
2. First visit: `_session_created` is empty → Enter inner if → Regenerate session
3. First visit: Set `_session_initialized = true` → Exit
4. Second+ visits: `_session_initialized` is true → Skip outer if → No regeneration!

---

## Backward Compatibility

✅ These changes are fully backward compatible
✅ No database changes required
✅ No configuration changes required
✅ No new dependencies added
✅ Works with existing code

---

## Testing Checklist After Upload

After uploading these fixed files:

- [ ] Load registration.php in browser
- [ ] Verify form displays (no PHP errors)
- [ ] Open browser DevTools (F12)
- [ ] Go to Network tab
- [ ] Fill form and click Submit/Next
- [ ] Check POST request shows 200 OK (not 403)
- [ ] Verify csrf_token is in request body
- [ ] Complete full registration to final step
- [ ] Check all steps work without 403 errors

---

## If You Need to Apply This Manually

If you want to make these changes to OTHER files, use this pattern:

```php
session_start();

// Only regenerate on first visit
if (empty($_SESSION['_session_initialized'])) {
    if (empty($_SESSION['_session_created'])) {
        session_regenerate_id(true);
        $_SESSION['_session_created'] = time();
    }
    $_SESSION['_session_initialized'] = true;
}

// Your code here...
```

This ensures session IDs don't change on subsequent page visits.
