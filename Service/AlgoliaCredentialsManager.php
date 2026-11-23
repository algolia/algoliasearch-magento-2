<?php

namespace Algolia\AlgoliaSearch\Service;

use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Message\ManagerInterface;
use Magento\Store\Model\StoreManagerInterface;
use Symfony\Component\Console\Output\ConsoleOutput;

class AlgoliaCredentialsManager
{
    public function __construct(
        protected ConfigHelper $configHelper,
        protected ManagerInterface $messageManager,
        protected ConsoleOutput $output,
        protected StoreManagerInterface $storeManager
    )
    {}

    /**
     * Validates the credentials set on a given store level
     *
     */
    public function checkCredentials(?int $storeId = null): bool
    {
        return $this->configHelper->getApplicationID($storeId) && $this->configHelper->getAPIKey($storeId);
    }

    /**
     * Validates the credentials set on a given store level with an additional check on the search only API Key
     *
     */
    public function checkCredentialsWithSearchOnlyAPIKey(?int $storeId = null): bool
    {
        return $this->checkCredentials($storeId) && $this->configHelper->getSearchOnlyAPIKey($storeId);
    }

    /**
     * The credentials-missing message, shared by every transport that reports it: this class
     * displays it, callers that funnel output through their own reporter show it verbatim.
     *
     * @param string|null $storeName Store display name
     */
    public function getErrorMessage(string $class, ?string $storeName = null): string
    {
        return $class . ': Algolia credentials missing' . ($storeName !== null ? ' for store ' . $storeName : '') . '
  => You need to configure your credentials in Stores > Configuration > Algolia Search.';
    }

    /**
     * Displays an error message in the console or in the admin
     *
     */
    public function displayErrorMessage(string $class, ?int $storeId = null): void
    {
        try {
            $errorMessage = $this->getErrorMessage(
                $class,
                $storeId ? $this->storeManager->getStore($storeId)->getName() : null
            );

            if (php_sapi_name() === 'cli') {
                $this->output->writeln($errorMessage);

                return;
            }

            $this->messageManager->addErrorMessage($errorMessage);
        } catch (NoSuchEntityException $exception) {
            $this->messageManager->addErrorMessage(__('Unable to locate store details: %1', $exception->getMessage()));
        }
    }

    /**
     * Checks if multiple application IDs are configured
     *
     */
    public function hasMultipleApplicationIDs(): bool
    {
        $applications = [];

        foreach ($this->storeManager->getStores() as $store) {
            $storeId = $store->getId();
            if ($this->checkCredentials($storeId)) {
                if (!isset($applications[$this->configHelper->getApplicationID($storeId)])) {
                    $applications[$this->configHelper->getApplicationID($storeId)] = true;
                }

                if (count($applications) > 1) {
                    return true;
                }
            }
        }

        return false;
    }
}
