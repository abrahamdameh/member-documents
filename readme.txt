=== Member Documents ===
Contributors: abrahamdameh
Tags: documents, pdf, membership, agreements, acceptance
Requires at least: 6.2
Tested up to: 6.8
Requires PHP: 8.1
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Versioned document templates, recorded member acceptance and per-member PDF archives.

== Description ==

Issue agreements, notices and policies to members as PDFs, and keep proof of exactly what each member received and accepted.

* Templates are versioned: editing one creates a new version, so issued documents never change.
* PDFs are generated with Dompdf, with no external service needed.
* Each PDF's SHA-256 hash is stored, so you can check whether a file has been altered.
* Acceptance records the time, IP and browser, and can only be recorded once.
* Members view and accept their documents on any page using the [member_documents] shortcode.
* Documents can be emailed with the PDF attached.
* `wp member-documents verify` checks the whole archive.

== Installation ==

1. Upload the plugin and activate it.
2. Go to Member Documents, add a template, then issue it to a member.
3. Add the [member_documents] shortcode to a page your members can reach.

== Frequently Asked Questions ==

= What happens to documents when I uninstall? =

They are kept, because they may be legal records. To delete everything, add `define( 'MDOCS_REMOVE_ALL_DATA', true );` to wp-config.php before deleting the plugin.

== Changelog ==

= 1.0.0 =
* First release.
