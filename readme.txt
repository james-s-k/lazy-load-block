=== Lazy Load Block ===
Contributors: strivewp
Tags: lazy load, performance, gutenberg, blocks, fse, full site editing
Requires at least: 6.0
Tested up to: 6.4
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A WordPress block that loads its inner content with Ajax when scrolled into view, improving initial page load performance. DO NOT lazy load content that needs to be visible to search engines on page load, as it will damage SEO.

== Description ==

The Lazy Load Block improves page performance by deferring the loading of block content until it's needed. Built specifically for WordPress core blocks, it provides seamless lazy loading while maintaining a smooth user experience.

= Key Features =

* Lazy loads inner blocks when they scroll into view
* Smooth loading animations
* Customizable loading trigger point
* Customizable loading spinner
* Fully responsive design
* Optimized for Core Web Vitals
* SEO-conscious implementation
* 🧹Efficient memory management

= Block Compatibility =

Fully Compatible:
* All WordPress core blocks (except for the Row, Stack & Grid blocks)
* Basic third-party blocks that follow core patterns
* Static content blocks
* Media blocks (images, galleries)
* Embed blocks
* Text-based blocks

Limited Compatibility:
* Row, Stack & Grid blocks
* Blocks with custom JavaScript initialization
* Blocks with their own lazy loading
* Interactive blocks (sliders, counters etc.)

Not Recommended For:
* Critical above-the-fold content
* SEO-sensitive content
* Blocks requiring JavaScript initialization

= Performance Benefits =

* Optimized Content Loading
    * HTML content loads only when needed
    * Initial page contains only placeholder containers
    * Progressive content loading on scroll
    * Improved Core Web Vitals scores
    * Efficient asset management

* Server Resource Optimization
    * Deferred block rendering
    * On-demand content processing
    * Reduced initial server load
    * Smart dependency handling
    * Minimal AJAX payload size

= Technical Features =

* Uses Intersection Observer for optimal performance
* Smart asset loading and deduplication
* Loads content via AJAX when needed
* Handles embeds and dynamic content
* Compatible with caching plugins
* Proper cleanup of observers and events
* Follows WordPress coding standards
* Implements proper error handling

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/lazy-load-block`
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Use the Lazy Load Block in the block editor

== Usage ==

1. Add the Lazy Load Block to your content
2. Place any blocks inside it that you want to lazy load
3. Customize the appearance and loading behavior in the block settings
4. The content will load automatically when scrolled into view

== Frequently Asked Questions ==

= Why use lazy loading if assets are loaded upfront? =

Even with assets pre-loaded, the benefits are substantial:
1. Reduced initial HTML - only loads placeholder containers
2. Deferred server processing - content is processed only when needed
3. Optimized memory usage - browser manages fewer DOM nodes
4. Better performance metrics - improved page speed scores

= Does this work with Full Site Editing? =

Yes! The block is fully compatible with Full Site Editing (FSE) and works in:
* Template parts
* Site editor templates
* Template patterns
* Any FSE context

= What blocks can I lazy load? =

You can lazy load any WordPress blocks, including:
* Core blocks
* Third-party blocks that don't require JavaScript initialization
* Block patterns
* Reusable blocks

However, be cautious with:
* Blocks requiring JavaScript initialization
* Complex interactive blocks
* Blocks with their own lazy loading
* Critical above-the-fold content for SEO reasons

= How do I customize the loading behavior? =

The block offers several customization options:
* Animation style (fade, slide-up, etc.)
* Loading offset distance
* Spinner appearance
* Animation duration
* And more in the block settings

== Screenshots ==

1. Block in action with loading animation
2. Block settings panel
3. Example in template part
4. Full Site Editor compatibility

== Changelog ==

= 1.0.0 =
* Initial release
* Full Site Editing support
* Template parts compatibility
* Reusable blocks support

== Upgrade Notice ==

= 1.0.0 =
Initial release with Full Site Editing support. 