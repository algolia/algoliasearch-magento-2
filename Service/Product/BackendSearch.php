<?php

namespace Algolia\AlgoliaSearch\Service\Product;

use Algolia\AlgoliaSearch\Api\Data\IndexOptionsInterface;
use Algolia\AlgoliaSearch\Exceptions\AlgoliaException;
use Algolia\AlgoliaSearch\Helper\AlgoliaHelper;
use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Helper\Entity\ProductHelper;
use Algolia\AlgoliaSearch\Model\IndexOptions;
use Algolia\AlgoliaSearch\Service\AlgoliaConnector;
use Magento\Framework\Exception\NoSuchEntityException;

class BackendSearch
{
    public function __construct(
        protected ConfigHelper  $configHelper,
        protected ProductHelper $productHelper,
        protected AlgoliaHelper $algoliaHelper,
    ){}

    /**
     * @param string $query
     * @param int $storeId
     * @param array|null $searchParams
     * @param string|null $targetedIndex
     * @return array
     * @throws AlgoliaException|NoSuchEntityException
     * @internal This method is currently unstable and should not be used. It may be revisited or fixed in a future version.
     *
     */
    public function getSearchResult(string $query, int $storeId, ?array $searchParams = null, ?string $targetedIndex = null): array
    {
        $indexOptions = $targetedIndex !== null ?
            new IndexOptions([IndexOptionsInterface::ENFORCED_INDEX_NAME, $targetedIndex]) :
            new IndexOptions([
                IndexOptionsInterface::INDEX_SUFFIX => ProductHelper::INDEX_NAME_SUFFIX,
                IndexOptionsInterface::STORE_ID => $storeId
           ]);

        $numberOfResults = 1000;
        if ($this->configHelper->isInstantEnabled()) {
            $numberOfResults = min($this->configHelper->getNumberOfProductResults($storeId), 1000);
        }

        $facetsToRetrieve = [];
        foreach ($this->configHelper->getFacets($storeId) as $facet) {
            $facetsToRetrieve[] = $facet['attribute'];
        }

        $params = [
            'hitsPerPage'            => $numberOfResults, // retrieve all the hits (hard limit is 1000)
            'attributesToRetrieve'   => AlgoliaConnector::ALGOLIA_API_OBJECT_ID,
            'attributesToHighlight'  => '',
            'attributesToSnippet'    => '',
            'numericFilters'         => ['visibility_search=1'],
            'removeWordsIfNoResults' => $this->configHelper->getRemoveWordsIfNoResult($storeId),
            'analyticsTags'          => 'backend-search',
            'facets'                 => $facetsToRetrieve,
            'maxValuesPerFacet'      => 100,
        ];

        if (is_array($searchParams)) {
            $params = array_merge($params, $searchParams);
        }

        $response = $this->algoliaHelper->query($indexOptions, $query, $params);
        $answer = reset($response['results']);

        $data = [];

        foreach ($answer['hits'] as $i => $hit) {
            $productId = $hit[AlgoliaConnector::ALGOLIA_API_OBJECT_ID];

            if ($productId) {
                $data[$productId] = [
                    'entity_id' => $productId,
                    'score' => $numberOfResults - $i,
                ];
            }
        }

        $facetsFromAnswer = $answer['facets'] ?? [];

        return [$data, $answer['nbHits'], $facetsFromAnswer];
    }
}
