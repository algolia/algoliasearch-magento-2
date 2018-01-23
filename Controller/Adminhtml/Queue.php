<?php

namespace Algolia\AlgoliaSearch\Controller\Adminhtml;

use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Controller\Result\JsonFactory;

abstract class Queue extends Action
{
    /**
     * Array of actions which can be processed without secret key validation
     *
     * @var string[]
     */
    protected $_publicActions = ['info', 'clear'];

    protected $configHelper;

    protected $resultJsonFactory;

    protected $tableName;
    protected $db;

    public function __construct(
        Context $context,
        ConfigHelper $configHelper,
        JsonFactory $resultJsonFactory,
        ResourceConnection $resourceConnection
    ) {
        parent::__construct($context);

        $this->configHelper = $configHelper;

        $this->resultJsonFactory = $resultJsonFactory;

        $this->tableName = $resourceConnection->getTableName('algoliasearch_queue');
        $this->db = $resourceConnection->getConnection('core_write');
    }
}
