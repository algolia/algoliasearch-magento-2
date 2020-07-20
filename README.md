Algolia Search for Magento 2
==================

![Latest version](https://img.shields.io/badge/latest-2.0.1-green.svg)
![Magento 2](https://img.shields.io/badge/Magento-%3E=2.2%20<2.4-blue.svg)
![PHP >= 7.0.6](https://img.shields.io/badge/PHP-%3E=7.0-green.svg)

[![CircleCI](https://circleci.com/gh/algolia/algoliasearch-magento-2/tree/master.svg?style=svg)](https://circleci.com/gh/algolia/algoliasearch-magento-2/tree/master)

-------

🔎  &nbsp; **Need help?** Check out our [Technical Troubleshooting Guide](https://www.algolia.com/doc/integration/magento-2/troubleshooting/technical-troubleshooting/). For feedback, bug reporting, or unresolved issues with the extension, please contact us at [support@algolia.com](mailto:support@algolia.com). Please include your Magento version, extension version, application ID, and steps to reproducing your issue. Add additional information like screenshots, screencasts, and error messages to help our team better troubleshoot your issues.

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

Need Help?
------------
Here are some helpful documentation to help with your issue:

- [General FAQs](https://www.algolia.com/doc/integration/magento-2/troubleshooting/general-faq/)
- [Technical Troubleshooting Guide](https://www.algolia.com/doc/integration/magento-2/troubleshooting/technical-troubleshooting/)
- [Indexing Queue](https://www.algolia.com/doc/integration/magento-2/how-it-works/indexing-queue/)
- [Frontend Custom Events](https://www.algolia.com/doc/integration/magento-2/customize/custom-front-end-events/)
- [Dispatched Backend Events](https://www.algolia.com/doc/integration/magento-2/customize/custom-back-end-events/)

For feedback, bug reporting, or unresolved issues with the extension, please contact us at [support@algolia.com](mailto:support@algolia.com). Please include your Magento version, extension version, application ID, and steps to reproducing your issue. Add additional information like screenshots, screencasts, and error messages to help our team better troubleshoot your issues.

