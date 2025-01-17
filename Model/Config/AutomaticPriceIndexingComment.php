<?php

namespace Algolia\AlgoliaSearch\Model\Config;

use Magento\Config\Model\Config\CommentInterface;
use Magento\Framework\UrlInterface;

class AutomaticPriceIndexingComment implements CommentInterface
{
    /**
     * @var UrlInterface
     */
    protected $urlInterface;

    public function __construct(
        UrlInterface $urlInterface
    ) {
        $this->urlInterface = $urlInterface;
    }

    public function getCommentText($elementValue)
    {
        $url = $this->urlInterface->getUrl('https://www.algolia.com/doc/integration/magento-2/how-it-works/indexing-queue/#configure-the-queue');

        $comment = array();
        $comment[] = 'Algolia relies on the core Magento Product Price index when serializing product data. If the price index is not up to date, Algolia will not be able to accurately determine what should be included in the search index.';
        $comment[] = 'If you are experiencing problems with products not syncing to Algolia due to this issue, enabling this setting will allow Algolia to automatically update the price index as needed.';
        $comment[] = 'NOTE: This can introduce a marginal amount of overhead to the indexing process so only enable if necessary. Be sure to <a href="' . $url . '" target="_blank">optimize the indexing queue</a> based on the impact of this operation.';
        return implode('<br><br>', $comment);
    }
}
