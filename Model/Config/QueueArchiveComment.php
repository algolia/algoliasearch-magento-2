<?php

namespace Algolia\AlgoliaSearch\Model\Config;

use Magento\Config\Model\Config\CommentInterface;
use Magento\Framework\UrlInterface;

class QueueArchiveComment implements CommentInterface
{
    public function __construct(
        protected UrlInterface $urlInterface
    ) { }

    public function getCommentText($elementValue)
    {
        $url = $this->urlInterface->getUrl('algolia_algoliasearch/queuearchive/index');

        return 'Useful for debugging. Algolia archives failed jobs by default. Enable this setting to archive all jobs that are processed by the indexing queue and to obtain and preserve the stack trace for jobs created. <br /> Access the <a href="' . $url . '">Queue Archives</a>.';
    }
}
