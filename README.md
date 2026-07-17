# Pull Quotes

Pull Quotes is a block-editor plugin for marking text that already appears in a post and echoing it as a floated callout.

## Requirements

- WordPress 7.0 or later
- PHP 8.3 or later
- Block-authored content

## Authoring

Select text in a Rich Text block and choose **Pull quote** in the block toolbar. The settings control:

- **Offset:** the number of visible authored blocks from the block containing the selected text. Zero inserts the callout immediately before that block. Parser whitespace, generated pull quotes, and nested-container boundaries do not count.
- **Direction:** move back or forward through the block tree.
- **Alignment:** float the generated callout left or right.

The selected sentence remains in the body. On the front end, the plugin removes the inline authoring marker and inserts a decorative `<aside class="pullquote" aria-hidden="true">` before the resolved block. Rendering is performed server-side and does not require jQuery.

## Migrating shortcodes

Version 2.0 does not render `[pullquote]` shortcodes at runtime.

- Go to **Tools → Pull Quotes Migration** to preview or migrate shortcodes in existing block posts.
- Or run `wp pull-quotes migrate --dry-run`, then `wp pull-quotes migrate`.
- For classic posts, open the post and use **Convert to blocks**. The editor integration converts its pull-quote shortcodes into inline markers as part of Core's conversion.

The migration maps positive `back` and `forward` values to `data-offset` plus `data-direction`, and preserves alignment and width where present.

## Development

Run `composer install` for PHP tooling and `npm install` for editor tooling. Verify changes with:

```text
composer lint
npm run lint:js
npm run build
```

## Changelog

### 2.0.0

- Require PHP 8.3 and WordPress 7.0.
- Add a block-editor Rich Text format with offset, direction, and alignment controls.
- Render pull quotes server-side using parsed block structure, without jQuery.
- Keep quoted sentences in the reading flow and hide decorative copies from assistive technology.
- Add admin, WP-CLI, and Convert-to-blocks migration paths for legacy shortcodes.
- Remove the classic-editor TinyMCE and Quicktags integrations and runtime shortcode support.

### 1.0.2

- Allow forward and back parameters to be set to 0.

### 1.0.1

- Add support for new TinyMCE.

### 1.0.0

- Released to the WordPress.org repository.
