<?php

namespace Algolia\AlgoliaSearch\Model\Source;

use Algolia\AlgoliaSearch\Exceptions\AlgoliaException;
use Algolia\AlgoliaSearch\Service\CompositionsFetcher;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Data\OptionSourceInterface;

class Compositions implements OptionSourceInterface
{
    public function __construct(
        protected CompositionsFetcher $compositionsFetcher,
        protected RequestInterface $request
    ){}

    /**
     * @throws AlgoliaException
     */
    public function toOptionArray()
    {
        $storeId = $this->request->getParam('store') !== null ?
            $this->request->getParam('store') :
            null;

        $compositionOptions = [
            ['value' => '', 'label' => '---']
        ];

        $fetchedCompositions = $this->compositionsFetcher->fetchCompositions($storeId);

        if ($fetchedCompositions['nbHits'] === 0) {
            return $compositionOptions;
        }

        return $this->formatCompositionOptions($compositionOptions, $fetchedCompositions);
    }

    protected function formatCompositionOptions(array $compositionOptions, array $fetchedCompositions): array
    {
        foreach ($fetchedCompositions['items'] as $fetchedComposition) {
            $compositionOptions[] = [
                'value' => $fetchedComposition['objectID'],
                'label' => __(
                    "%1 (Main index: %2)",
                    [
                        $fetchedComposition['name'],
                        CompositionsFetcher::extractIndexName($fetchedComposition['objectID'])
                    ]
                )
            ];
        }

        return $compositionOptions;
    }
}
