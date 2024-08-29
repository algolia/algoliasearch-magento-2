<?php

namespace Algolia\AlgoliaSearch\Service;

use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Magento\Framework\Message\ManagerInterface;
use Symfony\Component\Console\Output\ConsoleOutput;

class AlgoliaCredentialsManager
{
    public function __construct(
        protected ConfigHelper $configHelper,
        protected ManagerInterface $messageManager,
        protected ConsoleOutput $output
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
     * @param string $errorMessage
     * @return void
     */
    public function displayErrorMessage(string $errorMessage): void
    {
        if (php_sapi_name() === 'cli') {
            $this->output->writeln($errorMessage);

            return;
        }

        $this->messageManager->addErrorMessage($errorMessage);
    }
}
