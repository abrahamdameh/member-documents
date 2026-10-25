# Member Documents

[![CI](https://github.com/xhebruski/member-documents/actions/workflows/ci.yml/badge.svg)](https://github.com/xhebruski/member-documents/actions/workflows/ci.yml)

A WordPress plugin for membership sites that issue documents to members (agreements, notices, policies) and need to prove later exactly what each member received and accepted.

- **Versioned templates.** Editing a template creates a new version. Earlier versions, and every document generated from them, stay untouched.
- **PDF generation** with Dompdf. It is pure PHP, so it runs on any host with no external service.
- **Tamper-evident archive.** Each PDF's SHA-256 is recorded when it is issued, and acceptance is refused if the stored file no longer matches.
- **Recorded acceptance:** time, IP and browser. Acceptance is written once and can't be overwritten.
- **A members' page** (`[member_documents]`) to view and accept documents, and **email delivery** with the PDF attached.
- **A WP-CLI integrity check** across the whole archive: `wp member-documents verify`.

## How it works

```
Admin saves template ──► new TemplateVersion (immutable)
                                │
Issue to member ──► Renderer (fills {{placeholders}}, escaped)
                                │
                        PdfRenderer (Dompdf) ──► FilesystemStore (private folder)
                                │
                        Document record: version_id + sha256 + issued_at
                                │
Member accepts ──► hash re-checked ──► accepted_at / IP / browser (write-once)
```

| Layer | Class | Responsibility |
|---|---|---|
| Composition root | `Plugin` | Builds the object graph and registers hooks. Dependencies go in through constructors, never globals. |
| Domain | `DocumentService` | Issue, accept and verify. No WordPress calls, so every rule is unit-tested. |
| Templates | `TemplateRepository`, `Renderer` | Versioned storage (a transaction plus a UNIQUE key keeps version numbers race-free); placeholder filling with escaping. |
| PDF | `PdfRendererInterface`, `DompdfRenderer` | Swappable engine (`mdocs_pdf_renderer` filter); remote resources disabled. |
| Storage | `FilesystemStore` | One folder per member, closed to direct web access, atomic writes, path-traversal guards. |
| Persistence | `DocumentRepository`, `Installer` | Custom tables via `$wpdb->prepare()`; a schema that upgrades itself, with no reactivation needed. |
| Delivery | `Shortcode`, `RequestHandler`, `DocumentMailer`, `AdminPage`, `Cli\Command` | Nonce, capability and ownership checks on every action. |

## Security notes

- Every form and download checks a nonce, the user's capability, and that the document is theirs. PDFs are never served from a public URL.
- Member values are HTML-escaped before rendering. Template bodies go through `wp_kses_post()` when saved.
- Only `REMOTE_ADDR` is trusted for the acceptance IP, because forwarding headers can be forged. Sites behind a trusted proxy can use the `mdocs_client_ip` filter.
- Dompdf runs with remote fetching and PHP disabled.
- Uninstalling **keeps** documents and acceptance records by default, because they can be legal evidence. Define `MDOCS_REMOVE_ALL_DATA` as `true` to remove everything.

**Nginx:** `.htaccess` is ignored there, so add:

```nginx
location ^~ /wp-content/uploads/member-documents/ { deny all; }
```

## Extending

| Hook | Use |
|---|---|
| `mdocs_member_values` (filter) | Add placeholders, e.g. a subscription plan or renewal date |
| `mdocs_pdf_renderer` (filter) | Swap in another PDF engine |
| `mdocs_capability` (filter) | Capability for the admin screens (default `manage_options`) |
| `mdocs_documents_page_url` (filter) | Link used in emails |
| `mdocs_client_ip` (filter) | Real client IP behind a trusted proxy |
| `mdocs_document_issued` (action) | React to a newly issued document |

To issue a document from your own code, for example when a WooCommerce subscription becomes active:

```php
add_action( 'woocommerce_subscription_status_active', function ( $subscription ) {
    $service = \MemberDocuments\Plugin::service();
    $user    = get_user_by( 'id', $subscription->get_user_id() );
    if ( $service && $user ) {
        $service->issue( $user->ID, 1 /* template ID */, ( new \MemberDocuments\Documents\MemberValues() )->for_user( $user ) );
    }
} );
```

## Development

Requires PHP 8.1+ and Composer.

```bash
composer install
composer test        # PHPUnit unit tests (Brain Monkey for WordPress functions)
composer lint        # WordPress Coding Standards + PHP compatibility
```

CI runs the tests on PHP 8.1 to 8.4 and checks coding standards on every push. Tagging `v*` builds a release zip in which Dompdf is namespaced under `MemberDocuments\Vendor` with [Strauss](https://github.com/BrianHenryIE/strauss), so it can't clash with another plugin's copy.

## Licence

GPL-2.0-or-later.
