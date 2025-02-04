<?php

namespace Algolia\AlgoliaSearch\Service\AdditionalSection;

use Algolia\AlgoliaSearch\Api\RecordBuilder\RecordBuilderInterface;
use Algolia\AlgoliaSearch\Service\AlgoliaConnector;
use Magento\Framework\DataObject;
use Magento\Framework\Event\ManagerInterface;

class RecordBuilder implements RecordBuilderInterface
{
    public function __construct(
        protected ManagerInterface $eventManager,
    ){}

    /**
     * Builds a Section record
     *
     * @param DataObject $entity
     * @return array
     */
    public function buildRecord(DataObject $entity): array
    {
        $record = [
            AlgoliaConnector::ALGOLIA_API_OBJECT_ID => $entity->getData('value'),
            'value'                                 => $entity->getData('value'),
        ];

        $transport = new DataObject($record);

        /** Removed legacy algolia_additional_section_item_index_before event on 3.15.0 */
        $this->eventManager->dispatch(
            'algolia_additional_section_items_before_index',
            [
                'section' => $entity->getData('section'),
                'record' => $transport,
                'store_id' => $entity->getData('store_id')
            ]
        );

        return $transport->getData();
    }
}
