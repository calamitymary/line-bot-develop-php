<?php

require_once __DIR__ . '/vendor/autoload.php';

const GNAVI_ACCESS_KEY = "2b8c732e2cbae40e4ad94857e789bf6a";
const LINE_CHANNEL_ID = "1577755862";
const LINE_MID = "input your mid";

$httpClient = new \LINE\LINEBot\HTTPClient\CurlHTTPClient(getenv('CHANNEL_ACCESS_TOKEN'));
$bot = new \LINE\LINEBot($httpClient, ['channelSecret' => getenv('CHANNEL_SECRET')]);

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
  // $bot->replyText($event->getReplyToken(), $event->getText());
  if ($contentType !== 7) {
     $response_format_text = ['contentType'=>1,"toType"=>1,"text"=>"You should send location information"];
     send_message_to_user($to,$response_format_text);
  } else {
    $ramen_info = get_ramen_info($jsonObj);
    $response_format_text = ['contentType'=>1,"toType"=>1,"text"=>$ramen_info];
    send_message_to_user($to,$response_format_text);
  }
}

function send_message_to_user($to,$response_format_text){
  $post_data = ["to"=>[$to],"toChannel"=>"1383378250","eventType"=>"138311608800106203","content"=>$response_format_text];
  $ch = curl_init("https://trialbot-api.line.me/v1/events");
  curl_setopt($ch, CURLOPT_POST,true);
  curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($post_data));
  curl_setopt($ch, CURLOPT_HTTPHEADER, create_http_header());
  $result = curl_exec($ch);
  curl_close($ch);
}

function create_http_header(){
  $content_type = 'Content-Type: application/json; charser=UTF-8';
  $channel_id = 'X-Line-ChannelID: '.LINE_CHANNEL_ID;
  $channel_secret = 'X-Line-ChannelSecret: '.$bot;
  $mid = 'X-Line-Trusted-User-With-ACL: '.LINE_MID;
  return array($content_type,$channel_id,$channel_secret,$mid);
}

// ぐるなびWebサービスを利用した検索
function get_ramen_info($jsonObj){
  // ぐるなびWebサービス利用するためのURLの組み立て
  $url = build_url($jsonObj);
  // API実行
  $json = file_get_contents($url);
  return parse($json);
}


function build_url($jsonObj){

  //エンドポイント
  $uri = "http://api.gnavi.co.jp/RestSearchAPI/20150630/";

  //APIアクセスキーは、ぐるなびで取得して設定します。
  $acckey = GNAVI_ACCESS_KEY;

  //返却値のフォーマットを変数に入れる
  $format= "json";

  //緯度・経度、範囲、及びカテゴリーにラーメンを設定
  $location = $jsonObj->{"result"}[0]->{"content"}->{"location"};
  $lat   = $location->latitude;
  $lon   = $location->longitude;
  $range = 3;
  // 業態がラーメン屋さんを意味するぐるなびのコード(大業態マスタ取得APIをコールして調査)
  $category_s = "RSFST08008";

  //URL組み立て
  $url  = sprintf("%s%s%s%s%s%s%s%s%s%s%s%s%s", $uri, "?format=", $format, "&keyid=", $acckey, "&latitude=", $lat,"&longitude=",$lon,"&range=",$range,"&category_s=",$category_s);

  return $url;
}

function parse($json){

  $obj  = json_decode($json);

  $result = "";

  $total_hit_count = $obj->{'total_hit_count'};

  if ($total_hit_count === null) {
      $result .= "近くにラーメン屋さんはありません。";
  }else{
      $result .= "近くにあるラーメン屋さんです。\n\n";
      foreach($obj->{'rest'} as $val){

          if (checkString($val->{'name'})) {
              $result .= $val->{'name'}."\n";
          }

          if (checkString($val->{'address'})) {
              $address = get_address_without_postal_code($val->{'address'});
              $result .= $address."\n";
          }

          if (checkString($val->{'url'})) {
              $result .= $val->{'url'}."\n";
          }

          $result.="\n"."\n";
      }
      $result.="Powered by ぐるなび";
  }
  return $result;
}

function get_address_without_postal_code($address){
  return mb_substr($address,11);
}

//文字列であるかをチェック
function checkString($input)
{
  if(isset($input) && is_string($input)) {
      return true;
  }else{
      return false;
  }
}

?>
