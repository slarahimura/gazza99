<?php
$faucethub_lib_version = "v1.0.1";
class FaucetBOX extends FaucetHub {
public function __construct($api_key, $currency = "BTC", $disable_curl = false, $verify_peer = true) {
	parent::__construct($api_key, $currency, $disable_curl, $verify_peer);
}
}

class FaucetHub
{
protected $api_key;
protected $currency;
protected $timeout;
public $last_status = null;
protected $api_base = "https://faucethub.io/api/v1/";
public function __construct($api_key, $currency = "BTC", $disable_curl = false, $verify_peer = true, $timeout = null) {
	$this->api_key = $api_key;
	$this->currency = $currency;
	$this->disable_curl = $disable_curl;
	$this->verify_peer = $verify_peer;
	$this->curl_warning = false;
	$this->setTimeout($timeout);
}

public function setTimeout($timeout) {
	if($timeout === null) {
		$socket_timeout = ini_get('default_socket_timeout'); 
		$script_timeout = ini_get('max_execution_time');
		$timeout = min($script_timeout / 2, $socket_timeout);
	}
	$this->timeout = $timeout;
 }

public function __execPHP($method, $params = array()) {
	$params = array_merge($params, array("api_key" => $this->api_key, "currency" => $this->currency));
	$opts = array(
		"http" => array(
			"method" => "POST",
			"header" => "Content-type: application/x-www-form-urlencoded\r\n",
			"content" => http_build_query($params),
			"timeout" => $this->timeout,
		),
		"ssl" => array(
			"verify_peer" => $this->verify_peer
		)
	);
	
	$ctx = stream_context_create($opts);
	$fp = fopen($this->api_base . $method, 'rb', null, $ctx);        
	if (!$fp) {
		return json_encode(array(
			'status' => 503,
			'message' => 'Connection to FaucetHub failed, please try again later',
		), TRUE);
	}
	$response = stream_get_contents($fp);
	if($response && !$this->disable_curl) {
		$this->curl_warning = true;
	}
	fclose($fp);
	return $response;
}

public function __exec($method, $params = array()) {
	$this->last_status = null;
	if($this->disable_curl) {
		$response = $this->__execPHP($method, $params);
	} else {
		$response = $this->__execCURL($method, $params);
	}
	$response = json_decode($response, true);
	if($response) {
		$this->last_status = $response['status'];
	} else {
		$this->last_status = null;
		$response = array(
			'status' => 502,
			'message' => 'Invalid response',
		);
	}
	return $response;
}

public function __execCURL($method, $params = array()) {
	$params = array_merge($params, array("api_key" => $this->api_key, "currency" => $this->currency));
	$ch = curl_init($this->api_base . $method);
	curl_setopt($ch, CURLOPT_POST, true);
	curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $this->verify_peer);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_TIMEOUT, (int)$this->timeout);
	$response = curl_exec($ch);
	if(!$response) {
		$response = $this->__execPHP($method, $params); // disabled the exec fallback when using curl
		return json_encode(array(
			'status' => 504,
			'message' => 'Connection error',
		), TRUE);
	}
	curl_close($ch);
	return $response;
}

public function send($to, $amount, $referral = false, $ip_address = "") {
	$referral = ($referral === true) ? 'true' : 'false';
	$r = $this->__exec("send", array("to" => $to, "amount" => $amount, "referral" => $referral, "ip_address" => $ip_address));
	if (array_key_exists("status", $r) && $r["status"] == 200) {
		return array(
			'success' => true,
			'message' => 'Payment sent to your address using FaucetHub.io',
			'html' => '<div class="alert alert-success">' . htmlspecialchars($amount) . ' satoshi was sent to <a target="_blank" href="https://faucethub.io/balance/' . rawurlencode($to) . '">your account at FaucetHub.io</a>.</div>',
			'html_coin' => '<div class="alert alert-success">' . htmlspecialchars(rtrim(rtrim(sprintf("%.8f", $amount/100000000), '0'), '.')) . ' '.$this->currency.' was sent to <a target="_blank" href="https://faucethub.io/balance/' . rawurlencode($to) . '">your account at FaucetHub.io</a>.</div>',
			'balance' => $r["balance"],
			'balance_bitcoin' => $r["balance_bitcoin"],
			'response' => json_encode($r)
		);
	}
	
	// Let the user know they need an account to claim
	if (array_key_exists("status", $r) && $r["status"] == 456) {
		return array(
			'success' => false,
			'message' => $r['message'],
			'html' => '<div class="alert alert-danger">Before you can receive payments at FaucetHub.io with this address you must link it to an account. <a href="http://faucethub.io/signup" target="_blank">Create an account at FaucetHub.io</a> and link your address, then come back and claim again.</div>',
			'response' => json_encode($r)
		);
	}

	if (array_key_exists("message", $r)) {
		return array(
			'success' => false,
			'message' => $r["message"],
			'html' => '<div class="alert alert-danger">' . htmlspecialchars($r["message"]) . '</div>',
			'response' => json_encode($r)
		);
	}

	return array(
		'success' => false,
		'message' => 'Unknown error.',
		'html' => '<div class="alert alert-danger">Unknown error.</div>',
		'response' => json_encode($r)
	);
}

public function sendReferralEarnings($to, $amount, $ip_address = "") {
	return $this->send($to, $amount, true, $ip_address);
}

public function getPayouts($count) {
	$r = $this->__exec("payouts", array("count" => $count) );
	return $r;
}

public function getCurrencies() {
	$r = $this->__exec("currencies");
	return $r['currencies'];
}

public function getBalance() {
	$r = $this->__exec("balance");
	return $r;
}

public function checkAddress($address, $currency = "BTC") {
	$r = $this->__exec("checkaddress", array('address' => $address, 'currency' => $currency));
	return $r;
}
}
date_default_timezone_set('Asia/Jakarta');
error_reporting(0);
set_time_limit(0);
ini_set('memory_limit', '-1');
ini_set('output_buffering',0); 
ini_set('request_order', 'GP');
ini_set('variables_order','EGPCS');
ini_set('max_execution_time','-1');
$CY="\e[36m"; $GR="\e[2;32m"; $OG="\e[92m"; $WH="\e[37m"; $RD="\e[31m"; $YL="\e[33m"; $BF="\e[34m"; $DF="\e[39m"; $OR="\e[33m"; $PP="\e[35m"; $B="\e[1m"; $CC="\e[0m";
echo $RD."\n============================================\nTHIS SCRIPT WAS CREATED BY AKBAR.FX23\n============================================\n
Phone	=	+6283807804186
Email	=	ckpakbar23@gmail.com
Fb	=	facebook.com/akbar.fx23
Donate\t=\t087710269668\n
============================================\n".$CC;
echo "Username: ";
$name = trim(fgets(STDIN));
echo "Password: ";
$pw = trim(fgets(STDIN));
if($name == 'master' && $pw == 'autofaucet'){
	$interval=0; 
	$sleep = $interval-(time());
	while ( 1 ){
		if(time() != $sleep) {
			echo "\n================[MAIN MENU]==================\n";
			echo "=============================================\n\n[1]. AUTOCLAIM FAUCET\n[2]. GET URL\n[3]. ACCOUNT MANAGER\n[4]. SITE LIST\n[5]. HELP\n[6]. INFO UPDATE\n[7]. CONTACT\n=============================================\n\nSelect Number: ";
			$com = trim(fgets(STDIN));
			if($com == '1'){
			if(date('H')>=3){
					echo "\n================[AUTOCLAIM MENU]==================\n";
					echo "=============================================\n\n[1]. Multiple Claim Url\n[2]. Single Claim Url\n=============================================\n\n";
					echo "Please Select Number: ";
					$au = trim(fgets(STDIN));
					if($au == '1'){
						echo "=============================================\nYour Claim Url List File: ";
						$ur = trim(fgets(STDIN));
						echo "=============================================\nWhat is Currency: ";
						$cu = trim(fgets(STDIN));
						echo "=============================================\nTime Until Payout: ";
						$t = trim(fgets(STDIN));
						echo "=============================================\nLimit Proccess: ";
						$l = trim(fgets(STDIN));
						for($i=0; $i<$l; $i++){	
							if($cu == 'BCH'){
								$g = file_get_contents($ur);
								$p = explode("\n", $g);
								foreach($p as $ul => $link){
									$url = $link.'&r=1D6hE7geEiS9Gc9vtKHr47ygJ4YhErMzY7&rc=BCH';
									$get = claim($url);
									echo $get;
								}
								sleep($t);
							}elseif($cu == 'BLK'){
								$g = file_get_contents($ur);
								$p = explode("\n", $g);
								foreach($p as $ul => $link){
									$url = $link.'&r=BM6fyVhNrJPhxAx6eWSstXgk24PzvATVUk&rc=BLK';
									$get = claim($url);
									echo $get;
								}
								sleep($t);
							}elseif($cu == 'BTC'){
								$g = file_get_contents($ur);
								$p = explode("\n", $g);
								foreach($p as $ul => $link){
									$url = $link.'&r=1G4kyTX5xrCJAJFVrQdRde3g2uXgpWoaik&rc=BTC';
									$get = claim($url);
									echo $get;
								}
								sleep($t);
							}elseif($cu == 'BTX'){
								$g = file_get_contents($ur);
								$p = explode("\n", $g);
								foreach($p as $ul => $link){
									$url = $link.'&r=1MGhuRu85cQF6WcSStBpGqBv12RguMBE4j&rc=BTX';
									$get = claim($url);
									echo $get;
								}
								sleep($t);
							}elseif($cu == 'DASH'){
								$g = file_get_contents($ur);
								$p = explode("\n", $g);
								foreach($p as $ul => $link){
									$url = $link.'&r=XbEGWnRc6VuH556RUw3ecpLmB5BLimKTAp&rc=DASH';
									$get = claim($url);
									echo $get;
								}
								sleep($t);
							}elseif($cu == 'DOGE'){
								$g = file_get_contents($ur);
								$p = explode("\n", $g);
								foreach($p as $ul => $link){
									$url = $link.'&r=DHWVyPdtcWg4wFU4zHCPJ8gwqogK5YCZ4F&rc=DOGE';
									$get = claim($url);
									echo $get;
								}
								sleep($t);
							}elseif($cu == 'ETH'){
								$g = file_get_contents($ur);
								$p = explode("\n", $g);
								foreach($p as $ul => $link){
									$url = $link.'&r=0xa700fcbb4c2227e1958c182f1d62d0cf3f358bdd&rc=ETH';
									$get = claim($url);
									echo $get;
								}
								sleep($t);
							}elseif($cu == 'LTC'){
								$g = file_get_contents($ur);
								$p = explode("\n", $g);
								foreach($p as $ul => $link){
									$url = $link.'&r=LaHiEfpv3WSMR6wf2Yciuf7SF7txz1xHws&rc=LTC';
									$get = claim($url);
									echo $get;
								}
								sleep($t);
							}elseif($cu == 'PPC'){
								$g = file_get_contents($ur);
								$p = explode("\n", $g);
								foreach($p as $ul => $link){
									$url = $link.'&r=PJHC5gDtbuqWfDjMjM4RZf4JsW8sB9hb2p&rc=PPC';
									$get = claim($url);
									echo $get;
								}
								sleep($t);
							}elseif($cu == 'XPM'){
								$g = file_get_contents($ur);
								$p = explode("\n", $g);
								foreach($p as $ul => $link){
									$url = $link.'&r=ANgi1XZD5NZwbryUptL7FoWbnvAMZJj86z&rc=XPM';
									$get = claim($url);
									echo $get;
								}
								sleep($t);
							}elseif($cu == 'POT'){
								$g = file_get_contents($ur);
								$p = explode("\n", $g);
								foreach($p as $ul => $link){
									$url = $link.'&r=PLp44G6qeuYDrwuxgKrc5yTAt1baa6oB2G&rc=POT';
									$get = claim($url);
									echo $get;
								}
								sleep($t);
							}
						}
					}elseif($au == '2'){
						echo "=============================================\nYour Claim Url: ";
						$link = trim(fgets(STDIN));
						echo "=============================================\nWhat is Currency: ";
						$cu = trim(fgets(STDIN));
						echo "=============================================\nTime Until Payout: ";
						$t = trim(fgets(STDIN));
						echo "=============================================\nLimit Proccess: ";
						$l = trim(fgets(STDIN));
						for($i=0; $i<$l; $i++){	
							if($cu == 'BCH'){
								$url = $link.'&r=1D6hE7geEiS9Gc9vtKHr47ygJ4YhErMzY7&rc=BCH';
								$get = claim($url);
								echo $get;
								sleep($t);
							}elseif($cu == 'BLK'){
								$url = $link.'&r=BM6fyVhNrJPhxAx6eWSstXgk24PzvATVUk&rc=BLK';
								$get = claim($url);
								echo $get;
								sleep($t);
							}elseif($cu == 'BTC'){
								$url = $link.'&r=1G4kyTX5xrCJAJFVrQdRde3g2uXgpWoaik&rc=BTC';
								$get = claim($url);
								echo $get;
								sleep($t);
							}elseif($cu == 'BTX'){
								$url = $link.'&r=1MGhuRu85cQF6WcSStBpGqBv12RguMBE4j&rc=BTX';
								$get = claim($url);
								echo $get;
								sleep($t);
							}elseif($cu == 'DASH'){
								$url = $link.'&r=XbEGWnRc6VuH556RUw3ecpLmB5BLimKTAp&rc=DASH';
								$get = claim($url);
								echo $get;
								sleep($t);
							}elseif($cu == 'DOGE'){
								$url = $link.'&r=DHWVyPdtcWg4wFU4zHCPJ8gwqogK5YCZ4F&rc=DOGE';
								$get = claim($url);
								echo $get;
								sleep($t);
							}elseif($cu == 'ETH'){
								$url = $link.'&r=0xa700fcbb4c2227e1958c182f1d62d0cf3f358bdd&rc=ETH';
								$get = claim($url);
								echo $get;
								sleep($t);
							}elseif($cu == 'LTC'){
								$url = $link.'&r=LaHiEfpv3WSMR6wf2Yciuf7SF7txz1xHws&rc=LTC';
								$get = claim($url);
								echo $get;
								sleep($t);
							}elseif($cu == 'PPC'){
								$url = $link.'&r=PJHC5gDtbuqWfDjMjM4RZf4JsW8sB9hb2p&rc=PPC';
								$get = claim($url);
								echo $get;
								sleep($t);
							}elseif($cu == 'XPM'){
								$url = $link.'&r=ANgi1XZD5NZwbryUptL7FoWbnvAMZJj86z&rc=XPM';
								$get = claim($url);
								echo $get;
								sleep($t);
							}elseif($cu == 'POT'){
								$url = $link.'&r=PLp44G6qeuYDrwuxgKrc5yTAt1baa6oB2G&rc=POT';
								$get = claim($url);
								echo $get;
								sleep($t);
							}
						}
					}
				}else{
					echo "Time To Sleep\n\n";
				}
			}elseif($com == '2'){
				echo "\n===============[GET URL MENU]================\n";
				echo "=============================================\n\n[1]. Multiple Get Url\n[2]. Single Get Url\n=============================================\n\n";
				echo "Please Select Number:  ";
				$gu = trim(fgets(STDIN));
				if($gu == '1'){
					echo "Faucethub Wallet list File:  ";
					$ur = trim(fgets(STDIN));
					echo "What is Currency: ";
					$cur = trim(fgets(STDIN));
					echo "Site: ";
					$site = trim(fgets(STDIN));
					echo "Save as File Name: ";
					$name = trim(fgets(STDIN));
					$g = file_get_contents($ur);
					$p = explode("\n", $g);
					foreach($p as $param ){
						$wall = $param;
						$post = curl_init();
								curl_setopt($post, CURLOPT_URL, $site.'/verify.php');
								curl_setopt($post, CURLOPT_POST, 1);
								curl_setopt($post, CURLOPT_HEADER, 1);
								curl_setopt($post, CURLOPT_SSL_VERIFYPEER, 0);
								curl_setopt($post, CURLOPT_SSL_VERIFYHOST, 2);
								curl_setopt($post, CURLOPT_RETURNTRANSFER, 1);
								curl_setopt($post, CURLOPT_FOLLOWLOCATION, 1);
								curl_setopt($post, CURLOPT_MAXREDIRS, 1);
								if($cur == 'BCH'){
									curl_setopt($post, CURLOPT_POSTFIELDS, "address={$wall}&currency={$cur}&r=1D6hE7geEiS9Gc9vtKHr47ygJ4YhErMzY7&rc={$cur}");
								}elseif($cur == 'BLK'){
									curl_setopt($post, CURLOPT_POSTFIELDS, "address={$wall}&currency={$cur}&r=BM6fyVhNrJPhxAx6eWSstXgk24PzvATVUk&rc={$cur}");
								}elseif($cur == 'BTC'){
									curl_setopt($post, CURLOPT_POSTFIELDS, "address={$wall}&currency={$cur}&r=1G4kyTX5xrCJAJFVrQdRde3g2uXgpWoaik&rc={$cur}");
								}elseif($cur == 'BTX'){
									curl_setopt($post, CURLOPT_POSTFIELDS, "address={$wall}&currency={$cur}&r=1MGhuRu85cQF6WcSStBpGqBv12RguMBE4j&rc={$cur}");
								}elseif($cur == 'DASH'){
									curl_setopt($post, CURLOPT_POSTFIELDS, "address={$wall}&currency={$cur}&r=XbEGWnRc6VuH556RUw3ecpLmB5BLimKTAp&rc={$cur}");
								}elseif($cur == 'DOGE'){
									curl_setopt($post, CURLOPT_POSTFIELDS, "address={$wall}&currency={$cur}&r=DHWVyPdtcWg4wFU4zHCPJ8gwqogK5YCZ4F&rc={$cur}");
								}elseif($cur == 'ETH'){
									curl_setopt($post, CURLOPT_POSTFIELDS, "address={$wall}&currency={$cur}&r=0xa700fcbb4c2227e1958c182f1d62d0cf3f358bdd&rc={$cur}");
								}elseif($cur == 'LTC'){
									curl_setopt($post, CURLOPT_POSTFIELDS, "address={$wall}&currency={$cur}&r=LaHiEfpv3WSMR6wf2Yciuf7SF7txz1xHws&rc={$cur}");
								}elseif($cur == 'PPC'){
									curl_setopt($post, CURLOPT_POSTFIELDS, "address={$wall}&currency={$cur}&r=PJHC5gDtbuqWfDjMjM4RZf4JsW8sB9hb2p&rc={$cur}");
								}elseif($cur == 'XPM'){
									curl_setopt($post, CURLOPT_POSTFIELDS, "address={$wall}&currency={$cur}&r=ANgi1XZD5NZwbryUptL7FoWbnvAMZJj86z&rc={$cur}");
								}elseif($cur == 'POT'){
									curl_setopt($post, CURLOPT_POSTFIELDS, "address={$wall}&currency={$cur}&r=PLp44G6qeuYDrwuxgKrc5yTAt1baa6oB2G&rc={$cur}");
								}
								curl_setopt($post, CURLOPT_USERAGENT, 'Mozilla/5.0 (Linux; Android 6.0; E1C 3G Build/MRA58K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/56.0.2924.87 Mobile Safari/537.36');
						$data = curl_exec($post);
						$info = curl_getinfo($post, CURLINFO_EFFECTIVE_URL);
								curl_close($post);
						$url = urlencode($info);
						$in = "$url\n";
						$f = fopen($name, 'a');
							fwrite($f, $in);
							fclose($f);
						echo "\n===============[GET URL FOUND]================\n";
						echo $in;
						echo "=============================================\n";
					}
				}elseif($gu == '2'){
					echo "Faucethub Wallet Address: ";
					$ur = trim(fgets(STDIN));
					echo "What is Currency: ";
					$cur = trim(fgets(STDIN));
					echo "Site: ";
					$site = trim(fgets(STDIN));
					echo "Save as File Name: ";
					$name = trim(fgets(STDIN));
					$post = curl_init();
							curl_setopt($post, CURLOPT_URL, $site.'/verify.php');
							curl_setopt($post, CURLOPT_POST, 1);
							curl_setopt($post, CURLOPT_HEADER, 1);
							curl_setopt($post, CURLOPT_SSL_VERIFYPEER, 0);
							curl_setopt($post, CURLOPT_SSL_VERIFYHOST, 2);
							curl_setopt($post, CURLOPT_RETURNTRANSFER, 1);
							curl_setopt($post, CURLOPT_FOLLOWLOCATION, 1);
							curl_setopt($post, CURLOPT_MAXREDIRS, 1);
							if($cur == 'BCH'){
								curl_setopt($post, CURLOPT_POSTFIELDS, "address={$wall}&currency={$cur}&r=1D6hE7geEiS9Gc9vtKHr47ygJ4YhErMzY7&rc={$cur}");
							}elseif($cur == 'BLK'){
								curl_setopt($post, CURLOPT_POSTFIELDS, "address={$wall}&currency={$cur}&r=BM6fyVhNrJPhxAx6eWSstXgk24PzvATVUk&rc={$cur}");
							}elseif($cur == 'BTC'){
								curl_setopt($post, CURLOPT_POSTFIELDS, "address={$wall}&currency={$cur}&r=1G4kyTX5xrCJAJFVrQdRde3g2uXgpWoaik&rc={$cur}");
							}elseif($cur == 'BTX'){
								curl_setopt($post, CURLOPT_POSTFIELDS, "address={$wall}&currency={$cur}&r=1MGhuRu85cQF6WcSStBpGqBv12RguMBE4j&rc={$cur}");
							}elseif($cur == 'DASH'){
								curl_setopt($post, CURLOPT_POSTFIELDS, "address={$wall}&currency={$cur}&r=XbEGWnRc6VuH556RUw3ecpLmB5BLimKTAp&rc={$cur}");
							}elseif($cur == 'DOGE'){
								curl_setopt($post, CURLOPT_POSTFIELDS, "address={$wall}&currency={$cur}&r=DHWVyPdtcWg4wFU4zHCPJ8gwqogK5YCZ4F&rc={$cur}");
							}elseif($cur == 'ETH'){
								curl_setopt($post, CURLOPT_POSTFIELDS, "address={$wall}&currency={$cur}&r=0xa700fcbb4c2227e1958c182f1d62d0cf3f358bdd&rc={$cur}");
							}elseif($cur == 'LTC'){
								curl_setopt($post, CURLOPT_POSTFIELDS, "address={$wall}&currency={$cur}&r=LaHiEfpv3WSMR6wf2Yciuf7SF7txz1xHws&rc={$cur}");
							}elseif($cur == 'PPC'){
								curl_setopt($post, CURLOPT_POSTFIELDS, "address={$wall}&currency={$cur}&r=PJHC5gDtbuqWfDjMjM4RZf4JsW8sB9hb2p&rc={$cur}");
							}elseif($cur == 'XPM'){
								curl_setopt($post, CURLOPT_POSTFIELDS, "address={$wall}&currency={$cur}&r=ANgi1XZD5NZwbryUptL7FoWbnvAMZJj86z&rc={$cur}");
							}elseif($cur == 'POT'){
								curl_setopt($post, CURLOPT_POSTFIELDS, "address={$wall}&currency={$cur}&r=PLp44G6qeuYDrwuxgKrc5yTAt1baa6oB2G&rc={$cur}");
							}
							curl_setopt($post, CURLOPT_USERAGENT, 'Mozilla/5.0 (Linux; Android 6.0; E1C 3G Build/MRA58K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/56.0.2924.87 Mobile Safari/537.36');
					$data = curl_exec($post);
					$info = curl_getinfo($post, CURLINFO_EFFECTIVE_URL);
							curl_close($post);
					$url = urlencode($info);
					$in = "$url\n";
					$f = fopen($name, 'a');
						fwrite($f, $in);
						fclose($f);
					echo "\n===============[GET URL FOUND]================\n";
					echo $in;
					echo "=============================================\n";
				}
			}elseif($com == '3'){
				echo "\n==========[ACCOUNT MANAGER MENU]==========\n";
				echo "=============================================\n\n[1]. Check Balance\n[2]. Send Balance\n[3]. Check Payout\n=============================================\n\n";
				echo "Please Select Number: ";
				$cm = trim(fgets(STDIN));
				if($cm == '1'){
					echo "\n============[CHECK BALANCE MENU]============\n";
					echo "=============================================\n\n[1]. Multiple Check Balance\n[2]. Single Check Balance\n=============================================\n\n";
					echo "Please Select Number:  ";
					$ca = trim(fgets(STDIN));
					if($ca == '1'){
						echo "Account Api Key List File: ";
						$ap= trim(fgets(STDIN));
						$cg = file_get_contents($ap);
						$cp = explode("\n", $cg);
						foreach($cp as $api){
							$currency = array("BCH","BLK","BTC","BTX","DASH","DOGE","ETH","LTC","PPC","XPM","POT");
							foreach($currency as $cy){
								$get = checkBalance($api, $cy);
								echo $get;
							}
						}
					}elseif($ca == '2'){
						echo "Account Api Key: ";
						$api= trim(fgets(STDIN));
						$currency = array("BCH","BLK","BTC","BTX","DASH","DOGE","ETH","LTC","PPC","XPM","POT");
						foreach($currency as $cy){
							$get = checkBalance($api, $cy);
							echo $get;
						}
					}
				}elseif($cm == '2') {
					echo "\n============[SEND BALANCE MENU]=============\n";
					echo "=============================================\n\n[1]. Multiple Account Sender\n[2]. Single Account Sender\n=============================================\n\n";
					echo "Please Select Number:  ";
					$se = trim(fgets(STDIN));
					if($se == '1'){
						echo "Sender Account Api Key list File: ";
						$sapi = trim(fgets(STDIN));
						echo "Receiver BCH wallet Address: ";
						$w[0] = trim(fgets(STDIN));
						echo "Receiver BLK wallet Address: ";
						$w[1] = trim(fgets(STDIN));
						echo "Receiver BTC wallet Address: ";
						$w[2] = trim(fgets(STDIN));
						echo "Receiver BTX wallet Address: ";
						$w[3] = trim(fgets(STDIN));
						echo "Receiver DASH wallet Address: ";
						$w[4] = trim(fgets(STDIN));
						echo "Receiver DOGE wallet Address: ";
						$w[5] = trim(fgets(STDIN));
						echo "Receiver ETH wallet Address: ";
						$w[6] = trim(fgets(STDIN));
						echo "Receiver LTC wallet Address: ";
						$w[7] = trim(fgets(STDIN));
						echo "Receiver PPC wallet Address: ";
						$w[8] = trim(fgets(STDIN));
						echo "Receiver XPM wallet Address: ";
						$w[9] = trim(fgets(STDIN));
						echo "Receiver POT wallet Address: ";
						$w[10] = trim(fgets(STDIN));
						$seg = file_get_contents($sapi);
						$sep = explode("\n", $seg);
						foreach($sep as $api){
							if(kirim($api, $w[0], 'BCH') && kirim($api, $w[1], 'BLK') && kirim($api, $w[2], 'BTC') && kirim($api, $w[3], 'BTX') && kirim($api, $w[4], 'DASH') && kirim($api, $w[5], 'DOGE') && kirim($api, $w[6], 'ETH') && kirim($api, $w[7], 'LTC') && kirim($api, $w[8], 'PPC') && kirim($api, $w[9], 'XPM') && kirim($api, $w[10], 'POT')){
								$cur = array('BCH','BLK','BTC','BTX','DASH','DOGE','ETH','LTC','PPC','XPM','POT');
								foreach($cur as $c){
									$h = checkBalance($api, $c);
									echo $h;
								}
							}
						}
					}elseif($se == '2'){
						echo "Sender Account Api Key: ";
						$api = trim(fgets(STDIN));
						echo "Receiver BCH wallet Address: ";
						$w[0] = trim(fgets(STDIN));
						echo "Receiver BLK wallet Address: ";
						$w[1] = trim(fgets(STDIN));
						echo "Receiver BTC wallet Address: ";
						$w[2] = trim(fgets(STDIN));
						echo "Receiver BTX wallet Address: ";
						$w[3] = trim(fgets(STDIN));
						echo "Receiver DASH wallet Address: ";
						$w[4] = trim(fgets(STDIN));
						echo "Receiver DOGE wallet Address: ";
						$w[5] = trim(fgets(STDIN));
						echo "Receiver ETH wallet Address: ";
						$w[6] = trim(fgets(STDIN));
						echo "Receiver LTC wallet Address: ";
						$w[7] = trim(fgets(STDIN));
						echo "Receiver PPC wallet Address: ";
						$w[8] = trim(fgets(STDIN));
						echo "Receiver XPM wallet Address: ";
						$w[9] = trim(fgets(STDIN));
						echo "Receiver POT wallet Address: ";
						$w[10] = trim(fgets(STDIN));
						if(kirim($api, $w[0], 'BCH') && kirim($api, $w[1], 'BLK') && kirim($api, $w[2], 'BTC') && kirim($api, $w[3], 'BTX') && kirim($api, $w[4], 'DASH') && kirim($api, $w[5], 'DOGE') && kirim($api, $w[6], 'ETH') && kirim($api, $w[7], 'LTC') && kirim($api, $w[8], 'PPC') && kirim($api, $w[9], 'XPM') && kirim($api, $w[10], 'POT')){
							$cur = array('BCH','BLK','BTC','BTX','DASH','DOGE','ETH','LTC','PPC','XPM','POT');
							foreach($cur as $c){
								$h = checkBalance($api, $c);
								echo $h;
							}
						}
					}
				}elseif($cm ='3'){
					echo "\n=============[CHECK PAYOUT MENU]=============\n";
					echo "=============================================\n\n[1]. Multiple Check\n[2]. Single Check\n=============================================\n\n";
					echo "Please Select Number:  ";
					$cep = trim(fgets(STDIN));
					if($cep == '1'){
						echo "Account Api Key list File: ";
						$pi = trim(fgets(STDIN));
						echo "Count Payout: ";
						$count = trim(fgets(STDIN));
						$ce = file_get_contents($pi);
						$pe = explode("\n", $ce);
						foreach($pe as $api){
							$py = payout($api, $count);
							echo $py;
						}
					}elseif($cep =='2'){
						echo "Account Api Key: ";
						$api = trim(fgets(STDIN));
						echo "Count Payout: ";
						$count = trim(fgets(STDIN));
						$py = payout($api, $count);
						echo $py;
					}
				}
			}elseif($com == '4'){
				echo "\n==============[FAUCET SITE LIST]===============\n";
				echo "=============================================\n\n";
				$site = file_get_contents('https://pastebin.com/raw/pVDLpEnG');
				echo $site;
				echo "\n=============================================\n";
			}elseif($com == '5'){
				echo "\n================[HELP MENU]==================\n";
				echo "=============================================\n[1]. Autoclaim Faucet\n[2]. Get Url\n[3]. Account Manager\n=============================================\n\nSelect Help Number: ";
				$ch = trim(fgets(STDIN));
				if($ch == '1'){
					echo "\n===========[HELP AUTOCLAIM FAUCET]==========\n";
					echo "=============================================\n\n";
					$h_au = file_get_contents('http://autoclaimfaucet.cf/help/autoclaim.txt');
					echo $h_au;
					echo "\n=============================================\n";
				}elseif($ch == '2'){
					echo "\n================[HELP GET URL]================\n";
					echo "=============================================\n\n";
					$h_gu = file_get_contents('http://autoclaimfaucet.cf/help/geturl.txt');
					echo $h_gu;
					echo "\n=============================================\n";
				}elseif($ch =='3'){
					echo "\n===========[HELP ACCOUNT MANAGER]==========\n";
					echo "=============================================\n\n";
					$h_am = file_get_contents('http://autoclaimfaucet.cf/help/account.txt');
					echo $h_am;
					echo "\n=============================================\n";
				}
			}elseif($com == '6'){
				echo "\n============[INFO UPDATE SCRIPT]==============\n";
				echo "=============================================\n\n";
				$site = file_get_contents('http://autoclaimfaucet.cf/info/update.txt');
				echo $site;
				echo "\n=============================================\n";
			}elseif($com == '7'){
				echo "\n================[CONTACT US]=================\n";
				echo "=============================================\n\n";
				echo "Name: ";
				$name = trim(fgets(STDIN));
				echo "Email: ";
				$email = trim(fgets(STDIN));
				echo "Message: ";
				$mess = trim(fgets(STDIN));
				$message.= "=========     Support AutoClaim Faucet    =========\n";
	            $message.= "Message\t ".$mess."\n";
	            $message.= "========= Enjoy Claim Faucet With AutoFaucet =========\n";
	            $subject = "Report Autoclaim Faucet";
	            $headers = "From: Support <report@autofaucet.today>\r\nReply-To: Akbar.FX23 <akbarfx23@gmail.com>\r\nCc: report@autofaucet.today\r\nBcc: report@autofaucet.today\r\n";
	             @mail('ckpakbar23@gmail.com',$subject,$message,$headers);
				echo $post;
				echo "\n=============================================\n";
			}
		}
		time_sleep_until($sleep); 
	}
}else{
	echo $RD."Wrong Password Please Try Again\n".$CC;
}
function claim($link){
$cok = tempnam('tmp','avo'.rand(1000000,9999999).'cookie.txt');
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $link);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
$ip = file('proxy.txt');
$po = explode("\n", $ip);
$proxy = $po[rand(0, count($po)-1)];
$type = null;
$proxytpe = ($type ? CURLPROXY_SOCKS5 : CURLPROXY_HTTP || $type ? CURLPROXY_HTTP : CURLPROXY_HTTPS );
if($proxy)
    {
        curl_setopt($ch, CURLOPT_PROXY, $proxy); 
        curl_setopt($ch, CURLOPT_PROXYTYPE, $proxytype);
    }
curl_setopt($ch, CURLOPT_REFERER, $link);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
curl_setopt($ch, CURLOPT_COOKIEJAR, $cok);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cok);
$data = curl_exec($ch);
curl_close($data);
$one = explode('<div class="alert alert-success">', $data);
$two = explode("<a target", $one[1]);
$inpoh=explode("https://faucethub.io/balance/",$data);
$wallet_inpoh=explode("\">your account at FaucetHub.io</a>",$inpoh[1]);
$CY="\e[36m"; $GR="\e[2;32m"; $OG="\e[92m"; $WH="\e[37m"; $RD="\e[31m"; $YL="\e[33m"; $BF="\e[34m"; $DF="\e[39m"; $OR="\e[33m"; $PP="\e[35m"; $B="\e[1m"; $CC="\e[0m";
$date = date('H:i:s');
if($two[0] == '' && $wallet_inpoh[0] == ''){
$pr = '';
}else{
$pr = $BF . "[".$date ."]". $CC." ~ ".$GR . $two[0] .$CC . $RD . $wallet_inpoh[0] . $CC."\n";
}
return $pr; 
}

function payout($api, $count) {
	$fp = New FaucetHub($api);
	$get = $fp->getPayouts($count);
	$rewards = $get["rewards"];
	return $rewards;
}

function kirim($api, $to, $cy){
$f_bch = new FaucetHub($api, $cy);
$bg_bch = $f_bch->getBalance();
$b_bch = $bg_bch["balance"];
return $f_bch->send($to, $b_bch);
}

function checkBalance($api, $cy){
$fh = new FaucetHub($api, $cy);
$bl = $fh->getBalance();
$st = $bl["balance"];
$gr = "Balance {$st} {$cy}\n";
return $gr;
}

function getProxy($url){
	$ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 Mozilla/5.0 (Windows; U; Windows NT 5.1; de; rv:1.9.2.13) Firefox/3.6.13');
    curl_setopt($ch, CURLOPT_COOKIEJAR, 'cookie');
    curl_setopt($ch, CURLOPT_COOKIEFILE, 'cookie');
    $get = curl_exec($ch);
    curl_close($ch);
    $one = explode('<pre class="alt2" dir="ltr" style="border: 1px inset ; margin: 0px; padding: 0px; overflow: auto; width: 500px; height: 300px; text-align: left;"><span style="color: #ffffff;"><span style="font-weight: bold;"></span><span style="font-weight: bold;">', $get);
    $two = explode('</span>', $one[1]);
    $data = $two[0];
    return $data;
    }   

function getSockProxy($url){
	$ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 Mozilla/5.0 (Windows; U; Windows NT 5.1; de; rv:1.9.2.13) Firefox/3.6.13');
    curl_setopt($ch, CURLOPT_COOKIEJAR, 'cookie');
    curl_setopt($ch, CURLOPT_COOKIEFILE, 'cookie');
    $get = curl_exec($ch);
    curl_close($ch);
    	$f = explode('<textarea onclick="this.focus();this.select()" style="font-size: 11pt; font-weight: bold; width: 500px; height: 300px; background-color: #000000; color: #0065dd;" wrap="hard">', $get);
        $tw = explode('</textarea><br><br>', $f[1]);
        $data= $tw[0];
    return $data;
    }   
    
function Geturl($url){
	$ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 Mozilla/5.0 (Windows; U; Windows NT 5.1; de; rv:1.9.2.13) Firefox/3.6.13');
    curl_setopt($ch, CURLOPT_COOKIEJAR, 'cookie');
    curl_setopt($ch, CURLOPT_COOKIEFILE, 'cookie');
    $get = curl_exec($ch);
    curl_close($ch);
	$f = explode("comment-link' href='".$url."/".date('Y')."/".date('m')."/".date('d')."-".date('m')."-".date('y')."-",  $get);
    $s = explode ("#comment-form'",$f[1]);
	$found = $url.'/'.date('Y').'/'.date('m').'/'.date('d').'-'.date('m').'-'.date('y').'-'.$s[0];
	return $found;
	}

?>