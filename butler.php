<?php
// Import configuraton file and auxilliary code
include("settings.php");

$SABServer = rtrim($SABServer, "/");
$SABBaseURL = $SABServer."/api?output=json&apikey=".$SABAPIKey;


// Import and setup Telegram API
include("Telegram.php");
$telegram = new Telegram($bot_id);

// Take text and chat_id from the message
$result = $telegram->getData();
$text = $telegram->Text();
$chat_id = $telegram->ChatID();


file_put_contents("log.log", print_r($result, true));



if (array_key_exists("message", $result)) {
  // Check only permitted users are being parsed
  $user = $result["message"]["from"]["username"];
  if (!in_array($user, $permittedUsers)) {
    die();
  }

  // Check message text and chat ID exists.
  if(is_null($text) || is_null($chat_id)) {
    die();
  }

  normalMessage();
} else if (array_key_exists("callback_query", $result)) {
  callbackQuery();
}
else{
  die();
}


function normalMessage(){
  global $result, $text;

  // Switch on command
  // NOTE: This will deal only with the first command it finds
  // in the array of entities

  $entities = $result["message"]["entities"];
  foreach ($entities as $entity) {
    if($entity["type"] == "bot_command"){
      $offset = $entity["offset"];
      $length = $entity["length"];
      parseCommand(substr($text,$offset+1,$length-1));
      break;
    }
  }
}

function callbackQuery(){
  global $result, $telegram;
  $callbackID = $result["callback_query"]["id"];
  $buttonData = $result["callback_query"]["data"];
  $originalMessage = $result["callback_query"]["message"]["text"];
  $messageID = $result["callback_query"]["message"]["message_id"];
  switch ($originalMessage) {
    case "Very good, sir.  For how long would you like me to pause your downloads?":
      pauseSAB($buttonData);
      answerCallbackQuery($callbackID, "Downloads paused.");
      break;
    case "Very good, sir.  What speed should I limit your downloads to?":
      limitSAB($buttonData);
      answerCallbackQuery($callbackID, "Downloads limited to ".$buttonData."% of maximum.");
      break;
    default:
      break;
  }
}


function parseCommand($cmd){
  switch ($cmd) {
    case "pause":
      pauseSABPrompt();
      break;
    case "resume":
      resumeSAB();
      break;
    case "setspeed":
      SABSpeedPrompt();
      break;
    default:
      $message = "Command not recognised, sorry!";
      break;
  }
}


function pauseSABPrompt() {
  global $SABBaseURL, $telegram;

  $b15 = $telegram->buildInlineKeyboardButton("15 min", "", "15");
  $b30 = $telegram->buildInlineKeyboardButton("30 min", "", "30");
  $b60 = $telegram->buildInlineKeyboardButton("60 min", "", "60");
  $b180 = $telegram->buildInlineKeyboardButton("3 hr", "", "180");
  $b360 = $telegram->buildInlineKeyboardButton("6 hr", "", "360");
  $b540 = $telegram->buildInlineKeyboardButton("9 hr", "", "540");
  $bIND = $telegram->buildInlineKeyboardButton("Indefinitely", "", "indefinitely");

  $kbd = $telegram->buildInlineKeyboard([[$b15, $b30, $b60],[$b180, $b360, $b540],[$bIND]]);

  sendMessageWithInlineKeyboard("Very good, sir.  For how long would you like me to pause your downloads?", $kbd);
}

function pauseSAB($time) {
  global $SABBaseURL;
  if($time == "indefinitely"){
    $URL = $SABBaseURL."&mode=pause";
  }
  else {
    $URL = $SABBaseURL."&mode=config&name=set_pause&value=".$time;
  }
  $result = file_get_contents($URL);
}

function limitSAB($limit) {
  global $SABBaseURL;
  $URL = $SABBaseURL."&mode=config&name=speedlimit&value=".$limit;
  $result = file_get_contents($URL);
}

function resumeSAB() {
  global $SABBaseURL;
  $URL = $SABBaseURL."&mode=resume";
  $result = file_get_contents($URL);
  sendMessage("Your downloads have been resumed, sir.");
}

function SABSpeedPrompt() {
  global $SABBaseURL, $telegram;

  $b10 = $telegram->buildInlineKeyboardButton("10%", "", "10");
  $b25 = $telegram->buildInlineKeyboardButton("25%", "", "25");
  $b50 = $telegram->buildInlineKeyboardButton("50%", "", "50");
  $b75 = $telegram->buildInlineKeyboardButton("75%", "", "75");
  $b80 = $telegram->buildInlineKeyboardButton("80%", "", "80");
  $b90 = $telegram->buildInlineKeyboardButton("90%", "", "90");
  $b100 = $telegram->buildInlineKeyboardButton("100%", "", "100");

  $kbd = $telegram->buildInlineKeyboard([[$b10, $b25, $b50],[$b75, $b80, $b90],[$b100]]);

  sendMessageWithInlineKeyboard("Very good, sir.  What speed should I limit your downloads to?", $kbd);

}

function sendMessage($message){
  global $chat_id, $telegram;
  $content = array('chat_id' => $chat_id, 'text' => $message);
  $telegram->sendMessage($content);
}

function sendMessageWithInlineKeyboard($message, $keyboard){
  global $chat_id, $telegram;
  $content = array('chat_id' => $chat_id, 'text' => $message, 'reply_markup' => $keyboard);
  $telegram->sendMessage($content);
}

function answerCallbackQuery($callbackID, $message){
  global $telegram;
  $content = array('callback_query_id' => $callbackID, 'text' => $message);
  $telegram->answerCallbackQuery($content);

}

?>
