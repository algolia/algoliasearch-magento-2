# CHANGELOG

## 1.0.1

- Fixed issue with merging JS files - added new line at the end of [algoliaBundle.min.js](https://github.com/algolia/algoliasearch-magento-2/blob/master/view/frontend/web/internals/algoliaBundle.min.js)
- Fixed page indexing when some excluded pages were set
- Fixed data types of `enabled` variables in `algoliaConfig`
- Fixed few typos

## 1.0.0

- Release

## 0.9.1

- Remove `debug: true` from autocomplete menu

## 0.9.0

- Optimized front-end (#54) - **BC break!** 
    - Only necessary JS configuration is now rendered into HTML code, all other JS code is now loaded in within JS files
    - Templates were re-organized to make it's structure more readable
    - Layout's XML files were rewritten and optimized
    - Extension's assets were removed and replaced by SVGs
- Fixed CSS of autocomplete menu's footer (#55, #58)
- Instantsearch.js library was updated to it's latest version (#56)
- The extension officially supports only 2.0.X versions of Magento, however it's still possible and encouraged to use it on 2.1.0 (#53)
- Fixed some annotations in code (#52, #51)

## 0.8.4

- Always index categories' attribute `include_in_menu`
- Follow Magento 2 coding styles

## 0.8.3

- Add license information to `composer.json`

## 0.8.2

- Fix fatal error thrown on Algolia search Adapter on version ~2.0.0
- Fix version in `composer.json`

## 0.8.1

- Fix fatal error thrown when "Make SEO request" was turned on
- Follow the new Algolia's UA convention

## 0.8.0

Initial stable release