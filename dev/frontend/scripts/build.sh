#!/usr/bin/env bash

yarn add algoliasearch-extensions-bundle@latest --save &&
(cd node_modules/algoliasearch-extensions-bundle && npm run build) &&
cp node_modules/algoliasearch-extensions-bundle/dist/algoliaBundle.min.js* ../../view/frontend/web/internals &&
cp node_modules/algoliasearch-extensions-bundle/dist/algoliaAdminBundle.min.js* ../../view/adminhtml/web/
