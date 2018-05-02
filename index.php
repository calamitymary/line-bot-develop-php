<?php

require_once __DIR__ . '/vendor/autoload.php';

$httpClient = new \LINE\LINEBot\HTTPClient\CurlHTTPClient(getenv('CHANNEL_ACCESS_TOKEN'));
$bot = new \LINE\LINEBot($httpClient, ['channelSecret' => getenv('CHANNEL_SECRET')]);
// ぐるなびアクセスキー
$GNAVI_ACCESS_KEY = "2b8c732e2cbae40e4ad94857e789bf6a";

$request = file_get_contents('php://input');
$jsonObj = json_decode($request);
$to = $jsonObj->{"result"}[0]->{"content"}->{"from"};
$contentType = $jsonObj->{"result"}[0]->{"content"}->{"contentType"};
$opType = $jsonObj->{"result"}[0]->{"content"}->{"opType"};

// 友達追加時に送信するメッセージ
if ($opType !== null && $opType === 4) {
  $response_format_text = ['contentType'=>1,"toType"=>1,
    "text"=>"You can check ramen store information by location information. @Author y.o"];
  send_message_to_user($to,$response_format_text);
  return;
}

$signature = $_SERVER["HTTP_" . \LINE\LINEBot\Constant\HTTPHeader::LINE_SIGNATURE];
try {
  $events = $bot->parseEventRequest(file_get_contents('php://input'), $signature);
} catch(\LINE\LINEBot\Exception\InvalidSignatureException $e) {
  error_log("parseEventRequest failed. InvalidSignatureException => ".var_export($e, true));
} catch(\LINE\LINEBot\Exception\UnknownEventTypeException $e) {
  error_log("parseEventRequest failed. UnknownEventTypeException => ".var_export($e, true));
} catch(\LINE\LINEBot\Exception\UnknownMessageTypeException $e) {
  error_log("parseEventRequest failed. UnknownMessageTypeException => ".var_export($e, true));
} catch(\LINE\LINEBot\Exception\InvalidEventRequestException $e) {
  error_log("parseEventRequest failed. InvalidEventRequestException => ".var_export($e, true));
}

foreach ($events as $event) {
  if (!($event instanceof \LINE\LINEBot\Event\MessageEvent)) {
    error_log('Non message event has come');
    continue;
  }
  if (!($event instanceof \LINE\LINEBot\Event\MessageEvent\TextMessage)) {
    error_log('Non text message has come');
    continue;
  }
  $bot->replyText($event->getReplyToken(), $event->getText());
}

 ?>
