# Pull Quotes WordPress Plugin

Pull Quotes done right!  No duplicate or out of order content.  Create pull quotes right from your editor.

## Description

Pull Quotes done right.  The pull quotes are created with javascript, so that
you don't have any problems with out of order or duplicate content.

Brought to you by <a href="http://aarondcampbell.com/" title="WordPress Plugins">Aaron D. Campbell</a>

## Installation

1. Use automatic installer to install and active the plugin.

## Frequently Asked Questions

**How do I create a pull quote?**

Simply highlight the text you want to turn into a pull quote and click the pull
quote button in your editor.

**What about the visual editor?  What about the text editor?**

Pull quotes works in both the visual and text editor.

**Can I further customize the location or look of the pull quote?**

Currently there's no UI for this, but you can add a few attributes to the
shortcode to customize:

* align - Possible values are left, right or empty. Default is left.
* width - Specifies the width of the pull quote.  Can be any CSS width string such as 50%, 400px, or 10em.
* back - If you want the pull quote to be separated from the current paragraph, just specify how many paragraphs back to put it.  Can be any number.
* forward - If you want the pull quote to be separated from the current paragraph, just specify how many paragraphs forward to put it.  Can be any number.
* wrap - Used to wrap the pull quote in a paragraph tag.  Good for inserting between paragraphs.  Set to "true" defaults to ""

**This looks ugly!**

Currently there's no CSS packaged with the plugin.  At some point our amazing
designer will help put some together, but for now the styles are inherited from
your theme.  Here are some basic styles you could add to your theme as a
starting point:.

```CSS
/**
 * Pull Quotes
 */

.pulledquote {
	border-top: none;
	border-bottom: none;
	background: transparent;
	text-indent: 0;
	margin: 20px;
	-webkit-box-shadow: none;
	-moz-box-shadow: none;
	text-transform: uppercase;
	color: #c65800;
	font-style: italic;
	font-size: 1.6em;
}
span.pulledquote {
	max-width:35%;
}
```

## Changelog

### 1.0.2
* Allow forward and back parameters to be set to 0

### 1.0.1
* Add support for new TinyMCE

### 1.0.0
* Released to wordpress.org repository
