<?php

namespace Algolia\AlgoliaSearch\Block;

use Algolia\AlgoliaSearch\Helper\AlgoliaHelper;
use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Helper\Configuration\PersonalizationHelper;
use Algolia\AlgoliaSearch\Helper\Data as CoreHelper;
use Algolia\AlgoliaSearch\Helper\Entity\CategoryHelper;
use Algolia\AlgoliaSearch\Helper\Entity\ProductHelper;
use Algolia\AlgoliaSearch\Helper\Entity\SuggestionHelper;
use Algolia\AlgoliaSearch\Helper\LandingPageHelper;
use Algolia\AlgoliaSearch\Registry\CurrentCategory;
use Algolia\AlgoliaSearch\Registry\CurrentProduct;
use Algolia\AlgoliaSearch\Service\Product\SortingTransformer;
use Magento\Catalog\Api\Data\CategoryInterface;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Customer\Model\Context as CustomerContext;
use Magento\Framework\App\ActionInterface;
use Magento\Framework\App\Http\Context as HttpContext;
use Magento\Framework\Currency\Exception\CurrencyException;
use Magento\Framework\Data\CollectionDataSourceInterface;
use Magento\Framework\Data\Form\FormKey;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Locale\Currency;
use Magento\Framework\Locale\Format;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Framework\Url\Helper\Data as UrlHelper;
use Magento\Framework\View\Element\Template;
use Magento\Sales\Model\Order;
use Magento\Search\Helper\Data as CatalogSearchHelper;
use Magento\Store\Api\Data\StoreInterface;

class Algolia extends Template implements CollectionDataSourceInterface
{
    protected ?string $priceKey = null;

    public function __construct(
        protected ConfigHelper          $config,
        protected CatalogSearchHelper   $catalogSearchHelper,
        protected ProductHelper         $productHelper,
        protected Currency              $currency,
        protected Format                $format,
        protected CurrentProduct        $currentProduct,
        protected AlgoliaHelper         $algoliaHelper,
        protected UrlHelper             $urlHelper,
        protected FormKey               $formKey,
        protected HttpContext           $httpContext,
        protected CoreHelper            $coreHelper,
        protected CategoryHelper        $categoryHelper,
        protected SuggestionHelper      $suggestionHelper,
        protected LandingPageHelper     $landingPageHelper,
        protected PersonalizationHelper $personalizationHelper,
        protected CheckoutSession       $checkoutSession,
        protected DateTime              $date,
        protected CurrentCategory       $currentCategory,
        protected SortingTransformer    $sortingTransformer,
        Template\Context                $context,
        array                           $data = []
    )
    {
        parent::__construct($context, $data);
    }

    /**
     * @throws NoSuchEntityException
     */
    public function getStore(): StoreInterface
    {
        return $this->_storeManager->getStore();
    }

    public function getConfigHelper(): ConfigHelper
    {
        return $this->config;
    }

    public function getCoreHelper(): CoreHelper
    {
        return $this->coreHelper;
    }

    public function getProductHelper(): ProductHelper
    {
        return $this->productHelper;
    }

    public function getCategoryHelper(): CategoryHelper
    {
        return $this->categoryHelper;
    }

    public function getSuggestionHelper(): SuggestionHelper
    {
        return $this->suggestionHelper;
    }

    public function getCatalogSearchHelper(): CatalogSearchHelper
    {
        return $this->catalogSearchHelper;
    }

    public function getAlgoliaHelper(): AlgoliaHelper
    {
        return $this->algoliaHelper;
    }

    public function getPersonalizationHelper(): PersonalizationHelper
    {
        return $this->personalizationHelper;
    }

    /**
     * @throws CurrencyException|NoSuchEntityException
     */
    public function getCurrencySymbol(): ?string
    {
        return $this->currency->getCurrency($this->getCurrencyCode())->getSymbol();
    }

    /**
     * @throws NoSuchEntityException
     */
    public function getCurrencyCode(): ?string
    {
        return $this->getStore()->getCurrentCurrencyCode();
    }

    public function getPriceFormat(): array
    {
        return $this->format->getPriceFormat();
    }

    public function getGroupId()
    {
        return $this->httpContext->getValue(CustomerContext::CONTEXT_GROUP);
    }

    /**
     * @throws NoSuchEntityException
     */
    public function getPriceKey(): string
    {
        if ($this->priceKey === null) {
            $currencyCode = $this->getCurrencyCode();

            $this->priceKey = '.' . $currencyCode . '.default';

            if ($this->config->isCustomerGroupsEnabled($this->getStore()->getStoreId())) {
                $groupId = $this->getGroupId();
                $this->priceKey = '.' . $currencyCode . '.group_' . $groupId;
            }
        }

        return $this->priceKey;
    }

    /**
     * @throws NoSuchEntityException
     */
    public function getStoreId(): int
    {
        return $this->getStore()->getStoreId();
    }

    public function getCurrentCategory(): CategoryInterface
    {
        return $this->currentCategory->get();
    }

    public function getCurrentProduct(): ProductInterface
    {
        return $this->currentProduct->get();
    }

    public function getLastOrder(): Order
    {
        return $this->checkoutSession->getLastRealOrder();
    }

    /**
     * @return array<string, string>
     * @throws LocalizedException
     */
    public function getAddToCartParams(): array
    {
        return [
            'action' => $this->_urlBuilder->getUrl('checkout/cart/add', []),
            'formKey' => $this->formKey->getFormKey(),
            'redirectUrlParam' => ActionInterface::PARAM_NAME_URL_ENCODED
        ];
    }

    public function getTimestamp(): int|false
    {
        return $this->date->gmtTimestamp('today midnight');
    }

    protected function getCurrentLandingPage(): LandingPage|null|false
    {
        $landingPageId = $this->getRequest()->getParam('landing_page_id');
        if (!$landingPageId) {
            return null;
        }
        return $this->landingPageHelper->getLandingPage($landingPageId);
    }
}
