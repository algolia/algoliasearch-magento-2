<?php
use Magento\TestFramework\Helper\Bootstrap;
use Magento\Framework\App\Config\MutableScopeConfigInterface;

$objectManager = Bootstrap::getObjectManager();
$scopeConfig = $objectManager->get(MutableScopeConfigInterface::class);

// Set complex configuration value
$scopeConfig->setValue(
    'algoliasearch_advanced/advanced/backend_rendering_allowed_user_agents',
    join("\n", ["Googlebot", "Bingbot", "Foobot"]),
    \Magento\Store\Model\ScopeInterface::SCOPE_STORE
);
