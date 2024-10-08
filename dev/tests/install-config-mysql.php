<?php

return [
    'db-host' => '127.0.0.1',
    'db-user' => 'magento2',
    'db-password' => 'P4ssw0rd',
    'db-name' => 'magento2',
    'db-prefix' => '',
    'backend-frontname' => 'backend',
    'search-engine' => 'opensearch',
    'opensearch-host' => 'opensearch',
    'admin-user' => \Magento\TestFramework\Bootstrap::ADMIN_NAME,
    'admin-password' => \Magento\TestFramework\Bootstrap::ADMIN_PASSWORD,
    'admin-email' => \Magento\TestFramework\Bootstrap::ADMIN_EMAIL,
    'admin-firstname' => \Magento\TestFramework\Bootstrap::ADMIN_FIRSTNAME,
    'admin-lastname' => \Magento\TestFramework\Bootstrap::ADMIN_LASTNAME,
    'disable-modules' => 'Magento_WebapiSecurity,Magento_Amqp,Magento_MysqlMq,Magento_MessageQueue',
    'amqp-host' => 'rabbitmq',
    'amqp-port' => '5672',
    'amqp-user' => 'magento',
    'amqp-password' => 'magento',
    'consumers-wait-for-messages' => '0',
];
