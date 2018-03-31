<?php
require_once __DIR__ . '/vendor/autoload.php';
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;

$connection = new AMQPStreamConnection('127.0.0.1', 5672, 'guest', 'guest');
$channel = $connection->channel();
$expiration = 0;

$channel->exchange_declare('delay_exchange', 'direct',false,false,false);

$channel->queue_declare('delay_queue',false,true,false,false,false);
$channel->queue_bind('delay_queue', 'delay_exchange','delay_exchange');


$msg = new AMQPMessage($expiration,array(
    'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
));

$channel->basic_publish($msg,'delay_exchange','delay_exchange');
echo date('Y-m-d H:i:s')." [x] 发送一条0毫秒后执行的数据! ".PHP_EOL;

$channel->close();
$connection->close();
