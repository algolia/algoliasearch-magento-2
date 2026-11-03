<?php

namespace Algolia\AlgoliaSearch\Ui\Component\Listing\Column\Store;

use Magento\Store\Ui\Component\Listing\Column\Store\Options as StoreOptions;

class Options extends StoreOptions
{
    /**
     * All Store Views value. Only load-bearing in combination with
     * <captionValue>0</captionValue> in algolia_algoliasearch_indexing_queue_listing.xml:
     * the filter renders as the classic native select, whose parseOptions() strips the
     * option matching captionValue and uses its label as the empty-state caption, so this
     * option never renders as selectable and filters[store_id]=0 is never sent. Without
     * that pairing the '0' option becomes selectable and yields an empty grid, since no
     * queue row has store_id 0. All jobs are expected to be store-scoped.
     */
    const ALL_STORE_VIEWS = '0';

    /**
     * Get options
     *
     * @return array
     */
    public function toOptionArray()
    {
        if ($this->options !== null) {
            return $this->options;
        }

        $this->currentOptions['All Store Views']['label'] = __('All Store Views');
        $this->currentOptions['All Store Views']['value'] = self::ALL_STORE_VIEWS;

        $this->generateCurrentOptions();

        $this->options = array_values($this->currentOptions);

        return $this->options;
    }
}
