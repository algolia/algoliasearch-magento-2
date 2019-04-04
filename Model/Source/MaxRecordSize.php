<?php

namespace Algolia\AlgoliaSearch\Model\Source;

use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Helper\ProxyHelper;

class MaxRecordSize implements \Magento\Framework\Option\ArrayInterface
{
    /** @var ConfigHelper */
    private $configHelper;

    /** @var ProxyHelper */
    private $proxyHelper;

    public function __construct(
        ConfigHelper $configHelper,
        ProxyHelper $proxyHelper
    ) {
        $this->configHelper = $configHelper;
        $this->proxyHelper = $proxyHelper;
    }

    /**
     * Legacy default is 20000
     *
     * @return array
     */
    public function toOptionArray()
    {
        $options[] = $this->configHelper->getDefaultMaxRecordSize();
        $clientData = $this->proxyHelper->getClientConfigurationData();

        if ($clientData && isset($clientData['max_record_size'])) {
            if (!in_array($clientData['max_record_size'], $options)) {
                $options[] = $clientData['max_record_size'];
            }
        }

        rsort($options);

        $formattedOptions = [];
        foreach ($options as $option) {
            $formattedOptions[] = [
                'value' => $option,
                'label' => $option,
            ];
        }

        return $formattedOptions;
    }
}
