define([], function () {
    return {
        getItemHtml: function (item, html, addTocart) {
            let correctFKey = getCookie('form_key');
            let action = algoliaConfig.recommend.addToCartParams.action + 'product/' + item.objectID + '/';
            if(correctFKey != "" && algoliaConfig.recommend.addToCartParams.formKey != correctFKey) {
                algoliaConfig.recommend.addToCartParams.formKey = correctFKey;
            }
            this.defaultIndexName = algoliaConfig.indexName + '_products';
            return  html`<div class="product-details">
                <a class="recommend-item product-url" href="${item.url}" data-objectid=${item.objectID} data-position=${item.position}  data-index=${this.defaultIndexName}>
                    <img class="product-img" src="${item.image_url}" alt="${item.name}"/>
                    <p class="product-name">${item.name}</p>
                    ${addTocart && html`
                        <form class="addTocartForm" action="${action}" method="post" data-role="tocart-form">
                            <input type="hidden" name="form_key" value="${algoliaConfig.recommend.addToCartParams.formKey}" />
                            <input type="hidden" name="unec" value="${AlgoliaBase64.mageEncode(action)}"/>
                            <input type="hidden" name="product" value="${item.objectID}" />
                            <button type="submit" class="action tocart primary">
                                <span>${algoliaConfig.translations.addToCart}</span>
                            </button>
                        </form>`
                    }
                </a>
            </div>`;
        },
        getHeaderHtml: function (html,title) {
            return html`<h3 class="auc-Recommend-title">${title}</h3>`;
        }
    };
});
