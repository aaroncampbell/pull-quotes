=== Pull Quotes ===
Contributors: aaroncampbell
Tags: pull quote, block editor, typography, writing, accessibility
Requires at least: 7.0
Requires PHP: 8.3
Tested up to: 7.0
Stable tag: 2.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Mark existing post text and echo it as an accessible, server-rendered floated callout.

== Description ==

Pull Quotes adds an inline Rich Text format to the block editor. Select text, choose Pull quote, then set its offset, direction, and alignment.

The selected sentence remains in the body. On the front end, Pull Quotes removes the inline authoring marker and inserts an aria-hidden decorative aside before the resolved block. Rendering is performed server-side and does not require jQuery.

Version 2.0 supports block-authored content only and does not render legacy pullquote shortcodes at runtime.

Collaborate on the plugin: <a href="https://github.com/aaroncampbell/pull-quotes">Pull Quotes on GitHub</a>

Brought to you by <a href="https://aarondcampbell.com/" title="WordPress Plugins">Aaron D. Campbell</a>

== Installation ==

1. Install and activate Pull Quotes.
2. In the block editor, select text in a Rich Text block and choose Pull quote from the block toolbar.
3. Choose an offset, direction, and alignment, then apply the format.

== Screenshots ==

1. Select text in the block editor, apply the Pull quote format, and configure its offset, direction, and alignment.
2. The selected sentence remains in the article while a styled pull quote is positioned alongside the surrounding text.

== Frequently Asked Questions ==

= What does an offset of zero do? =

It inserts the floated aside immediately before the block containing the selected sentence.

= How are larger offsets resolved? =

The plugin moves through visible authored blocks in the selected back or forward direction. Parser whitespace, generated pull quotes, and moves out of nested containers do not count. The aside is inserted before the resolved block.

= What happens to the original sentence? =

It stays in the body's normal reading flow. Only the duplicate aside is hidden from assistive technology.

= How do I migrate legacy shortcodes? =

Use Tools → Pull Quotes Migration for existing block posts, or run `wp pull-quotes migrate`. Use `wp pull-quotes migrate --dry-run` to preview the batch. For a classic post, open it and use Convert to blocks; the editor converts pullquote shortcodes during Core's conversion.

= Does version 2.0 support the Classic Editor? =

No. Version 2.0 is block-only, and legacy pullquote shortcodes are not registered as a runtime shortcode.

== Upgrade Notice ==

= 2.0.0 =
Requires WordPress 7.0 and PHP 8.3. Block content can be batch-migrated under Tools → Pull Quotes Migration; classic posts should be converted to blocks in the editor.

== Changelog ==

= 2.0.0 =
* Require PHP 8.3 and WordPress 7.0.
* Add a block-editor Rich Text format with offset, direction, and alignment controls.
* Render pull quotes server-side using parsed block structure, without jQuery.
* Keep quoted sentences in the reading flow and hide decorative copies from assistive technology.
* Add admin, WP-CLI, and Convert-to-blocks migration paths for legacy shortcodes.
* Remove classic-editor integrations and runtime shortcode support.

= 1.0.2 =
* Allow forward and back parameters to be set to 0.

= 1.0.1 =
* Add support for new TinyMCE.

= 1.0.0 =
* Released to the WordPress.org repository.
