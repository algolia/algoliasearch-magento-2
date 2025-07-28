<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

return [
    'db-host' => 'db',
    'db-user' => 'magento',
    'db-password' => 'magento',
    'db-name' => 'magento_integration_tests',
    'db-prefix' => '',
    'backend-frontname' => 'backend',
    'search-engine' => 'opensearch',
    'opensearch-host' => 'opensearch',
    'admin-user' => \Magento\TestFramework\Bootstrap::ADMIN_NAME,
    'admin-password' => \Magento\TestFramework\Bootstrap::ADMIN_PASSWORD,
    'admin-email' => \Magento\TestFramework\Bootstrap::ADMIN_EMAIL,
    'admin-firstname' => \Magento\TestFramework\Bootstrap::ADMIN_FIRSTNAME,
    'admin-lastname' => \Magento\TestFramework\Bootstrap::ADMIN_LASTNAME,
    'amqp-host' => 'rabbitmq',
    'amqp-port' => '5672',
    'amqp-user' => 'magento',
    'amqp-password' => 'magento',
    'consumers-wait-for-messages' => '0',
    'session-save' => 'redis',
    'session-save-redis-host' => 'redis',
    'session-save-redis-port' => 6379,
    'session-save-redis-db' => 5,
    'session-save-redis-max-concurrency' => 20,
    'cache-backend' => 'redis',
    'cache-backend-redis-server' => 'redis',
    'cache-backend-redis-db' => 4,
    'cache-backend-redis-port' => 6379,
    'page-cache' => 'redis',
    'page-cache-redis-server' => 'redis',
    'page-cache-redis-db' => 3,
    'page-cache-redis-port' => 6379
];