<p float="left">
  <img src="https://storage.googleapis.com/seamk-production/2025/03/8082dded-seamk_lila-logo_nimella_fi_eng-300x184.png" alt="SEAMK logo" height="100">
  <img src="https://github.com/SeAMKedu/rovesugv_gps_nav/raw/main/images/Euroopan_unionin_osarahoittama_POS.png" alt="EU flag" height="150"> 
</p>

# JetFormBuilder Verification Redirect Cookie Fix

A WordPress snippet for fixing JetFormBuilder email verification redirect issues caused by email security scanners and multi-step verification flows.

## Purpose

This snippet fixes a specific issue with JetFormBuilder email verification links when using:

* frontend publishing flows
* custom post types (CPT)
* reply/contact forms
* custom success/failure redirects

The snippet was originally created for a workflow where:

1. A user submits a form.
2. JetFormBuilder sends a verification email.
3. The user clicks the verification link.
4. JetFormBuilder verifies the token.
5. The site redirects the user to a success page.

The problem was that some email providers and antivirus/security systems automatically opened the verification links before the user clicked them.

This caused the verification token to appear as already used during the final redirect phase, even though:

* the verification itself succeeded correctly
* the CPT post was created/published correctly
* but the user was redirected to a failure page instead of the success page

The issue was especially visible in multi-step redirect flows.

This snippet solves the problem by temporarily storing the JetFormBuilder token ID in secure short-lived cookies and resolving the final redirect state separately from the original verification request.



## What This Snippet Does

The snippet creates two independent verification flows:

### 1. Publish Flow

Used when a verified form creates or publishes a custom post type item.

Example:

* public inquiry submission
* listing publication
* directory submission
* marketplace posting

The snippet:

* stores the verification token ID in a cookie
* waits for JetFormBuilder to process verification
* checks whether the CPT item was successfully published
* redirects to either:

  * success page
  * failure page

### 2. Reply / Contact Flow

Used when verification is required before sending a contact or reply form.

The snippet:

* stores the token ID temporarily
* checks JetFormBuilder token state
* allows a one-time successful redirect
* prevents duplicate success handling



## Why Cookies Are Used

JetFormBuilder verification flows can involve:

* multiple redirects
* delayed CPT publishing
* temporary token state changes
* interference from email security scanners

Some email systems automatically pre-open links to scan them for malware.

This may:

* consume the verification token
* trigger JetFormBuilder verification before the real user visits the link
* break the final redirect logic

The cookie-based approach separates:

* token verification
* final user-facing redirect handling

This makes the flow more reliable in real-world email environments.



## Requirements

* WordPress
* JetFormBuilder
* Email verification enabled
* PHP snippets allowed (Code Snippets plugin, custom plugin, or theme functions.php)


## Custom Post Type Dependency

The publish flow depends on a Custom Post Type (CPT).

The snippet checks whether a verified form submission successfully created or published a CPT item.

Example:

```php
'post_type' => 'your_post_type'
```

After verification, the snippet searches for a published CPT item containing the stored JetFormBuilder verification token ID meta value.

If found:

* the verification is considered successful
* the user is redirected to the success page

If not found:

* the user is redirected to the failure page

Because of this, the publish flow is intended for workflows where:

* a form creates a post
* a listing
* an inquiry
* or another CPT-based content item

The reply/contact flow does not require a CPT.

---

## Configuration

Edit the CONFIG section inside the snippet.

Important settings:

```php
'post_type'
```

Your custom post type.

```php
'token_id_meta_key'
```

Meta key used for storing the JetFormBuilder token ID.

```php
'publish_page_slug'
```

Page handling verified publishing flow.

```php
'reply_page_slug'
```

Page handling verified reply/contact flow.

```php
'publish_success_path'
```

Redirect target after successful publishing verification.

```php
'reply_success_path'
```

Redirect target after successful reply verification.

```php
'failed_path'
```

Redirect target for failed verification.


## Installation

### Option 1: Code Snippets plugin

1. Install Code Snippets
2. Create new snippet
3. Paste the PHP file
4. Activate snippet
5. Adjust config values

### Option 2: Custom plugin

Place the PHP file inside a custom plugin.

### Option 3: Theme functions.php

Paste into your active theme's functions.php file.


## Important Notes

This snippet:

* does not modify JetFormBuilder core files
* depends on JetFormBuilder token tables
* assumes verification URLs contain:

  * `jfb_token_id`
  * `jfb_token`

The reply flow contains heuristics for determining whether a token has already been used because JetFormBuilder internal token states may vary between versions.

Test carefully after:

* JetFormBuilder updates
* redirect flow changes
* CPT changes
* verification setting changes


## Recommended Testing

Test at least:

### Publish Flow

* successful verification
* duplicate click
* expired link
* antivirus-scanned email links

### Reply Flow

* successful reply verification
* duplicate click
* invalid token
* failed redirect recovery


## License

MIT License
