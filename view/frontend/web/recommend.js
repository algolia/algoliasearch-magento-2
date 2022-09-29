requirejs([
    'recommend',
    'recommendJs',
    'algoliaBundle'
], function (recommend, recommendJs, algoliaBundle) {
algoliaBundle.$(function ($) {
    this.config = algoliaConfig;
    this.defaultIndexName = algoliaConfig.indexName + '_products';
    const appId = this.config.applicationId;
    const apiKey = this.config.apiKey;
    const recommendClient = recommend(appId, apiKey);
    const indexName = this.defaultIndexName;
    // --- Add the current product objectID here ---
    const currentObjectID = objectId;
    if (this.config.recommend.enabledFBT) {
        recommendJs.frequentlyBoughtTogether({
            container: '#frequentlyBoughtTogether',
            recommendClient,
            indexName,
            objectIDs: [currentObjectID],
            maxRecommendations: this.config.recommend.limitFBTProducts,
            itemComponent({item, createElement, Fragment}) {
                return createElement(
                    'div',
                    null,
                    createElement(
                        'div',
                        {className: "product-details"},
                        createElement(
                            'a',
                            {className: "product-url", href: item.url},
                            createElement('img', {className: "product-img", src: item.image_url}, item.image_url),
                            createElement('p', {className: "product-name"}, item.name)
                        )
                    )
                );
            },
        });
    }
    if (this.config.recommend.enabledRelated) {
        recommendJs.relatedProducts({
            container: '#relatedProducts',
            recommendClient,
            indexName,
            objectIDs: [currentObjectID],
            maxRecommendations: this.config.recommend.limitRelatedProducts,
            itemComponent({item, createElement, Fragment}) {
                return createElement(
                    'div',
                    null,
                    createElement(
                        'div',
                        {className: "product-details"},
                        createElement(
                            'a',
                            {className: "product-url", href: item.url},
                            createElement('img', {className: "product-img", src: item.image_url}, item.image_url),
                            createElement('p', {className: "product-name"}, item.name)
                        )
                    )
                );
            },
        });
    }
});
});
