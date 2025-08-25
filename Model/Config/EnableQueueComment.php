<?php

namespace Algolia\AlgoliaSearch\Model\Config;

use Magento\Config\Model\Config\CommentInterface;
use Magento\Framework\UrlInterface;

class EnableQueueComment implements CommentInterface
{
    public function __construct(
        protected UrlInterface $urlInterface
    ) { }

    public function getCommentText($elementValue)
    {
        $url = $this->urlInterface->getUrl('algolia_algoliasearch/queue/index');

        return 'If enabled, all indexing operations (add, remove & update operations) will be done asynchronously using the cron mechanism.<br><br/> Go to <a href="' . $url . '">Indexing Queue</a>. <br/>
<span class="algolia-config-warning">&#9888;</span> Enabling this option is recommended in production or if your store has a lot of products. ';
    }
}
