define([
    'jquery',
    'algoliaSearchLib',
    'algoliaInstantSearchLib',
    'recommendProductsHtml',
    'domReady!',
], function ($, { liteClient: algoliasearch }, instantsearch, recommendProductsHtml) {
    if (typeof algoliaConfig === 'undefined') {
        return;
    }

    const {
        frequentlyBoughtTogether,
        relatedProducts,
        trendingItems,
        lookingSimilar,
    } = instantsearch.widgets;

    const transformItems = function (items) {
        return items.map((item, index) => ({
            ...item,
            position: index + 1,
        }));
    };

    const buildTemplates = function (title, addToCart) {
        return {
            header(data, { html }) {
                return recommendProductsHtml.getHeaderHtml(html, title);
            },
            item(item, { html }) {
                return recommendProductsHtml.getItemHtml(item, html, addToCart);
            },
            empty() {
                return '';
            },
        };
    };

    // trendingItems needs both facetName and facetValue together; otherwise it
    // fetches global trends. Omitting the keys reproduces the previous behavior
    // where an empty facet string meant "no facet".
    const facetParams = function (facetName, facetValue) {
        if (facetName && facetValue) {
            return { facetName, facetValue };
        }
        return {};
    };

    // Defer building the InstantSearch instance (and its getRecommendations
    // requests) until one of the widget containers nears the viewport. Falls
    // back to requestIdleCallback / immediate build when IntersectionObserver
    // is unavailable. Builds exactly once.
    const deferBuild = function (containers, build) {
        let built = false;
        const run = function () {
            if (built) {
                return;
            }
            built = true;
            build();
        };

        if (typeof IntersectionObserver === 'undefined') {
            if (typeof window.requestIdleCallback === 'function') {
                window.requestIdleCallback(run);
            } else {
                run();
            }
            return;
        }

        const observer = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    observer.disconnect();
                    run();
                }
            });
        }, { rootMargin: '200px' });

        containers.forEach(function (container) {
            observer.observe(container);
        });
    };

    return function (config, element) {
        $(function ($) {
            const indexName = algoliaConfig.indexName + '_products';
            const appId = algoliaConfig.applicationId;
            const apiKey = algoliaConfig.apiKey;
            const searchClient = algoliasearch(appId, apiKey);
            const objectIDs = config.objectIDs;

            const widgets = [];
            const containers = [];

            const register = function (selector, createWidget) {
                const container = document.querySelector(selector);
                if (!container) {
                    return;
                }
                widgets.push(createWidget(container));
                containers.push(container);
            };

            if (
                $('body').hasClass('catalog-product-view') ||
                $('body').hasClass('checkout-cart-index')
            ) {
                if (
                    (algoliaConfig.recommend.enabledFBT &&
                        $('body').hasClass('catalog-product-view')) ||
                    (algoliaConfig.recommend.enabledFBTInCart &&
                        $('body').hasClass('checkout-cart-index'))
                ) {
                    register('#frequentlyBoughtTogether', (container) =>
                        frequentlyBoughtTogether({
                            container,
                            objectIDs,
                            limit             : algoliaConfig.recommend.limitFBTProducts,
                            transformItems,
                            templates         : buildTemplates(
                                algoliaConfig.recommend.FBTTitle,
                                algoliaConfig.recommend.isAddToCartEnabledInFBT
                            ),
                        })
                    );
                }
                if (
                    (algoliaConfig.recommend.enabledRelated &&
                        $('body').hasClass('catalog-product-view')) ||
                    (algoliaConfig.recommend.enabledRelatedInCart &&
                        $('body').hasClass('checkout-cart-index'))
                ) {
                    register('#relatedProducts', (container) =>
                        relatedProducts({
                            container,
                            objectIDs,
                            limit             : algoliaConfig.recommend.limitRelatedProducts,
                            transformItems,
                            templates         : buildTemplates(
                                algoliaConfig.recommend.relatedProductsTitle,
                                algoliaConfig.recommend.isAddToCartEnabledInRelatedProduct
                            ),
                        })
                    );
                }
            }

            if (
                (algoliaConfig.recommend.isTrendItemsEnabledInPDP &&
                    $('body').hasClass('catalog-product-view')) ||
                (algoliaConfig.recommend.isTrendItemsEnabledInCartPage &&
                    $('body').hasClass('checkout-cart-index'))
            ) {
                register('#trendItems', (container) =>
                    trendingItems({
                        container,
                        ...facetParams(
                            algoliaConfig.recommend.trendItemFacetName,
                            algoliaConfig.recommend.trendItemFacetValue
                        ),
                        limit             : algoliaConfig.recommend.limitTrendingItems,
                        transformItems,
                        templates         : buildTemplates(
                            algoliaConfig.recommend.trendingItemsTitle,
                            algoliaConfig.recommend.isAddToCartEnabledInTrendsItem
                        ),
                    })
                );
            } else if (
                algoliaConfig.recommend.enabledTrendItems &&
                typeof config.recommendTrendContainer !== 'undefined'
            ) {
                register('#' + config.recommendTrendContainer, (container) =>
                    trendingItems({
                        container,
                        ...facetParams(config.facetName, config.facetValue),
                        limit             : config.numOfTrendsItem
                            ? parseInt(config.numOfTrendsItem)
                            : algoliaConfig.recommend.limitTrendingItems,
                        transformItems,
                        templates         : buildTemplates(
                            algoliaConfig.recommend.trendingItemsTitle,
                            algoliaConfig.recommend.isAddToCartEnabledInTrendsItem
                        ),
                    })
                );
            }

            if (
                (algoliaConfig.recommend.isLookingSimilarEnabledInPDP &&
                    $('body').hasClass('catalog-product-view')) ||
                (algoliaConfig.recommend.isLookingSimilarEnabledInCartPage &&
                    $('body').hasClass('checkout-cart-index'))
            ) {
                register('#lookingSimilar', (container) =>
                    lookingSimilar({
                        container,
                        objectIDs,
                        limit             : algoliaConfig.recommend.limitLookingSimilar,
                        transformItems,
                        templates         : buildTemplates(
                            algoliaConfig.recommend.lookingSimilarTitle,
                            algoliaConfig.recommend.isAddToCartEnabledInLookingSimilar
                        ),
                    })
                );
            } else if (
                algoliaConfig.recommend.enabledLookingSimilar &&
                objectIDs &&
                typeof config.recommendLSContainer !== 'undefined'
            ) {
                register('#' + config.recommendLSContainer, (container) =>
                    lookingSimilar({
                        container,
                        objectIDs,
                        limit             : config.numOfLookingSimilarItem
                            ? parseInt(config.numOfLookingSimilarItem)
                            : algoliaConfig.recommend.limitLookingSimilar,
                        transformItems,
                        templates         : buildTemplates(
                            algoliaConfig.recommend.lookingSimilarTitle,
                            algoliaConfig.recommend.isAddToCartEnabledInLookingSimilar
                        ),
                    })
                );
            }

            if (!widgets.length) {
                return;
            }

            deferBuild(containers, function () {
                const search = instantsearch({
                    indexName,
                    searchClient,
                });
                search.addWidgets(widgets);
                search.start();
            });
        });
    };
});
