<?php
require_once __DIR__ . '/vendor/autoload.php';
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;
$connection = new AMQPStreamConnection('127.0.0.1', 5672, 'guest', 'guest');
$channel = $connection->channel();

$channel->exchange_declare('delay_exchange', 'direct',false,false,false);
$channel->exchange_declare('cache_exchange', 'direct',false,false,false);


$channel->queue_declare('delay_queue',false,true,false,false,false);
$channel->queue_bind('delay_queue', 'delay_exchange','delay_exchange');

echo ' [*] Waiting for message. To exit press CTRL+C '.PHP_EOL;

$callback = function ($msg) {
    //$second = [15000,15000,30000,180000,1800000,1800000,1800000,3600000];
    $second = [0,1500,1500,3000,18000,180000,180000,180000,360000];
    $msg->delivery_info['channel']->basic_ack($msg->delivery_info['delivery_tag']);
    $connection = new AMQPStreamConnection('127.0.0.1', 5672, 'guest',
        'guest'); $channel = $connection->channel();
    //重发
    $callback_time = $msg->body;
    echo date('Y-m-d H:i:s')." [x] 接收一条".$second[$callback_time]."毫秒后的数据! ".PHP_EOL;
    $callback_time++;
    if (!isset($second[$callback_time])) return;
    $expiration = $second[$callback_time];
    $cache_exchange_name = 'cache_exchange_' . $expiration;
    $cache_queue_name = 'cache_queue_' . $expiration;
    $channel->exchange_declare('delay_exchange', 'direct', false, false, false);
    $channel->exchange_declare($cache_exchange_name, 'direct', false, false, false);
    $tale = new AMQPTable();
    $tale->set('x-dead-letter-exchange', 'delay_exchange');
    $tale->set('x-dead-letter-routing-key', 'delay_exchange');
    $tale->set('x-message-ttl', $expiration);
    $channel->queue_declare($cache_queue_name,false,true,false,false,false,$tale);
    $channel->queue_bind($cache_queue_name, $cache_exchange_name,'');
    $channel->queue_declare('delay_queue',false,true,false,false,false);
    $channel->queue_bind('delay_queue', 'delay_exchange','delay_exchange');
    $msg = new AMQPMessage($callback_time, array(
        'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
    ));

    $channel->basic_publish($msg, $cache_exchange_name,'');
    echo date('Y-m-d H:i:s')." [x] 发送一条".$expiration."毫秒后执行的数据! ".PHP_EOL;
    $channel->close();
    $connection->close();
};

//只有consumer已经处理并确认了上一条message时queue才分派新的message给它
$channel->basic_qos(null, 1, null);
$channel->basic_consume('delay_queue','',false,false,false,false,$callback);


while (count($channel->callbacks)) {
    $channel->wait();
}
$channel->close();
$connection->close();
