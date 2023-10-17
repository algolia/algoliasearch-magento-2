<?php

namespace Algolia\AlgoliaSearch\Model\Indexer;

use Algolia\AlgoliaSearch\Helper\AlgoliaHelper;
use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Helper\Data;
use Algolia\AlgoliaSearch\Helper\Entity\PageHelper;
use Algolia\AlgoliaSearch\Model\Queue;
use Magento\Framework\Message\ManagerInterface;
use Magento\Store\Model\StoreManagerInterface;
use Symfony\Component\Console\Output\ConsoleOutput;

class Page implements \Magento\Framework\Indexer\ActionInterface, \Magento\Framework\Mview\ActionInterface
{
    /**
     * @var Data
     */
    protected $fullAction;
    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;
    /**
     * @var PageHelper
     */
    protected $pageHelper;
    /**
     * @var AlgoliaHelper
     */
    protected $algoliaHelper;
    /**
     * @var Queue
     */
    protected $queue;
    /**
     * @var ConfigHelper
     */
    protected $configHelper;
    /**
     * @var ManagerInterface
     */
    protected $messageManager;
    /**
     * @var ConsoleOutput
     */
    protected $output;

    /**
     * @param StoreManagerInterface $storeManager
     * @param PageHelper $pageHelper
     * @param Data $helper
     * @param AlgoliaHelper $algoliaHelper
     * @param Queue $queue
     * @param ConfigHelper $configHelper
     * @param ManagerInterface $messageManager
     * @param ConsoleOutput $output
     */
    public function __construct(
        StoreManagerInterface $storeManager,
        PageHelper $pageHelper,
        Data $helper,
        AlgoliaHelper $algoliaHelper,
        Queue $queue,
        ConfigHelper $configHelper,
        ManagerInterface $messageManager,
        ConsoleOutput $output
    ) {
        $this->fullAction = $helper;
        $this->storeManager = $storeManager;
        $this->pageHelper = $pageHelper;
        $this->algoliaHelper = $algoliaHelper;
        $this->queue = $queue;
        $this->configHelper = $configHelper;
        $this->messageManager = $messageManager;
        $this->output = $output;
    }

    /**
     * @param $ids
     * @return void
     */
    public function execute($ids)
    {
        if (!$this->configHelper->getApplicationID()
            || !$this->configHelper->getAPIKey()
            || !$this->configHelper->getSearchOnlyAPIKey()) {
            $errorMessage = 'Algolia reindexing failed:
                You need to configure your Algolia credentials in Stores > Configuration > Algolia Search.';

            if (php_sapi_name() === 'cli') {
                $this->output->writeln($errorMessage);

                return;
            }

            $this->messageManager->addErrorMessage($errorMessage);

            return;
        }

        $storeIds = $this->pageHelper->getStores();

        foreach ($storeIds as $storeId) {
            if ($this->fullAction->isIndexingEnabled($storeId) === false) {
                continue;
            }

            if ($this->isPagesInAdditionalSections($storeId)) {
                $data = ['storeId' => $storeId];
                if (is_array($ids) && count($ids) > 0) {
                    $data['pageIds'] = $ids;
                }

                $this->queue->addToQueue(
                    $this->fullAction,
                    'rebuildStorePageIndex',
                    $data,
                    is_array($ids) ? count($ids) : 1
                );
            }
        }
    }

    /**
     * @return void
     */
    public function executeFull()
    {
        $this->execute(null);
    }

    /**
     * @param array $ids
     * @return void
     */
    public function executeList(array $ids)
    {
        $this->execute($ids);
    }

    /**
     * @param $id
     * @return void
     */
    public function executeRow($id)
    {
        $this->execute([$id]);
    }

    /**
     * @param $storeId
     * @return bool
     */
    protected function isPagesInAdditionalSections($storeId)
    {
        $sections = $this->configHelper->getAutocompleteSections($storeId);
        foreach ($sections as $section) {
            if ($section['name'] === 'pages') {
                return true;
            }
        }

        return false;
    }
}
