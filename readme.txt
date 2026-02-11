=== Flamingo Filter Plus ===
Contributors: robertopussini
Tags: flamingo, contact form 7, email filter, tld filter, domain filter
Requires at least: 6.7
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Adds TLD and email domain filter dropdowns to Flamingo's Inbound Messages and Address Book admin pages.

== Description ==

**Flamingo Filter Plus** enhances the [Flamingo](https://wordpress.org/plugins/flamingo/) plugin by adding two filter dropdowns to both the **Inbound Messages** and **Address Book** admin list pages:

* **TLD filter** — filter entries by top-level domain (e.g. `.com`, `.ru`, `.it`).
* **Domain filter** — filter entries by full email domain (e.g. `gmail.com`, `yahoo.it`).

Each dropdown shows the count of matching entries so you can quickly identify the most common sources. This is especially useful for bulk spam cleanup operations.

= Features =

* Works on both Inbound Messages (inbox, spam, trash views) and Address Book pages.
* Dropdown counts update per view (inbox vs spam vs trash).
* Filter parameters are preserved across bulk actions (mark as spam, delete, etc.).
* Efficient SQL queries with transient caching (1 hour TTL with automatic invalidation).
* Top 500 TLDs/domains shown to keep dropdowns fast and usable.
* Dynamic domain filtering: selecting a TLD automatically narrows the domain dropdown.
* Fully translatable via standard WordPress i18n.

= Requirements =

* WordPress 6.7 or later
* PHP 7.4 or later
* [Flamingo](https://wordpress.org/plugins/flamingo/) plugin installed and active

== Installation ==

1. Make sure the [Flamingo](https://wordpress.org/plugins/flamingo/) plugin is installed and active.
2. Upload the `flamingo-filter-plus` folder to the `/wp-content/plugins/` directory, or install directly from the WordPress plugin screen.
3. Activate the plugin through the "Plugins" menu in WordPress.
4. Navigate to **Flamingo > Inbound Messages** or **Flamingo > Address Book** to see the new filter dropdowns.

== Frequently Asked Questions ==

= Does this plugin work without Flamingo? =

No. Flamingo Filter Plus requires the Flamingo plugin. If Flamingo is not active, this plugin will show an admin notice and deactivate itself.

= How are TLDs determined? =

TLDs are extracted from the last segment after the final dot in each email address (e.g. `com` from `user@example.com`). Compound TLDs like `co.uk` are split at the last dot, so they appear as `uk`.

= Why do I only see 500 entries in the dropdown? =

To keep the admin interface fast and responsive, the dropdowns are capped at the top 500 results sorted by count. This covers the vast majority of use cases.

= Is the data cached? =

Yes. TLD and domain counts are cached as WordPress transients for 1 hour. The cache is automatically invalidated whenever a Flamingo message or contact is saved or deleted.

= Can I use both filters at the same time? =

Yes. When both TLD and domain are selected, they work as an AND filter. Selecting a TLD also dynamically narrows the domain dropdown to only show matching domains.

== Screenshots ==

1. TLD and domain filter dropdowns on the Inbound Messages page.

== Changelog ==

= 1.0.0 =
* Initial release.
* TLD filter dropdown for Inbound Messages and Address Book.
* Domain filter dropdown with per-view counts (inbox, spam, trash).
* Dynamic domain dropdown filtering based on selected TLD.
* Filter parameters preserved across bulk actions.
* Efficient SQL queries with transient caching (1 hour TTL, automatic invalidation).
* Dependency check with admin notice if Flamingo is missing.
* Fully translatable via standard WordPress i18n.
