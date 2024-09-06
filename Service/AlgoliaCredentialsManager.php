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
     * @param int|null $storeId
     * @return bool
     */
    public function checkCredentials(int $storeId = null): bool
    {
        return $this->configHelper->getApplicationID($storeId) && $this->configHelper->getAPIKey($storeId);
    }

    /**
     * Validates the credentials set on a given store level with an additional check on the search only API Key
     *
     * @param int|null $storeId
     * @return bool
     */
    public function checkCredentialsWithSearchOnlyAPIKey(int $storeId = null): bool
    {
        return $this->checkCredentials($storeId) && $this->configHelper->getSearchOnlyAPIKey($storeId);
    }

    /**
     * Displays an error message in the console or in the admin
     *
     * @param string $class
     * @param int|null $storeId
     * @return void
     * @throws NoSuchEntityException
     */
    public function displayErrorMessage(string $class, ?int $storeId = null): void
    {
        try {
            $storeInfo = $storeId ? ' for store '. $this->storeManager->getStore($storeId)->getName() : '';
            $errorMessage = '
' . $class . ': Algolia credentials missing' . $storeInfo . '
  => You need to configure your credentials in Stores > Configuration > Algolia Search.';

            if (php_sapi_name() === 'cli') {
                $this->output->writeln($errorMessage);

                return;
            }

            $this->messageManager->addErrorMessage($errorMessage);
        } catch (NoSuchEntityException $exception) {
            $this->messageManager->addErrorMessage(__("Unable to locate store details: %1", $exception->getMessage()));
        }
    }

    /**
     * Checks if multiple application IDs are configured
     *
     * @return bool
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
