<?php


include_once __DIR__ . "/../api/1.2/libs/DB.php";
include_once __DIR__ . "/../api/1.2/libs/amqp.php";
include_once __DIR__ . "/../api/1.2/libs/pki.php";
include_once __DIR__ . "/../api/1.2/libs/exceptions.php";
include_once __DIR__ . "/../api/1.2/libs/services.php";

$ch = amqp_connect();

$ex = new AMQPExchange($ch);
$ex->setName('org.blocked');

$q = new AMQPQueue($ch);
$q->setName('import');
$q->setFlags(AMQP_DURABLE);
$q->declare();

$conn = db_connect();

$queueservice = new AMQPQueueService($ch, "check.test");
$urlloader = new UrlLoader($conn);

function process_import($msg, $queue) {
  global $queueservice, $urlloader;
  try {

    $queue->ack($msg->getDeliveryTag());
    $data = (array)json_decode($msg->getBody());

    error_log("Got url: {$data['url']}}");

    # workaround for unicode encoding bug
    if (is_null($data['url'])) {
      return true;
    }

    #Middleware::verifyUserMessage(
    #  implode(":", array(
    #    $data['probe_uuid'],
    #    $data['url'],
    #    $data['status'],
    #    $data['date'],
    #    $data['config']
    #    )
    #  ),
    #  $probe['secret'],
    #  $data['signature']
    #);

    $urltext = normalize_url($data['domain']);

    $newurl = $app['db.url.load']->insert($urltext, $data['source']);

    if ($newurl) {
        $queueservice->publish_url($urltext);
    }


  } catch (Exception $e) {
    error_log("process_result failed.");
    error_log("Caught exception: " . get_class($e));
    error_log("Message was: " . $e->getMessage());
  }

  return true;
}

$q->consume("process_import");
