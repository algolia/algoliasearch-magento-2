# CHANGELOG

## 1.0.5

- Official support of Magento >= 2.1 (#117)
- Fixed method signature for delete objects in Algolia (#120)
- Show all configuration options in website and store views (#133)
- Option "Make SEO Request" is enabled by default now (#134)
- CMS pages are now indexed correctly for specific stores (#135)
- Product's custom data now contains it's `type` in `algolia_subproducs_index` event (#136)
- Replica indices are now not created when Instant Search feature is not enabled (#137)
- New Algolia logo is used in autocomplete menu (#138)
- The extension now sends `replicas` index setting instead of `slaves` (#139)
- Products are now indexed correctly into all assigned stores (#140)

## 1.0.4

- Fixed User-Agent from Magento 1 to Magento 2 (#92)
- Fixed additional sections' links in autocomplete menu (#93)
- All searchable attributes are set as Unordered by default (#94)
- Fixed configHelper to use ProductMetadataInterface to get the correct dependecy (#97)
- Fixed indexing of categories when option "Show categories that are not included in the navigation menu" weren't taken into account (#100)
- Fixed backend Algolia adapter to correctly respect "Make SEO request" configuration (#101)
- Fixed images config paths (#104)
- Added specific HTML classes to refinement widget containers (#105)
- Fixed the issue when queue runner didn't process any job after specific settings changed. Now it process always at least one job (#106)
- Fixed the functionality of "Add To Cart" button (#107)
- Attribute `in_stock` is now exposed in Algolia configuration and can be used for custom ranking or sorting (#109)
- Add `algolia_get_retrievable_attributes` custom event to `getRetrievableAttributes` method to allow developers set custom retrievable  attributes for generated API keys (#112)
- Fixed queue issue when `store_id` parameter was not passed to `deleteObjects` categories' operation (#113)

## 1.0.3

- Fixed issue with indexing content on Magento 2.1 EE and higher (#87)
- Fixed page indexing to index pages only from active stores (#82)

## 1.0.2

- Fixed issue with merging JS files in administration - added new line at the end of [algoliaAdminBundle.min.js](https://github.com/algolia/algoliasearch-magento-2/blob/master/view/adminhtml/web/algoliaAdminBundle.min.js)

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