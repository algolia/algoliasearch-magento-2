define([
    'jquery',
    'algoliaBundle',
    'recommend',
    'recommendJs',
    'recommendProductsHtml',
    'domReady!'
],function ($, algoliaBundle, recommend, recommendJs, recommendProductsHtml) {
    'use strict';

    if (typeof algoliaConfig === 'undefined') {
        return;
    }

    return function (config, element) {
        $(function ($) {
            this.defaultIndexName = algoliaConfig.indexName + '_products';
            const appId = algoliaConfig.applicationId;
            const apiKey = algoliaConfig.apiKey;
            const recommendClient = recommend(appId, apiKey);
            const indexName = this.defaultIndexName;
            if ($('body').hasClass('catalog-product-view') || $('body').hasClass('checkout-cart-index')) {
                // --- Add the current product objectID here ---
                if ((algoliaConfig.recommend.enabledFBT && $('body').hasClass('catalog-product-view')) || (algoliaConfig.recommend.enabledFBTInCart && $('body').hasClass('checkout-cart-index'))) {
                    recommendJs.frequentlyBoughtTogether({
                        container: '#frequentlyBoughtTogether',
                        recommendClient,
                        indexName,
                        objectIDs: config.algoliObjectId,
                        maxRecommendations: algoliaConfig.recommend.limitFBTProducts,
                        transformItems:function (items) {
                            return items.map((item, index) => ({
                                ...item,
                                position: index + 1,
                            }));
                        },
                        headerComponent({html}) {
                            return recommendProductsHtml.getHeaderHtml(html,algoliaConfig.recommend.FBTTitle);
                        },
                        itemComponent({item, html}) {
                            return recommendProductsHtml.getItemHtml(item, html, algoliaConfig.recommend.isAddToCartEnabledInFBT);
                        },
                    });
                }
                if ((algoliaConfig.recommend.enabledRelated && $('body').hasClass('catalog-product-view')) || (algoliaConfig.recommend.enabledRelatedInCart && $('body').hasClass('checkout-cart-index'))) {
                    recommendJs.relatedProducts({
                        container: '#relatedProducts',
                        recommendClient,
                        indexName,
                        objectIDs: config.algoliObjectId,
                        maxRecommendations: algoliaConfig.recommend.limitRelatedProducts,
                        transformItems:function (items) {
                            return items.map((item, index) => ({
                                ...item,
                                position: index + 1,
                            }));
                        },
                        headerComponent({html}) {
                            return recommendProductsHtml.getHeaderHtml(html,algoliaConfig.recommend.relatedProductsTitle);
                        },
                        itemComponent({item, html}) {
                            return recommendProductsHtml.getItemHtml(item, html, algoliaConfig.recommend.isAddToCartEnabledInRelatedProduct);
                        },
                    });
                }
            }

            if ((algoliaConfig.recommend.isTrendItemsEnabledInPDP && $('body').hasClass('catalog-product-view')) || (algoliaConfig.recommend.isTrendItemsEnabledInCartPage && $('body').hasClass('checkout-cart-index'))) {
                recommendJs.trendingItems({
                    container: '#trendItems',
                    facetName: algoliaConfig.recommend.trendItemFacetName ? algoliaConfig.recommend.trendItemFacetName : '',
                    facetValue: algoliaConfig.recommend.trendItemFacetValue ? algoliaConfig.recommend.trendItemFacetValue : '',
                    recommendClient,
                    indexName,
                    maxRecommendations: algoliaConfig.recommend.limitTrendingItems,
                    transformItems:function (items) {
                        return items.map((item, index) => ({
                            ...item,
                            position: index + 1,
                        }));
                    },
                    headerComponent({html}) {
                        return recommendProductsHtml.getHeaderHtml(html,algoliaConfig.recommend.trendingItemsTitle);
                    },
                    itemComponent({item, html}) {
                        return recommendProductsHtml.getItemHtml(item, html, algoliaConfig.recommend.isAddToCartEnabledInTrendsItem);
                    },
                });
            } else if (algoliaConfig.recommend.enabledTrendItems && typeof config.recommendTrendContainer !== "undefined") {
                let containerValue = "#" + config.recommendTrendContainer;
                recommendJs.trendingItems({
                    container: containerValue,
                    facetName: config.facetName ? config.facetName : '',
                    facetValue: config.facetValue ? config.facetValue : '',
                    recommendClient,
                    indexName,
                    maxRecommendations: config.numOfTrendsItem ? parseInt(config.numOfTrendsItem) : algoliaConfig.recommend.limitTrendingItems,
                    transformItems:function (items) {
                        return items.map((item, index) => ({
                            ...item,
                            position: index + 1,
                        }));
                    },
                    headerComponent({html}) {
                        return recommendProductsHtml.getHeaderHtml(html,algoliaConfig.recommend.trendingItemsTitle);
                    },
                    itemComponent({item, html}) {
                        return recommendProductsHtml.getItemHtml(item, html, algoliaConfig.recommend.isAddToCartEnabledInTrendsItem);
                    },
                });
            }
        });
    }
});
