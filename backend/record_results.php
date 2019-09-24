<?php


include_once __DIR__ . "/../api/1.2/libs/DB.php";
include_once __DIR__ . "/../api/1.2/libs/amqp.php";
include_once __DIR__ . "/../api/1.2/libs/pki.php";
include_once __DIR__ . "/../api/1.2/libs/exceptions.php";
include_once __DIR__ . "/../api/1.2/libs/services.php";

$opts = getopt('v', array('exchange:','queue:','no-verify','debug'));

function opt($name, $default) {
    global $opts;
    
    return (isset($opts[$name])) ? $opts[$name] : $default;
}

function flag($flag) {
    global $opts;
    
    return isset($opts[$flag]);
}

$ch = amqp_connect();

$ex = new AMQPExchange($ch);
$ex->setName(opt('exchange', 'org.results'));

$q = new AMQPQueue($ch);
$q->setName(opt('queue', 'results'));
$q->setFlags(AMQP_DURABLE);
$q->declare();

$q->bind('org.blocked', opt('queue', 'results') . '.#');

$dynamo = new DynamoWrapper();

$conn = db_connect();

$VERIFY = 1;
if (flag('no-verify')) {
    $VERIFY = 0;
}

if (flag('v')) {
    print "Listening on " . opt('queue','results') . "\n";
}

$processor = new ResultProcessorService(
  $conn,
  new UrlLoader($conn),
  new ProbeLoader($conn),
  new IspLoader($conn)
);

function process_result($msg, $queue) {
  global $processor, $ex, $VERIFY;

  try {

    $queue->ack($msg->getDeliveryTag());
    $data = (array)json_decode($msg->getBody());

    error_log("Got result: {$data['probe_uuid']} {$data['url']} {$data['date']} {$data['status']}");

    $probe = $processor->probe_loader->load($data['probe_uuid']);
    if ($probe['enabled'] != 1) {
      print "Probe not enabled.\n";
      // drop message on floor
      return true;
    }

    if (flag('debug')) {
        var_dump($data);
    }
    # workaround for unicode encoding bug
    if (is_null($data['url'])) {
      return true;
    }

    if ($VERIFY) {
        Middleware::verifyUserMessage(
          implode(":", array(
            $data['probe_uuid'],
            $data['url'],
            $data['status'],
            $data['date'],
            $data['config']
            )
          ),
          $probe['secret'],
          $data['signature']
        );
    }

    try {
      $processor->process_result($data, $probe);
      
      if (array_has_key($data, 'request_data')) {
          $reqdata = array(
            'url' => $data['url'], 
            'created' => $data['date'],
            'id' => $data['test_uuid'],
            'requests' => $data['request_data']
            );
            
      }
      
    } catch (Exception $e) {
      error_log("processor->process_result failed.");
      error_log("Caught exception: " . get_class($e));
      error_log("Message was: " . $e->getMessage());
    }

    $forward = array(
      'url' => $data['url'],
      'network_name' => $data['network_name'],
      'status' => $data['status'],
      'blocktype' => $data['blocktype']
    );
    $ex->publish(json_encode($forward), $msg->getRoutingKey() . '.' . $data['status'], AMQP_NOPARAM);

  } catch (Exception $e) {
    error_log("process_result failed.");
    error_log("Caught exception: " . get_class($e));
    error_log("Message was: " . $e->getMessage());
  }

  return true;
}

$q->consume("process_result");
