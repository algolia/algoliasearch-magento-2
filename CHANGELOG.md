# CHANGE LOG

## 1.8.4

- **Added compatibility with Magento 2.3** (#624)
- When searching for empty string, search results page displayed "__empty__" as searched query. Now it doesn't display anything (#586)
- Fixed failing configuration when query rules are not enabled on Algolia application (#591, #604)
- Removed categories from No results links in autocomplete menu when Categories are not set as attribute for faceting (#592)
- Fixed issue with serialized arrays when Autocomplete or Instant search features are turned off (#593)

## 1.8.3

* Removed the default facet query rule (attribute "color") (#600)

## 1.8.2

* Fixed error which showed Instant search components on checkout page (#572)
* Fixed administration categories and category merchandising on Magento 2.1 (#573)
* Fixed indexing queue page on Magento 2.1 (#575)
* Fixed configurable products' price calculation when parent product has zero price (#580)
* Fixed processed jobs removal from indexing queue (#582)

## 1.8.1

* Fixed PHP 5.5 support (#562)
* Fixed `archive` table creation (#566)
* Fixed PHP notice on not recognised product type (#566)

## 1.8.0

### FEATURES
- Possibility to [reindex specific SKUs](https://community.algolia.com/doc/m2/sku-reindexing-form/) (#536)
    - the form will give an option to reindex specific SKU(s)
    - if the product shouldn't be reindexed, the form shows the exact reason why the product is not indexed 
- Category visual merchandiser - Magento 2.1 and higher (#510)
    - the tool gives possibility to visually merchandise products on category pages powered by Algolia
    - it's placed in category detail in tab "Algolia Merchandising"
- Indexing queue page (#537)
    - The page shows the status and remaining jobs in indexing queue
    - It offers suggestions to improve performance of the queue to get the fastest indexing
- "Non-castable" attributes can now be specified in configuration (#507)
- Added support for tier prices (#558)

### UPDATES
- Configuration page was proofread and enhanced to provide better UX (#526, #529, #531)
- Values of `sku`s and `color`s are now correctly index within record of main configurable product
- Price in filter is correctly formatted (#539)
- Use correct column name (`row_id` vs. `entity_id`) based on staging module availability (#544)
- Improved `algolia_after_products_collection_build` event to pass all relevant parameters (#546)
- The extension has improved [Continuous Integration build](https://github.com/algolia/algoliasearch-magento-2/blob/master/.github/CONTRIBUTING.md) checking quality of code, coding standards and tests (#557)
- Refactored price calculation class (#558)

### FIXES
- Fixed incorrect replacement of "+" and "-" of toggle button of facet panel (#532)
- Fixed indexed URLs for CMS pages (#551)

## 1.7.2

Fixed JavaScript issue causing malfunctioning the extension in IE 11 (#538)

## 1.7.1

### UPDATES
- Algolia JS bundle were updated to it's latest version (#504)

### FIXES
- Fixed issue where configurable products were indexed with "0" prices (#527)
- The extension doesn't throw a fatal error when Algolia credentials are not provided (#505)
- Catalog rule's prices are now correctly indexed within configurable products (#506)
- Scope is correctly added to URLs (#509, #513)

## 1.7.0

### FEATURES
- [Click & Conversion analytics](https://www.algolia.com/doc/guides/analytics/click-analytics/) support (#435, #468, #486, #498) - [Documentation](https://community.algolia.com/magento/doc/m2/click-analytics/)
- Option to automatically create ["facet" query rules](https://www.algolia.com/doc/guides/query-rules/query-rules-overview/?language=php#dynamic-facets--filters) (#438)
- Extension now supports upcoming Algolia's A/B testing feature (#492)

### UPDATES
- Frontend event hooks mechanism was refactored to support multi event listeners (#437) - [Documentation](https://community.algolia.com/magento/doc/m2/frontend-events/)
- Refactoring of code to be more robust and reliable (#436)
- Product is updated in Algolia on it's stock status update (#443)
- Product thumbnail size is now configurable via `etc/view.xml` file (#448)
- `SKU`, `name`, `description` products' attributes are not casted from string (#483)
- Parent product of update child product is now always reindexed (#482)
- `EMPTY_QUEUE` constant name was replaced by more descriptive `PROCESS_FULL_QUEUE` name (#491)
- Refactored `CategoryHelper` to remove memory leak (#495)
- Expired special prices are now replaced by default prices even without reindex (#499)
- [InstantSearch.js library](https://community.algolia.com/instantsearch.js/) was updated to it's latest version bringing [routing feature](https://community.algolia.com/instantsearch.js/v2/guides/routing.html) to the extension (#500)
- Added link to Algolia configuration directly to "Stores" panel (#501)

### FIXES
- Extension now correctly removes disable products from Algolia (#447)
- Fixed the issue when some records weren't indexed because of too big previous record (#451)
- Fixed issue when product was not added to cart on first attempt after page load (#460)
- Removed filenames with asterisks which prevented the extension from being installed on Windows (#461)
- Fixed issue which fetched from DB not relevant sub products (#462)
- Fix issues with wrong category names (#467)
- Fixed issue when backend rendering was prevented not only on category pages (#471)
- Pages from disabled stores are not indexed anymore (#475)
- Fixed image types IDs to configure image sizes via `etc/view.xml` file (#478)
- Fixed exploding of line breaks on User Agents setting for Prevent backend rendering feature to work on Windows servers (#479)
- Correct default values for query suggestions (#484)
- TMP index is now not removed with not used replica indices (#488)
- Fixed documentation links (#490)
- Fixed issue which overrode instant search search parameters (#501)

## 1.6.0

### FEATURES
- New indexer which deletes all products which shouldn't be indexed in Algolia (#405)
- Facets now support [**search for facet values**](https://www.algolia.com/doc/api-reference/api-methods/search-for-facet-values/) feature (#408)
- The extension now displays the right image for a color variant depending on search query or selected color filter (#409)
- Experimental feature to prevent backend rendering of category and search results pages (#413)
    - Use very carefully and read [documentation](https://community.algolia.com/magento/doc/m2/prevent-backend-rendering/) before enabling it
- Infinite scrolling on instant search pages (#414)
- Replica indices are automatically deleted when removing sorting options in configuration (#430)

### UPDATES
- Code is now more readable - **BC Break**
    - shorter lines (#402)
    - lower cyclomatic complexity (#410)
- Price calculation was moved to separate class (#411) - **BC Break**
- Most of `protected` class members were changed to `private` ones (#415) - **BC Break**
- Ranking formula of replicas now contain `filters` rule (#419)
- It's now possible to remove autocomplete menu sections by specifying 0 results in configuration (#429)

### FIXES
- Fixed buggy behavior on iOS when scrolling in autocomplete was not possible (#401)
- Fixed magnifying glass icon next to query suggestions (#403)
- Fixed URL of image placeholders (#428)

## 1.5.0

### FEATURES
- Added option to index empty categories (#382)
- Travis CI now correctly runs builds from community pull requests (#383, #384)
- **BC Break** - Instant search page is now powered by InstantSearch.js v2 (#394)
    - Migration guide: https://community.algolia.com/instantsearch.js/v2/guides/migration.html
    - Magnifier glass and reset search icons are now added directly by ISv2 - old were removed
    - Some template variables were changed (see migration guide)
    - CSS for slider was refactored
- The extension code is checked by [PHPStan](https://github.com/phpstan/phpstan) (#396)

### UPDATES
- Products' and categories' collections now uses `distinct` to fetch only unique records (#371)
- SKUs, names and descriptions are not casted from string to numeric types (#375)
- Configurable product is hidden from search when all its variants are out of stock and out of stock products shouldn't be displayed (#378)
- Stock Qty is not fetched with inner query in collection, but with StockRegistry (#386)
- Indexing jobs for disabled stores are not added to queue anymore (#392)
- **BC Break** - `BaseHelper` class was completely removed (#388, #390)
    - Entity helpers are not dependent on any base class
    - Indexer names can be get from `Data` helper now

### FIXES
- Query suggestions are correctly escaped when displayed (#373)
- Fixed error when `in_stock` comes from `$defaultData` (#374)
- Grouped products now correctly display price ranges (#377)
- The extension now correctly deletes out of stock products from Algolia (#381)
- **BC Break** - Fixed fetching of group ID on frontend (#365)
- Original prices is now displayed correctly with customer groups enabled (#391, #398, #399)
- Cart icon is now clickable on mobile devices (#395, #397)

## 1.4.0

### FEATURES
- When a record is too big to be indexed in Algolia the description displays which attribute is the longest and why the record cannot be indexed (#367)

### UPDATES
- Algolia configuration menu was moved lower (#329)
- Optimized TravisCI (#335)
- More restricted search adapter (#357)
- Indexed product URLs now will never contain SID (#361)

### FIXES
- Fixed price calculations (#330)
- Fixed instant search page with no results - now it displays better "no results" message (#336)
- Fixed attributes to retrieve (#338)
- Fixed `unserialize` method for Magento 2.2 (#339)
- Fixed undefined array index `order` (#340)
- Fixed buggy hover on in autocomplete menu on iOS devices (#351)
- Fixed issue with mixed facets and sorts labels (#354)
- Fixed special prices for customer groups (#359)
- Fixed categories fetching (#366)

## 1.3.0

Since this release, the extension is **Enterprise Edition compliant**!

### FEATURES
- Support of **Magento 2.2** (#319)
- Processing of queue runner is now logged in `algoliasearch_queue_log` table (#310)
- Enabled selection of autocomplete items (#316)

### UPDATES
- Refactored ConfigHelper - removed unused methods (#317)

### FIXES
- API is not called on a non-product page (#311)
- Query rules are not erased on full reindex with queue enabled (#312)

## 1.2.1

- Added configuration option to turn on debug regime for autocomplete menu (#281)
- Fixed the infinite loop in queue processing when ran with `EMPTY_QUEUE=1` (#286)
- Fixed PHP notice on reindex / save settings when `categories` attribute was missing from attributes to index (#293)
- Products with visibility set to `catalog` only are still indexed to show them on category pages (#294)
- Fixed issue which indexed categories within products which shouldn't be displayed in menu (#295)
- Optimized `getPopularQueries` method (#297)
- Fixed issue with missing config default values on Magento 2.1.0 and higher (#300)

## 1.2.0

### FEATURES

- Analytics - the extension now uses Magento's GA to measure searches (#253)
    - [Documentation](https://community.algolia.com/magento/doc/m2/analytics/)
- Option to send an extra Algolia settings to Algolia indices (#245)
- The configuration page now displays information about the indexing queue and gives possibility to clear the queue (#262)
- In attribute select boxes (sorts, facets, ranking, ...) is now possible to choose from all attributes and not just those set as "attribute to indexing" (#257)
- Option to disable synonyms management in Magento (#260)
    - By default it's turned off - if you're using synonyms management in Magento, please turn it on after the upgrade
- Extension back-end events are now more granular (#266)
    - [Documentation](https://community.algolia.com/magento/doc/m2/backend/)

### UPDATES

- All CSS selectors were prefixed with Algolia containers and unused styles were removed (#246)
    - **BC break** - please check the look & feel of your results
- Algolia settings are now pushed to Algolia after the save of extra settings configuration page (#258)
- Added titles to configuration sections (#259)
- **BC Break** - Unused "deleteIndices" method were removed (#263)


### FIXES

- Fix the issue with Algolia error when more than 1000 products are supposed to be deleted (#249)
- Fixed the thumbnail URL when using `/pub/` directory as the root directory (#247)
    - [Documentation](https://community.algolia.com/magento/faq/#in-magento2-the-indexed-image-urls-have-pub-at-the-beginning)
- Fix the issue when backend was still enabled even though it was set as disabled in configuration (#256)
- Fix the issue when indexing was disabled, but the extension still performed some indexing operations (#261)
- Fix category fetching on Magento EE (#265)
- Fix the back button on category pages to not return all products from the store (#267)
- CMS pages are no longer index when the "Pages" section is removed from Addition sections (#271)

## 1.1.0

- Fixed products prices - now all prices (currencies, promos, ...) are correctly indexed (#233)
- Optimized the number of delete queries (#209)
- Image URLs are indexed without protocol (#211)
- Queue processing is now optimized and process always the right number of jobs (#208)
- Fixed the autocomplete menu on mobile (#215, #222)
- Fixed the replica creation with customers' groups enabled (#217)
- Fixed broken reference on Magento_Theme (#224)
- Fix for overloaded queued jobs (#229, #228)
- Fixed encoding of CMS pages (#227)
- Fixed image URLs with double slashes (#234)
- Fixed `attributesToRetrieve` to contain category attributes (#235)

**BC Breaks**
- Refactored `configuration.phtml` - all the logic moved to `Block` class (#238)
- Optimized CSS and assets - removed couple of images and CSS classes (#236)
- JS hooks - instantsearch.js file was completely refactored to create IS config object which can be manipulated via hook method (#240)

## 1.0.10

- Fix IS on replaced category page (#202)

## 1.0.9

- `algoliaBundle` is now loaded only via requireJS (#171)
- Fixed warning raised by nested emulation (#175)
- Fixed indexing of secured URLs (#174)
- Fixed the issue when some products were not indexed due to bad SQL query (#181)
- `categories` attribute is automatically set as an attribute for faceting when Replace categories is turned on (#184)
- Fixed set settings on TMP indices (#186)
- Settings now sends `searchableAttributes` instead of `attributesToIndex` (#187)
- Fixed IS page when using pagination and refreshing the page (#195)
- Fixed filters and pagination on category pages (#200)

## 1.0.8

- Fixed the requireJS issue introduced in 1.0.6, which ended up in administration's JavaScript not working (#168, #169)

## 1.0.6

- Fixed indexing of out-of-stock products (#142)
- Fixed CSS for showing products's ratings on instant search page (#143)
- The category refinement is now displayed in Current filters section and it's easy to remove on replaced category page (#144)
- Fixed indexing of prices with applied price rules (#145, #160)
- Formatted default original price attribute is now retrieved from Algolia by default (#151)
- Fixed showing of "Add to cart" button (#152)
- Exception is not thrown anymore from Algolia indexers when Algolia credentials are not filled in (#155)
    - Fixes the installation abortion when the extension was installed before Magento's installation
- Fixed the layout handle to load templates correctly to Ultimo theme (#156)
- Fixed admin JavaScript to load correctly and not conflict with other pages (#159, #162)
- `script` and `style` tags are now completely (with it's content) removed from CMS pages' indexed content (#163)
- New version of instantsearch.js and autocomplete.js libraries (#165)

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