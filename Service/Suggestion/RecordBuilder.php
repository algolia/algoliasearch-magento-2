<?php

namespace Algolia\AlgoliaSearch\Service\Suggestion;

use Algolia\AlgoliaSearch\Api\RecordBuilder\RecordBuilderInterface;
use Algolia\AlgoliaSearch\Service\AlgoliaConnector;
use Magento\Framework\DataObject;
use Magento\Framework\Event\ManagerInterface;

class RecordBuilder implements RecordBuilderInterface
{
    public function __construct(
        protected ManagerInterface $eventManager,
    ){}

    public function buildRecord(DataObject $entity): array
    {
        $suggestionObject = [
            AlgoliaConnector::ALGOLIA_API_OBJECT_ID => $entity->getData('query_id'),
            'query'                                 => $entity->getData('query_text'),
            'number_of_results'                     => (int) $entity->getData('num_results'),
            'popularity'                            => (int) $entity->getData('popularity'),
            'updated_at'                            => (int) strtotime($entity->getData('updated_at')),
        ];

        $transport = new DataObject($suggestionObject);
        $this->eventManager->dispatch(
            'algolia_after_create_suggestion_object',
            ['suggestion' => $transport, 'suggestionObject' => $entity]
        );

        return $transport->getData();
    }
}
