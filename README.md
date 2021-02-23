Algolia Search for Magento 2
==================

![Latest version](https://img.shields.io/badge/latest-3.0.2-green.svg)
![Magento 2](https://img.shields.io/badge/Magento-%32.3,%202.4-blue.svg)

[![CircleCI](https://circleci.com/gh/algolia/algoliasearch-magento-2/tree/master.svg?style=svg)](https://circleci.com/gh/algolia/algoliasearch-magento-2/tree/master)

-------

🔎  &nbsp; **Need help?** Check out our [Technical Troubleshooting Guide](https://www.algolia.com/doc/integration/magento-2/troubleshooting/technical-troubleshooting/). For feedback, bug reporting, or unresolved issues with the extension, please contact us at [support@algolia.com](mailto:support@algolia.com). Please include your Magento version, extension version, application ID, and steps to reproducing your issue. Add additional information like screenshots, screencasts, and error messages to help our team better troubleshoot your issues.

-------

#### Magento 2.4 compatibility & extension's versions End of Life

We are happy to announce that the version 3.x of our extension is now compatible with Magento 2.4. Review the [Customisation](https://github.com/algolia/algoliasearch-magento-2#customisation) section to learn more about the differences between our extension versions.

Additionally, we are announcing the end of life for our legacy versions. We will continue to support and backport major changes to the minor branches until the defined dates below. We will not accept community PRs for those branches after this date. 

| Extension Version | End of Life |
| --- | --- |
| v1.x | Dec 2020 |
| v2.x | Dec 2020 |
| v3.x | N/A |

-------

- **Autocompletion menu:** Offer End-Users immediate access to your whole catalog from the dropdown menu, whatever your number of categories or attributes.

- **Instantsearch results page:** Have your search results page, navigation and pagination updated in realtime, after each keystroke.

Official website: [https://www.algolia.com/solutions/magento/](https://www.algolia.com/solutions/magento/).

*Note: if your store is running under Magento version 1.x, please check our [Algolia for Magento 1 extension](https://github.com/algolia/algoliasearch-magento).*

Demo
--------------

Try the autocomplete and the instantsearch results page on our [live demo](https://magento2.algolia.com).

Algolia Search
--------------

[Algolia](http://www.algolia.com) is a search engine API as a service capable of delivering realtime results from the first keystroke.

This extension replaces the default search of Magento with a typo-tolerant, fast & relevant search experience backed by Algolia. It's based on [algoliasearch-client-php](https://github.com/algolia/algoliasearch-client-php), [autocomplete.js](https://github.com/algolia/autocomplete.js) and [instantsearch.js](https://github.com/algolia/instantsearch.js).

Documentation
--------------

Check out the [Algolia Search for Magento 2 documentation](https://www.algolia.com/doc/integration/magento-2/getting-started/quick-start/).


Installation
------------

The easiest way to install the extension is to use [Composer](https://getcomposer.org/) and follow our [getting started guide](https://www.algolia.com/doc/integration/magento-2/getting-started/quick-start/).

Run the following commands:

- ```$ composer require algolia/algoliasearch-magento-2```
- ```$ bin/magento module:enable Algolia_AlgoliaSearch```
- ```$ bin/magento setup:upgrade && bin/magento setup:static-content:deploy```

Upgrading from 1.x to 2.x
------------
With the release of a new major version, we have decided to create minor and major version releases to allow those that want to continue on the minor version. This update will **break compatibility**. Please read the [upgrade guide](https://www.algolia.com/doc/integration/magento-2/getting-started/upgrading/#upgrading-from-v1-to-v2) for all of the file changes and updates included in this release. 

If you would like to stay on the minor version, please upgrade your composer to only accept versions less than version 2 like the example:

`"algolia/algoliasearch-magento-2": ">=1.13.1 <2.0"`

Contribution
------------

To start contributing to the extension follow the [contributing guildelines](.github/CONTRIBUTING.md).

Customisation
------------
The extension uses libraries to help assist with the frontend implementation for autocomplete, instantsearch, and insight features. It also uses the Algolia PHP client to leverage indexing and search methods from the backend. When you approach customisations for either, you have to understand that you are customising the implementation itself and not the components it is based on.

These libraries are here to help add to your customisation but because the extension has already initialised these components, you should hook into the area between the extension the libraries.

#### The Extension JS Bundle
Knowing the version of the library will help you understand what is available in these libraries for you to leverage in terms of customisation. This table will help you determine which documentation to reference when you start working on your customisation.

| Extension Version |	autocomplete.js | instantsearch.js | search-insights.js |
| --- | --- | --- | --- |
| v1.x | [0.26.0](https://github.com/algolia/autocomplete.js/tree/v0.26.0) | [2.10.2](https://github.com/algolia/instantsearch.js/tree/v2.10.2) | [0.0.14](https://cdn.jsdelivr.net/npm/search-insights@0.0.14) |
| v2.x | [0.38.0](https://github.com/algolia/autocomplete.js/tree/v0.38.0) | [4.7.2](https://github.com/algolia/instantsearch.js/tree/v4.7.2) | [1.4.0](https://github.com/algolia/search-insights.js/tree/v1.4.0) |
| v3.x | [0.38.0](https://github.com/algolia/autocomplete.js/tree/v0.38.0) | [4.15.0](https://github.com/algolia/instantsearch.js/tree/v4.15.0) | [1.7.1](https://github.com/algolia/search-insights.js/tree/v1.7.1) |

The autocomplete and instantsearch libraries are accessible in the `algoliaBundle` global. This bundle is a prepackage javascript file that contains it's dependencies. What is included in this bundle can be seen here:

v1.x latest bundle: https://github.com/algolia/algoliasearch-extensions-bundle/blob/ISv2/package.json \
v2.x latest bundle: https://github.com/algolia/algoliasearch-extensions-bundle/blob/ISv4/package.json

The search-insights.js library is standalone.

Refer to these docs when customising your Algolia Magento extension frontend features:
 - [Autocomplete](https://www.algolia.com/doc/integration/magento-2/customize/autocomplete-menu/)
 - [Instantsearch](https://www.algolia.com/doc/integration/magento-2/customize/instant-search-page/)
 - [Frontend Custom Events](https://www.algolia.com/doc/integration/magento-2/customize/custom-front-end-events/)


#### The Algolia PHP API Client
The extension does most of the heavy lifting when it comes to gathering and preparing the data needed for indexing to Algolia. In terms of interacting with the Algolia Search API, the extension leverages the PHP API Client for backend methods including indexing, configuration, and search queries.

Depending on the extension version you are using, you could have a different PHP API client version powering the extension's backend functionality.

| Extension Version | API Client Version |
| --- | --- |
| v1.x | [1.28.0](https://github.com/algolia/algoliasearch-client-php/tree/1.28.0) |
| v2.x | [2.5.1](https://github.com/algolia/algoliasearch-client-php/tree/2.5.1) |
| v3.x | [2.5.1](https://github.com/algolia/algoliasearch-client-php/tree/2.5.1) |

Refer to these docs when customising your Algolia Magento extension backend:
- [Indexing](https://www.algolia.com/doc/integration/magento-2/how-it-works/indexing/)
- [Dispatched Backend Events](https://www.algolia.com/doc/integration/magento-2/customize/custom-back-end-events/)


Need Help?
------------
Here are some helpful documentation to help with your issue:

- [General FAQs](https://www.algolia.com/doc/integration/magento-2/troubleshooting/general-faq/)
- [Technical Troubleshooting Guide](https://www.algolia.com/doc/integration/magento-2/troubleshooting/technical-troubleshooting/)
- [Indexing Queue](https://www.algolia.com/doc/integration/magento-2/how-it-works/indexing-queue/)
- [Frontend Custom Events](https://www.algolia.com/doc/integration/magento-2/customize/custom-front-end-events/)
- [Dispatched Backend Events](https://www.algolia.com/doc/integration/magento-2/customize/custom-back-end-events/)

For feedback, bug reporting, or unresolved issues with the extension, please contact us at [support@algolia.com](mailto:support@algolia.com). Please include your Magento version, extension version, application ID, and steps to reproducing your issue. Add additional information like screenshots, screencasts, and error messages to help our team better troubleshoot your issues.

