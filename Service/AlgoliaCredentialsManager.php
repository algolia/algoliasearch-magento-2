<?php

namespace Algolia\AlgoliaSearch\Service;

use Algolia\AlgoliaSearch\Helper\ConfigHelper;
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
     */
    public function displayErrorMessage(string $class, ?int $storeId = null): void
    {
        $storeInfo = $storeId ? ' for store '. $this->storeManager->getStore($storeId)->getName() : '';
        $errorMessage = '
' . $class . ': Algolia credentials missing' . $storeInfo . '
  => You need to configure your credentials in Stores > Configuration > Algolia Search.';

        if (php_sapi_name() === 'cli') {
            $this->output->writeln($errorMessage);

            return;
        }

        $this->messageManager->addErrorMessage($errorMessage);
    }
}
