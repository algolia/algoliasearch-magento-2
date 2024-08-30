<?php

namespace Algolia\AlgoliaSearch\Observer\Insights;

use Algolia\AlgoliaSearch\Helper\InsightsHelper;
use Algolia\AlgoliaSearch\Helper\Logger;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Stdlib\Cookie\CookieMetadataFactory;
use Magento\Framework\Stdlib\CookieManagerInterface;

class CustomerLogout implements ObserverInterface
{
    public const UNSET_AUTHENTICATION_USER_TOKEN_COOKIE_NAME = "unset_authentication_token";

    /**
     * @param CookieManagerInterface $cookieManager
     * @param CookieMetadataFactory $cookieMetadataFactory
     */
    public function __construct(
        private readonly CookieManagerInterface $cookieManager,
        private readonly CookieMetadataFactory  $cookieMetadataFactory,
        private Logger                          $logger
    ) {

    }

    /**
     * Check and clear session data if persistent session expired
     *
     * @param \Magento\Framework\Event\Observer $observer
     * @return void
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function execute(\Magento\Framework\Event\Observer $observer): void
    {
        if ($this->cookieManager->getCookie(InsightsHelper::ALGOLIA_CUSTOMER_USER_TOKEN_COOKIE_NAME)) {
            //TODO: Verify whether this additional cookie is needed
            $metaDataUnset = $this->cookieMetadataFactory->createPublicCookieMetadata()
                ->setDurationOneYear()
                ->setPath('/')
                ->setHttpOnly(false)
                ->setSecure(false);
            $metadata = $this->cookieMetadataFactory->createCookieMetadata();
            $metadata->setPath('/');
            try {
                $this->cookieManager->setPublicCookie(self::UNSET_AUTHENTICATION_USER_TOKEN_COOKIE_NAME, 1, $metaDataUnset);
                $this->cookieManager->deleteCookie(InsightsHelper::ALGOLIA_CUSTOMER_USER_TOKEN_COOKIE_NAME, $metadata);
                $this->cookieManager->deleteCookie(InsightsHelper::ALGOLIA_ANON_USER_TOKEN_COOKIE_NAME, $metadata);
            } catch (LocalizedException $e) {
                $this->logger->error("Error writing Algolia customer cookies: " . $e->getMessage());
            }
        }
    }
}
