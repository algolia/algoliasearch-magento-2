Algolia Search for Magento
==================

[Algolia Search](http://www.algolia.com) is a hosted full-text, numerical, and faceted search engine capable of delivering realtime results from the first keystroke.

This extension replaces the default search of Magento with a typo-tolerant, fast & relevant search experience backed by Algolia. It's based on [algoliasearch-client-php](https://github.com/algolia/algoliasearch-client-php), [autocomplete.js](https://github.com/algolia/autocomplete.js) and [instantsearch.js](https://github.com/algolia/instantsearch.js).

See features and benefits of [Algolia Search Extension for Magento](https://community.algolia.com/magento).

![Latest version](https://img.shields.io/badge/latest-1.0.5-green.svg)
![Magento 2](https://img.shields.io/badge/Magento-%3E=2.0-blue.svg)
![PHP >= 5.5.22](https://img.shields.io/badge/PHP-%3E=5.5.22-green.svg)

<!-- 
The extension officially supports only 2.0.X versions of Magento. 
It's possible to use it for versions >= 2.1.0, but some unexpected issues might appear. When you experience that, please [open an issue](https://github.com/algolia/algoliasearch-magento-2/issues/new).
-->

Documentation & demo
--------------

Check out our documentation on [community.algolia.com/magento](https://community.algolia.com/magento/doc/m2/getting-started/) and live demo on [https://magento2.algolia.com](https://magento2.algolia.com). 

Installation
------------

The easiest way to install the extension is to use [Composer](https://getcomposer.org/).

Run the following commands:

- ```$ composer require algolia/algoliasearch-magento-2```
- ```$ bin/magento module:enable Algolia_AlgoliaSearch```
- ```$ bin/magento setup:upgrade && bin/magento setup:static-content:deploy```


### Auto-completion menu

Offer End-Users immediate access to your whole catalog from the dropdown menu, whatever your number of categories or attributes.

![demo](gifs/autocomplete.gif)

### Instant search results page

Have your search results page, navigation and pagination updated in realtime, after each keystroke.

![demo](gifs/instantsearch.gif)


