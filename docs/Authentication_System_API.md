# Authentication System Documentation

This document describes the authentication system as it currently exists in the project. The application collects a Gmail address, an Iranian mobile number, and a username, creates a short-lived verification code, and issues a 24-hour authentication token after successful code verification.

The project is a **traditional server-rendered PHP application**, not a JSON REST API. It uses HTML forms, `POST` requests, PHP sessions, cookies, and `303 See Other` redirects. The term **route** or **processing endpoint** is therefore more accurate than API endpoint for the current implementation.

The application uses plain PHP and does not use Laravel, another PHP framework, Composer packages, or JWT.

## 1. Project Overview

The system implements the following flow:

1. The user submits `gmail`, `phone`, and `username`.
2. PHP validates all three values on the server.
3. PHP generates a secure 4-digit verification code.
4. The code and its two-minute expiration time are stored in MySQL.
5. The pending phone number is stored in the PHP session.
6. The browser is redirected to `verify.php`.
7. The verification page calculates its countdown from the real `code_expires_at` value in MySQL.
8. The user submits the verification code.
9. PHP validates the CSRF token, code format, stored code, and server-side expiration time.
10. A secure opaque authentication token is generated after successful verification.
11. Only the token hash is stored in MySQL; the raw token is stored in an HttpOnly cookie.
12. The authenticated user can access `dashboard.php`.
13. Valid activity may extend token validity to 24 hours from the current time.
14. Logout invalidates the database token, cookie, and PHP session.

The identity fields are not proof of authentication. Matching `gmail`, `phone`, and `username` does not create or renew an authentication token. A valid verification code or an existing valid authentication cookie is required.

> **Current limitation:** The project generates and stores verification codes, but it does not yet send them through SMS or email.

## 2. Technology Stack

| Technology | Current use |
|---|---|
| Plain PHP | Request handling, validation, sessions, cookies, and authentication logic |
| MySQL | Persistent user, verification, and token state |
| PDO | Database connection, prepared statements, and transactions |
| HTML5 | Server-rendered forms and pages |
| Tailwind CSS | Responsive styling, dark/light themes, and glassmorphism UI |
| Vanilla JavaScript | Client-side validation, theme controls, code inputs, and countdown display |
| PHP Sessions | Pending verification state, CSRF tokens, and flash messages |
| HttpOnly Cookies | Session identifier and raw authentication token |
| SHA-256 | Hashing the opaque authentication token before database storage |

All application and database date-time comparisons use UTC.

## 3. Project Structure

Only authentication-related files are shown below. There is no `authenticated.php` file in the current project; `dashboard.php` is the protected page.

```text
project-root/
+-- index.php
+-- process.php
+-- verify.php
+-- verify-process.php
+-- resend-code.php
+-- verification-helpers.php
+-- dashboard.php
+-- logout.php
+-- test-db.php
+-- auth/
|   +-- check-auth.php
+-- config/
|   +-- database.php
+-- database/
|   +-- schema.sql
+-- docs/
    +-- API_DOCUMENTATION.md
    +-- Authentication_System_API.md
```

### File responsibilities

| File | Responsibility |
|---|---|
| `index.php` | Displays the registration form and field/status messages |
| `process.php` | Validates identity fields and creates or replaces a verification challenge |
| `verify.php` | Displays the database-backed timer, code form, and resend form |
| `verify-process.php` | Verifies the code and issues a new authentication token |
| `resend-code.php` | Replaces an expired code when the resend rules allow it |
| `verification-helpers.php` | Session, verification-code, CSRF, flash, redirect, and UTC helpers |
| `auth/check-auth.php` | Authentication-token generation, cookies, validation, and sliding expiration |
| `dashboard.php` | Protected authenticated page |
| `logout.php` | CSRF-protected token invalidation and session destruction |
| `config/database.php` | Creates and returns the configured PDO connection |
| `database/schema.sql` | Defines `users` and includes commented upgrade migrations |
| `test-db.php` | Simple database connectivity diagnostic |

## 4. Current Architecture and Request Format

The current application uses:

- Browser-submitted HTML forms
- `application/x-www-form-urlencoded` request bodies
- `GET` for pages
- `POST` for operations that change state
- PHP session cookies to connect related requests
- CSRF hidden form fields for sensitive operations
- Server-rendered HTML responses
- `303 See Other` redirects after most processing requests

No current route accepts or returns JSON.

### Local base URL

```text
http://localhost:8000
```

Example:

```text
http://localhost:8000/process.php
```

The production URL will be different and should use HTTPS.

## 5. Authentication Components

| Component | Purpose |
|---|---|
| Verification code | A 4-digit challenge created with `random_int(1000, 9999)` and valid for two minutes |
| PHP session | Holds pending verification state, CSRF values, and one-time flash messages |
| Authentication token | A random 32-byte value encoded as 64 hexadecimal characters |
| Token hash | The SHA-256 hash stored in `auth_token_hash`; the raw token is not stored in MySQL |
| `sign_in_auth` cookie | Stores the raw authentication token with HttpOnly and SameSite protections |
| CSRF token | A random session-bound value that protects sensitive forms from cross-site submission |

These values are independent. A CSRF token is not an authentication token, a verification code, or a PHP session ID.

## 6. Route Summary

| Method | Route | Response type | Session | CSRF | Auth token |
|---|---|---|---:|---:|---:|
| `GET` | `/index.php` | HTML | No | No | No |
| `POST` | `/process.php` | HTML on validation error; otherwise redirect | Created after success | No | No |
| `GET` | `/verify.php` | HTML or redirect | Required | Generates tokens for forms | No |
| `POST` | `/verify-process.php` | Redirect | Required | Required | No |
| `POST` | `/resend-code.php` | Redirect | Required | Required | No |
| `GET` | `/dashboard.php` | HTML or redirect | Used | No for GET | Required |
| `POST` | `/logout.php` | Redirect | Required | Required | Required |
| `GET` normally | `/test-db.php` | Plain text | No | No | No |

`auth/check-auth.php`, `verification-helpers.php`, and `config/database.php` are internal include files, not public application routes.

## 7. Route Reference

### 7.1 Registration form

**File:** `index.php`  
**Method:** `GET`  
**URL:** `/index.php`  
**Authentication:** None  
**Session:** Not required  
**CSRF:** Not implemented for this form

Displays the Gmail, phone, and username form. It accepts one optional query parameter:

| Field | Accepted values | Behavior |
|---|---|---|
| `status` | `success`, `database-error` | Displays the matching safe Persian message; unknown values are ignored |

The normal response is an HTML page with status `200 OK`.

> `status=success` is defined in `index.php`, but no current processing path redirects to it. Successful registration currently redirects to `verify.php`.

### 7.2 Registration processing

**File:** `process.php`  
**Method:** `POST`  
**URL:** `/process.php`  
**Authentication:** None  
**Session:** Started after a successful database operation  
**CSRF:** Not currently implemented

#### Form fields

| Field | Type | Required | Server-side rules |
|---|---|---:|---|
| `gmail` | string | Yes | Valid according to `FILTER_VALIDATE_EMAIL` and ends with `@gmail.com` |
| `phone` | string | Yes | Exactly 11 ASCII digits matching `09[0-9]{9}` |
| `username` | string | Yes | Between 5 and 10 UTF-8 characters |

#### Database behavior

The operation runs in a transaction:

1. A prepared `SELECT ... FOR UPDATE` searches by `phone`.
2. A new phone creates one `users` row with `resend_count=0` and `is_verified=0`.
3. An existing phone updates `gmail`, `username`, `verification_code`, and `code_expires_at` and increments `resend_count`.
4. Updating an existing row does not change `created_at`.
5. Existing verification and authentication-token state is not changed merely because the identity values match.
6. If `resend_count` is already 5, no new challenge is written and a limit message is stored.

#### Outcomes

| Condition | Result |
|---|---|
| Valid request | Stores the phone in session and returns `303` to `/verify.php` |
| Invalid identity field | Returns the rendered registration page with status `422` |
| Database failure | Rolls back and returns `303` to `/index.php?status=database-error` |
| Method other than POST | Returns `303` to `/index.php` |

Security controls include server-side validation, prepared statements, a transaction, row locking, secure random code generation, and no code disclosure.

### 7.3 Verification page

**File:** `verify.php`  
**Method:** `GET`  
**URL:** `/verify.php`  
**Authentication:** Authentication token not required  
**Session:** Valid `verification_phone` required  
**CSRF:** Creates a token for the verification and resend forms

The page loads `code_expires_at` and `resend_count` with a prepared query. PHP calculates remaining seconds using the UTC server time. JavaScript receives only that remaining duration and displays the visual countdown.

Refreshing the page does not restart the countdown at `02:00`; the value is recalculated from MySQL.

#### UI behavior

- Before expiration, code submission is enabled and resend is disabled.
- At expiration, code inputs and the verification button are disabled in the UI.
- After expiration, resend is enabled unless the limit has been reached.
- Server-side processing rechecks all timing and resend rules. Changing HTML attributes cannot bypass them.

#### Outcomes

| Condition | Result |
|---|---|
| Valid pending session and user | HTML with `200 OK` |
| Missing/invalid pending session | `303` to `/index.php` |
| User no longer exists | Clears pending state and returns `303` to `/index.php` |
| Database read failure | HTML with `200` and a generic error message |

### 7.4 Verification processing

**File:** `verify-process.php`  
**Method:** `POST`  
**URL:** `/verify-process.php`  
**Authentication:** Not yet authenticated  
**Session:** Valid `verification_phone` required  
**CSRF:** Required and checked with `hash_equals()`

#### Form fields

| Field | Type | Required | Rules |
|---|---|---:|---|
| `verification_code` | string | Yes | Exactly 4 ASCII digits |
| `csrf_token` | string | Yes | Must match the session verification CSRF token |

#### Successful verification

The endpoint uses a transaction and `SELECT ... FOR UPDATE` to:

1. Load the user identified by the phone stored in the session.
2. Parse and check `code_expires_at` against the current UTC time.
3. Compare the submitted and stored code with `hash_equals()`.
4. Generate an opaque raw token with `bin2hex(random_bytes(32))`.
5. Hash the raw token with SHA-256.
6. Set `is_verified=1` and reset `resend_count=0`.
7. Store the hash, 24-hour expiration, and authentication timestamp.
8. Commit the transaction.
9. Regenerate the PHP session ID.
10. Clear the pending phone and verification CSRF state.
11. Place only the raw token in the `sign_in_auth` HttpOnly cookie.
12. Return `303` to `/dashboard.php`.

A new successful verification replaces `auth_token_hash`, immediately invalidating the previous authentication token.

Wrong, malformed, or expired codes do not authenticate the user or generate a token. Database exceptions are not exposed to the browser.

### 7.5 Verification code resend

**File:** `resend-code.php`  
**Method:** `POST`  
**URL:** `/resend-code.php`  
**Authentication:** Authentication token not required  
**Session:** Valid `verification_phone` required  
**CSRF:** Required

#### Form fields

| Field | Type | Required | Rules |
|---|---|---:|---|
| `csrf_token` | string | Yes | Must match the session verification CSRF token |

The endpoint allows resend only when:

- The pending user exists.
- The current code has expired according to UTC server time.
- `resend_count` is lower than `MAX_RESEND_ATTEMPTS` (`5`).

On success, a transaction replaces `verification_code`, sets `code_expires_at` to two minutes after the current time, increments `resend_count`, updates `updated_at`, and returns `303` to `/verify.php`.

The old code stops working because its database value is overwritten. Early resend and limit failures leave the challenge unchanged.

### 7.6 Protected dashboard

**File:** `dashboard.php`  
**Method:** `GET`  
**URL:** `/dashboard.php`  
**Authentication:** Valid `sign_in_auth` cookie required  
**Session:** Used for flash messages and logout CSRF  
**CSRF:** Not required for this read-only GET

`dashboard.php` calls `authenticatedUser()` from `auth/check-auth.php`. That helper:

1. Reads the raw token from the HttpOnly cookie.
2. Requires exactly 64 hexadecimal characters.
3. Hashes the token with SHA-256.
4. Searches by `auth_token_hash` with a prepared statement.
5. Requires `is_verified=1`.
6. Requires `token_expires_at` to be later than the current UTC time.
7. Clears invalid or expired cookies.
8. Clears expired token fields in MySQL when the matching expired record is found.

The page displays escaped `gmail`, `phone`, and `username` values.

#### Sliding expiration

When a valid authenticated request occurs and at least one hour has passed since `last_authenticated_at`, the helper:

- Keeps the same raw token.
- Extends `token_expires_at` to 24 hours from the current UTC time.
- Updates `last_authenticated_at`.
- Refreshes the cookie expiration.

Requests made before one hour do not cause an unnecessary database update. Expired or invalid tokens are never renewed.

#### Outcomes

| Condition | Result |
|---|---|
| Valid authentication token | HTML with `200 OK` |
| Missing, invalid, or expired token | `303` to `/index.php` |
| Database failure | `303` to `/index.php?status=database-error` |

### 7.7 Logout

**File:** `logout.php`  
**Method:** `POST`  
**URL:** `/logout.php`  
**Authentication:** Valid authentication token required  
**Session:** Required  
**CSRF:** Required

#### Form fields

| Field | Type | Required | Rules |
|---|---|---:|---|
| `csrf_token` | string | Yes | Must match the session authentication CSRF token |

On success, the route:

- Sets `auth_token_hash`, `token_expires_at`, and `last_authenticated_at` to `NULL`.
- Expires the `sign_in_auth` cookie.
- Clears session data.
- Expires the PHP session cookie.
- Destroys the PHP session.
- Returns `303` to `/index.php`.

Invalid CSRF does not invalidate the token and returns `303` to `/dashboard.php`. A non-POST request also returns `303` to `/dashboard.php`.

### 7.8 Database connectivity diagnostic

**File:** `test-db.php`  
**Typical method:** `GET`  
**URL:** `/test-db.php`  
**Authentication, session, and CSRF:** None

The file attempts to load the PDO connection. Success returns plain text with `200 OK`; failure returns a generic plain-text message with `500 Internal Server Error`. Raw PDO errors are not displayed.

This diagnostic should be removed from public access or restricted before production deployment.

## 8. Validation Rules

| Value | Current server-side validation |
|---|---|
| `gmail` | Required, valid email syntax, and `@gmail.com` suffix |
| `phone` | Required, exactly 11 ASCII digits, starts with `09` |
| `username` | Required, 5 to 10 UTF-8 characters |
| `verification_code` | Exactly 4 ASCII digits, equals the stored code, and is not expired |
| Resend request | Valid pending session and CSRF, expired current code, fewer than 5 resends |
| Authentication token | 64 hexadecimal characters, matching stored hash, verified user, future expiration |
| CSRF token | Submitted string must match the session value using `hash_equals()` |

Browser and JavaScript checks improve usability only. PHP is the final security authority.

## 9. Database Model

The project uses the `users` table defined in `database/schema.sql`.

| Column | MySQL type | Nullable/default | Purpose | Sensitivity |
|---|---|---|---|---|
| `id` | `BIGINT UNSIGNED` | NOT NULL, auto increment | Primary user identifier | Low |
| `gmail` | `VARCHAR(254)` | NOT NULL | User Gmail address | Personal data |
| `phone` | `CHAR(11)` | NOT NULL, UNIQUE | Unique user lookup identifier | Personal data |
| `username` | `VARCHAR(10)` | NOT NULL | User-facing name | User data |
| `verification_code` | `CHAR(4)` | NOT NULL | Current verification code | High, short-lived |
| `code_expires_at` | `DATETIME` | NOT NULL | UTC verification-code expiration | Security state |
| `resend_count` | `SMALLINT UNSIGNED` | NOT NULL, default `0` | Current resend counter | Security state |
| `is_verified` | `TINYINT UNSIGNED` | NOT NULL, default `0` | Verification state constrained to 0 or 1 | Security state |
| `auth_token_hash` | `CHAR(64)` ASCII | NULL, UNIQUE | SHA-256 authentication-token hash | High |
| `token_expires_at` | `DATETIME` | NULL | UTC authentication expiration | Security state |
| `last_authenticated_at` | `DATETIME` | NULL | Controls one-hour refresh threshold | Security state |
| `created_at` | `TIMESTAMP` | Current timestamp | Row creation time | Low |
| `updated_at` | `TIMESTAMP` | Auto-updated | Last row update time | Low |

Additional database properties:

- Storage engine: InnoDB
- Character set: `utf8mb4`
- Collation: `utf8mb4_unicode_ci`
- Unique index on `phone`
- Index on `code_expires_at`
- Unique binary-comparison index on `auth_token_hash`
- Check constraint restricting `is_verified` to 0 or 1
- Commented, non-destructive upgrade sections for existing tables

## 10. Session and Cookie Behavior

### Session keys

| Key | Stored value | Lifecycle |
|---|---|---|
| `verification_phone` | Pending Iranian mobile number | Registration success until verification success or invalid user cleanup |
| `verification_csrf_token` | Verification/resend CSRF token | Verification flow |
| `verification_flash` | One-time type and message | Read and removed by `verify.php` |
| `auth_csrf_token` | Logout CSRF token | Authenticated session |
| `auth_flash` | One-time authentication message | Read and removed by `dashboard.php` |

The raw authentication token and complete authenticated user record are not stored in the PHP session.

### PHP session cookie

- Name: `sign_in_system_session`
- Lifetime: browser session (`0`)
- Path: `/`
- HttpOnly: enabled
- SameSite: `Lax`
- Secure: enabled when the current request uses HTTPS
- Strict session mode: enabled

`session_regenerate_id(true)` runs after registration state is established and after successful code verification. Logout clears and destroys the session.

### Authentication cookie

- Name: `sign_in_auth`
- Contains: raw opaque token
- Initial lifetime: 24 hours
- Path: `/`
- HttpOnly: enabled
- SameSite: `Lax`
- Secure: enabled for HTTPS; disabled only for local HTTP development
- JavaScript access: blocked by HttpOnly

Only the SHA-256 hash of this token is stored in MySQL.

## 11. Timer and Resend Behavior

The timer is based on `users.code_expires_at`, not a hard-coded client-side restart:

1. PHP reads the stored expiration time.
2. PHP parses it as UTC.
3. PHP subtracts the current UTC server timestamp.
4. Only the remaining number of seconds is rendered into JavaScript.
5. JavaScript displays `MM:SS` and updates the interface.
6. PHP checks expiration again when verification or resend is submitted.

This means a refresh after approximately 70 seconds shows approximately 50 seconds remaining instead of restarting at two minutes.

The resend limit is `MAX_RESEND_ATTEMPTS = 5`. A successful verification resets `resend_count` to zero.

## 12. Current HTTP Status Codes

| Status | Current use |
|---|---|
| `200 OK` | Server-rendered pages and successful `test-db.php` output |
| `303 See Other` | Post/Redirect/Get behavior and access redirects |
| `422 Unprocessable Content` | Invalid registration fields in `process.php` |
| `500 Internal Server Error` | Failed connection in `test-db.php` only |

The current project does not return `401`, `403`, `405`, or `429`. Missing authentication, invalid CSRF, unsupported methods, and resend limits are currently handled through `303` redirects and server-rendered messages.

## 13. Status and Error Handling

The application uses Persian user-facing messages. The main states include:

- Required or invalid Gmail
- Required or invalid phone number
- Required or invalid username length
- Invalid request/CSRF state
- Verification code must contain exactly four digits
- Incorrect verification code
- Expired verification code
- Current code is still valid, so resend is blocked
- New two-minute code created
- Resend limit reached
- Account successfully verified
- Generic database or processing failure

Raw SQL errors, PDO messages, stack traces, database credentials, verification codes, CSRF values, session IDs, and raw authentication tokens are not included in user responses.

## 14. Security Review

### Implemented

- Server-side validation
- PDO prepared statements for user-influenced database operations
- Transactions and row locking for challenge updates
- Secure code generation with `random_int()`
- Secure authentication-token and CSRF generation with `random_bytes()`
- Verification-code expiration checked by PHP
- Resend permission and limit checked by PHP
- Token expiration checked by PHP
- Raw token stored only in an HttpOnly cookie
- SHA-256 token hash stored in MySQL
- SameSite cookie protection
- Session ID regeneration after important transitions
- CSRF protection for verification, resend, and logout
- Generic error messages instead of technical exception details
- UTC database connection and application comparisons
- Escaped dynamic output with `htmlspecialchars()`
- Token rotation after a new successful verification
- Controlled sliding expiration after one hour

### Needs improvement

- `process.php` does not currently have CSRF protection.
- There is no failed-code attempt counter or lockout for verification brute force.
- Resend limiting is row-based; there is no IP-, device-, or time-window-based rate limiter.
- `test-db.php` is publicly reachable unless restricted by the web server.
- Unsupported methods and security failures redirect instead of returning precise `405`, `403`, or `429` statuses.
- Production secrets should come only from environment variables or a secret manager; the current local configuration includes fallback values.
- External Tailwind, font, and image resources are loaded without a defined Content Security Policy.

### Not implemented

- SMS or email verification-code delivery
- Audit/security event logging
- Account recovery and secure phone-number changes
- Web-server-level HTTPS enforcement
- CORS policy for an API
- JSON REST endpoints

## 15. Request and Response Examples

The values below are examples only. They are not copied from the database and do not contain real secrets.

### Start registration

```http
POST /process.php HTTP/1.1
Host: localhost:8000
Content-Type: application/x-www-form-urlencoded

gmail=test.user@gmail.com&phone=09123456789&username=parham
```

Successful result:

```http
HTTP/1.1 303 See Other
Location: verify.php
```

Invalid identity data returns the registration HTML with status `422`.

### Verify code

```http
POST /verify-process.php HTTP/1.1
Host: localhost:8000
Content-Type: application/x-www-form-urlencoded
Cookie: sign_in_system_session=<session-cookie>

verification_code=<four-digit-code>&csrf_token=<session-csrf-value>
```

Successful result:

```http
HTTP/1.1 303 See Other
Location: dashboard.php
Set-Cookie: sign_in_auth=<opaque-value>; HttpOnly; SameSite=Lax; Path=/
```

### Resend expired code

```http
POST /resend-code.php HTTP/1.1
Host: localhost:8000
Content-Type: application/x-www-form-urlencoded
Cookie: sign_in_system_session=<session-cookie>

csrf_token=<session-csrf-value>
```

Success and handled failures both return `303` to `verify.php`, where a flash message describes the result.

### Logout

```http
POST /logout.php HTTP/1.1
Host: localhost:8000
Content-Type: application/x-www-form-urlencoded
Cookie: sign_in_system_session=<session-cookie>; sign_in_auth=<opaque-value>

csrf_token=<authentication-csrf-value>
```

Successful logout returns `303` to `index.php` after server-side and client-side authentication state is invalidated.

## 16. Testing Guide

### Browser testing

1. Start the local server with `php -S localhost:8000`.
2. Open `http://localhost:8000/index.php`.
3. Test valid and invalid registration values.
4. In browser developer tools, inspect Network redirects and status codes.
5. Confirm that refreshing `verify.php` does not reset the timer.
6. Confirm that early resend is rejected by the server.
7. In Application/Storage, verify that `sign_in_auth` has the HttpOnly attribute. Do not copy or publish its value.
8. Confirm that direct access to `dashboard.php` without a valid cookie redirects to `index.php`.
9. Confirm that logout clears both authentication and session state.

### MySQL Workbench testing

Inspect only the fields required for testing, such as `resend_count`, `is_verified`, and expiration timestamps. Do not publish or place verification codes or token hashes in screenshots and logs.

### Postman testing

- Use `x-www-form-urlencoded` request bodies.
- Enable cookie persistence.
- Fetch the HTML form before submitting a CSRF-protected route.
- Use the CSRF value from the same PHP session.
- Expect HTML and redirects, not JSON.

### Recommended scenarios

| # | Scenario | Expected result |
|---:|---|---|
| 1 | Valid new user | Row created and redirected to verification |
| 2 | Existing phone | Existing row updated, no duplicate row |
| 3 | Invalid Gmail | `422` and Gmail error |
| 4 | Invalid phone | `422` and phone error |
| 5 | Invalid username length | `422` and username error |
| 6 | Correct, unexpired code | Verified state, token cookie, and dashboard |
| 7 | Wrong code | No authentication token |
| 8 | Correct but expired code | Rejected by PHP |
| 9 | Resend before expiration | No code replacement |
| 10 | Resend after expiration | Old code replaced and timer restarted from MySQL |
| 11 | `resend_count` is already 5 | Additional resend rejected |
| 12 | Valid authentication token | Dashboard access allowed |
| 13 | Invalid or expired token | Cookie cleared and index redirect |
| 14 | New successful verification | Previous token invalidated |
| 15 | Valid logout | Token fields cleared and session destroyed |
| 16 | Invalid CSRF | Sensitive operation not performed |

## 17. Future REST API Proposal

> **Proposed and not currently implemented**

The following routes do not exist in the current project. They are possible future replacements for the form-based processing routes.

| Proposed route | Purpose | Example proposed response |
|---|---|---|
| `POST /api/register` | Validate identity data and create a verification challenge | `{ "status": "verification_required" }` |
| `POST /api/verify-code` | Verify a code and establish API authentication | `{ "status": "verified" }` |
| `POST /api/resend-code` | Replace an expired verification code | `{ "status": "code_replaced" }` |
| `GET /api/me` | Return the authenticated user's public profile | `{ "id": 1, "username": "example" }` |
| `POST /api/logout` | Invalidate API authentication | `{ "status": "logged_out" }` |

A future API design must separately define:

- A stable JSON request and error schema
- Authentication transport for browser and mobile clients
- Cookie or authorization-header policy
- CORS policy
- CSRF policy for cookie-based clients
- Rate limits and brute-force controls
- Accurate `400`, `401`, `403`, `405`, `422`, and `429` responses
- API versioning and production logging

These proposals must not be confused with the current server-rendered routes.

## 18. Known Documentation Mismatches and Limitations

The source review found these current-state details:

1. `index.php` supports `status=success`, but no current route redirects to that state.
2. The registration form posts to `process.php` without a CSRF token.
3. There is no `authenticated.php`; the protected route is `dashboard.php`.
4. There are no JSON routes despite the documentation file using `API` in its name.
5. Verification-code generation exists, but delivery through SMS or email does not.

No application code was changed to hide these differences. They are documented as the system behaves today.
