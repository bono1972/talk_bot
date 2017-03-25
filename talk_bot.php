<?php 
ini_set( 'display_errors', 1 );

// TwitterOAuthライブラリの読み込み
require (__DIR__ ."/twitteroauth/autoload.php");
use Abraham\TwitterOAuth\TwitterOAuth;
//twitterOAuth設定とA3RTのAPIの設定読み込み　このファイルにTwitter認証4つのkeyとA3RTのAPIKey記載
try{
  require (__DIR__ ."/credentials.php"); 
} catch (Exception $e){
  echo $e->getMessage();
}
$user = "A3RT_Talk_BOT";//twitterID

//接続
$connection = new TwitterOAuth(CONSUMER_KEY, CONSUMER_SECRET, ACCESS_TOKEN, ACCESS_TOKEN_SECRET);

$sID = LoadID();//since_id読み込み

//自分宛てメンションのタイムライン取得
$mentions = $connection->get("statuses/mentions_timeline", array("count" => 30,"since_id" => $sID));
foreach ($mentions as $mention){
    var_dump ($mention);
    $tx = null;
    $id = $mention->id_str; // 呟きのID。string型
    $screen_name = $mention->user->screen_name; // ユーザーID
    $name = $mention->user->name; // ユーザー名
    $in_reply_to_status_id_str = $mention->in_reply_to_status_id_str; //会話表示用
    // 呟き内容。余分なスペースを消して、半角カナを全角カナに、全角英数を半角英数に変換
    $text = mb_convert_kana(trim($mention->text),"rnKHV","utf-8");
    $text = trim(str_replace("@".$user,"",$text)); //mention部分を削除
    // Botが自分自身の呟き、RT、QTに反応しないようにする
    if($screen_name == $user || preg_match("/(R|Q)T( |:)/",$text)){continue;}
    $reply = getTalk($text);
    $tx = "@".$screen_name." ".$reply;
    $param = ["status" => $tx, "in_reply_to_status_id_str" => $in_reply_to_status_id_str];
    if(isset($tx)) {
        $res = $connection->post("statuses/update",  $param);
        if($sID < $id) {$sID = $id;}
  
    }
    SaveID($sID);
}

function getTalk ($text) {
	// A3RT TalkAPI
	$url = "https://api.a3rt.recruit-tech.co.jp/talk/v1/smalltalk";
	// ポストするデータ
	$data = [
		"apikey" => API_KEY,
		"query" => $text
	];

	// セッションを初期化
	$conn = curl_init();
	// オプション
	curl_setopt($conn, CURLOPT_SSL_VERIFYPEER, false);
	curl_setopt($conn, CURLOPT_SSL_VERIFYHOST, false);
	curl_setopt($conn, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($conn, CURLOPT_URL,  $url);
	curl_setopt($conn, CURLOPT_POST, true);
	curl_setopt($conn, CURLOPT_POSTFIELDS, $data);
	// 実行
	$res = curl_exec($conn);
	// close
	curl_close($conn);

	$res = mb_convert_encoding($res,'UTF-8');
	$obj = json_decode($res, false);
	$reply = $obj->results[0]->reply;
	return $reply;
}


function SaveID($sID){
  try{
  $savefile = __DIR__."/savefile.txt";
  $fileObj = new SplFileObject($savefile,"wb");//書込専用、上書き
  $fileObj->flock(LOCK_EX);
  $fileObj->fwrite($sID);
  $fileObj->flock(LOCK_UN);
  } catch (Exception $e){
    echo $e->getMessage();
  }
}
function LoadID(){
  try{
  $savefile = __DIR__."/savefile.txt";
  $fileObj = new SplFileObject($savefile,"rb");//読込専用
  $fileObj->flock(LOCK_SH);
  $sID = $fileObj->fread($fileObj->getSize());
  if ($sID ===FALSE){$sID ="1";} 
  $fileObj->flock(LOCK_UN);
  return $sID;
  } catch (Exception $e){
    echo $e->getMessage();
  }
}
