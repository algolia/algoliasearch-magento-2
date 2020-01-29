<?php

namespace Algolia\AlgoliaSearch\Model\Backend;

use Algolia\AlgoliaSearch\Helper\AlgoliaHelper;
use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Helper\Entity\ProductHelper;
use Magento\Framework\App\Config\Value;
use Magento\Framework\Exception\LocalizedException;

class EnableClickAnalytics extends Value
{
    private $algoliaHelper;
    private $productHelper;
    private $configHelper;

    public function __construct(
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\App\Config\ScopeConfigInterface $config,
        \Magento\Framework\App\Cache\TypeListInterface $cacheTypeList,
        AlgoliaHelper $algoliaHelper,
        ConfigHelper $configHelper,
        ProductHelper $productHelper,
        \Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
        \Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        parent::__construct($context, $registry, $config, $cacheTypeList, $resource, $resourceCollection, $data);

        $this->algoliaHelper = $algoliaHelper;
        $this->productHelper = $productHelper;
        $this->configHelper = $configHelper;
    }

    public function beforeSave()
    {
        $value = trim($this->getData('value'));

        if ($value !== '1') {
            return parent::beforeSave();
        }

        $ch = curl_init();

        $headers = [
            'X-Algolia-Api-Key: ' . $this->configHelper->getAPIKey(),
            'X-Algolia-Application-Id: ' . $this->configHelper->getApplicationID(),
            'Content-Type: application/x-www-form-urlencoded',
        ];

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $postFields = json_encode([
            'timestamp' => time(),
            'queryID' => 'a',
            'objectID' => 'non_existent_object_id',
            'position' => 1,
        ]);

        curl_setopt($ch, CURLOPT_URL, 'https://insights.algolia.io/1/searches/click');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
        curl_setopt($ch, CURLOPT_POST, 1);

        $result = curl_exec($ch);
        curl_close($ch);

        if ($result) {
            $result = json_decode($result);
            if ($result->status === 401 && $result->message === 'Feature not available') {
                throw new LocalizedException(
                    __('Click & Conversion analytics are not supported on your current plan. Please refer to <a target="_blank" href="https://www.algolia.com/pricing/">Algolia\'s pricing page</a> for more details.')
                );
            }
        }

        return parent::beforeSave();
    }
}
