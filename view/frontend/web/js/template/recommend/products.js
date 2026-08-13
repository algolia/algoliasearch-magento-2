define(['algoliaCommon', 'algoliaBase64'], function (algoliaCommon, algoliaBase64) {
    return {
        getItemHtml: function ({item, html, addToCart}) {
            let correctFKey = algoliaCommon.getCookie('form_key');
            let action = algoliaConfig.recommend.addToCartParams.action + 'product/' + item.objectID + '/';
            if(correctFKey != "" && algoliaConfig.recommend.addToCartParams.formKey != correctFKey) {
                algoliaConfig.recommend.addToCartParams.formKey = correctFKey;
            }
            return  html`<div class="product-details">
                <a class="recommend-item product-url" 
                   href="${item.url}" 
                   data-objectid=${item.objectID} 
                   data-position=${item.position} 
                   data-index=${algoliaConfig.indexName + '_products'}>
                    <img class="product-img" src="${item.image_url}" alt="${item.name}"/>
                    <p class="product-name">${item.name}</p>
                    ${addToCart && html`
                        <form class="addTocartForm" action="${action}" method="post" data-role="tocart-form">
                            <input type="hidden" name="form_key" value="${algoliaConfig.recommend.addToCartParams.formKey}" />
                            <input type="hidden" name="unec" value="${algoliaBase64.mageEncode(action)}"/>
                            <input type="hidden" name="product" value="${item.objectID}" />
                            <button type="submit" class="action tocart primary">
                                <span>${algoliaConfig.translations.addToCart}</span>
                            </button>
                        </form>`
                    }
                </a>
            </div>`;
        },
        getHeaderHtml: function ({html, title}) {
            return html`<h3 class="auc-Recommend-title">${title}</h3>`;
        },
        getNoResultHtml: function ({html}) {
            return '';
        }
    };
});
