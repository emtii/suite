<?php

use Spryker\Shared\Application\ApplicationConstants;
use Spryker\Shared\Collector\CollectorConstants;
use Spryker\Shared\Mail\MailConstants;
use Spryker\Shared\Propel\PropelConstants;
use Spryker\Shared\Queue\QueueConstants;
use Spryker\Shared\RabbitMq\RabbitMqConstants;
use Spryker\Shared\Search\SearchConstants;

// ---------- Propel
$config[PropelConstants::ZED_DB_DATABASE] = 'DE_development_zed';

// ---------- Email
$config[MailConstants::MAILCATCHER_GUI] = sprintf('http://%s:1080', $config[ApplicationConstants::HOST_ZED]);

// ---------- Elasticsearch
$ELASTICA_INDEX_NAME = 'at_search';
$config[SearchConstants::ELASTICA_PARAMETER__INDEX_NAME] = $ELASTICA_INDEX_NAME;
$config[CollectorConstants::ELASTICA_PARAMETER__INDEX_NAME] = $ELASTICA_INDEX_NAME;

// ---------- Queue
$config[QueueConstants::QUEUE_WORKER_INTERVAL_MILLISECONDS] = 1000;
$config[QueueConstants::QUEUE_WORKER_LOG_ACTIVE] = false;
$config[QueueConstants::QUEUE_WORKER_OUTPUT_FILE_NAME] = 'data/AT/logs/ZED/queue.out';

// ---------- RabbitMQ
$config[RabbitMqConstants::RABBITMQ_CONNECTIONS] = [
    [
        RabbitMqConstants::RABBITMQ_DEFAULT_CONNECTION => true,
        RabbitMqConstants::RABBITMQ_CONNECTION_NAME => 'AT-connection',
        RabbitMqConstants::RABBITMQ_HOST => 'localhost',
        RabbitMqConstants::RABBITMQ_PORT => '5672',
        RabbitMqConstants::RABBITMQ_PASSWORD => 'mate20mg',
        RabbitMqConstants::RABBITMQ_USERNAME => 'AT_development',
        RabbitMqConstants::RABBITMQ_VIRTUAL_HOST => '/AT_development_zed',
    ],
    [
        RabbitMqConstants::RABBITMQ_CONNECTION_NAME => 'DE-connection',
        RabbitMqConstants::RABBITMQ_HOST => 'localhost',
        RabbitMqConstants::RABBITMQ_PORT => '5672',
        RabbitMqConstants::RABBITMQ_PASSWORD => 'mate20mg',
        RabbitMqConstants::RABBITMQ_USERNAME => 'DE_development',
        RabbitMqConstants::RABBITMQ_VIRTUAL_HOST => '/DE_development_zed',
    ],
];

// ---------- MailCatcher
$config[MailConstants::MAILCATCHER_GUI] = sprintf('http://%s:1080', $config[ApplicationConstants::HOST_ZED]);
