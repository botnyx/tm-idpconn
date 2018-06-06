<?php


namespace botnyx\tmidpconn;



class idpconn {
	
	
	var $authorize_endpoint = "/authorize";
	var $token_endpoint		= "/token";
	
	
	var $client_id;
	var $client_secret;

	var $accessToken ;
	var $idpServer;

	function __construct($server,$clientid,$clientsecret){

		$this->client_id 	= $clientid;
		$this->client_secret= $clientsecret;
		$this->idpServer = $server;


		// read the token, or fetch it.
		if(file_exists("../idpComm.token")){
			//error_log('idpComm.token exists()');
			$tokenData=json_decode($this->readToken(),true);
			error_log("Using cached token");
		}else{
			$tokenData=$this->requestToken();
			$this->writeToken(json_encode($tokenData));
		}

		// set the accesstoken var.
		$this->accessToken=$tokenData['access_token'];
		// create client with correct token
		$this->createClient();


	}
	public function getRefreshToken($rt){
		//curl -u TestClient:TestSecret https://api.mysite.com/token -d 'grant_type=refresh_token&refresh_token=tGzv3JOkF0XG5Qx2TlKWIA'
		// grant_type=password
		// client_id
		
		// username
		// password
		$client = new \GuzzleHttp\Client();
		$response = $client->request('POST', $this->idpServer.'/token', [
			'timeout' => 5,
			'http_errors' => false,
			'auth' => [$this->client_id, $this->client_secret],
			'form_params' => [
				'grant_type' => 'refresh_token',
				'refresh_token' => $rt
			],
			'headers' => [
        		'User-Agent' => 'trustmaster/1.0'
			]
		]);
		
		
		#print_r($response->getStatusCode());
		$resp =	json_decode($response->getBody()->getContents(),true);
		
		#print_r($resp);
		#die();
		
		$status = array('code'=>$response->getStatusCode() , 'data'=>$resp);
		return $status;
	}
	
	public function oauthLogin($user,$pass){
		// grant_type=password
		// client_id
		
		// username
		// password
		$client = new \GuzzleHttp\Client();
		#echo "<pre>";
		$options = [
			'timeout' => 5,
			'http_errors' => false,
			'auth' => [$this->client_id, $this->client_secret],
			'headers' => [
        		'User-Agent' => 'trustmaster/1.0'
			],
			'form_params' => [
				'grant_type' => 'password',
				'username' => $user,
				'password' => $pass
			]
		];
		#print_r($options);
		#echo "<pre>";
		$response = $client->request('POST', $this->idpServer.$this->token_endpoint, $options);
		
		
		#print_r($response->getStatusCode());
		$resp =	json_decode($response->getBody()->getContents(),true);
		
		#print_r($resp);
		#die();
		
		$status = array('code'=>$response->getStatusCode() , 'data'=>$resp);
		return $status;
		
		
	}	

	
	public function OLDSHIT($authorized,$client_id,$user_id){
		#if($this->debug) {
		//	error_log("receiveCode(): Exchange code for token");
		#	echo " cookiemanager->receiveAuthCode($code)";
		#}
		echo "<pre>";
		#var_dump($this->client_id);
		#var_dump($this->client_secret);
		$this->server= "https://idp.trustmaster.nl";
		var_dump($this->server);
		#var_dump($code);
		
		$idp = new \botnyx\tmidpconn\idpconn($this->server,$this->client_id,$this->client_secret);
		
		
		$result = $idp->receiveAuthCode($code );
		#var_dump($result);
		if($result['code']!=200){
			var_dump($result);
			#$result['code'];
			#$result['data']['error'];
			#$result['data']['error_description'];
			return false;
		}else{
			
			
			if( $this->verifyJWT($result['data']['access_token'])){
				// jwt is ok, setcookie.
				if($this->debug) error_log("JWT validated, setcookies! ");

				$this->setNewCookies($result['data']);
				#print_r($result);
				#print_r($this->payload);
				#die();
				return true;
			}else{
				// jwt decoding failed!
				return false;
			}
			
			
			#$result['code'];
			#$result['data']['access_token'];
			#$result['data']['expires_in'];		
			#$result['data']['token_type'];		
			#$result['data']['scope'];		
			#$result['data']['refresh_token'];		
		}
		
		
		
		
		
	}
	
	
	
	public function receiveAuthCode($authorized,$client_id,$user_id){
		
		
		//POST=>
		// https://idp.trustmaster.nl/authorize?response_type=code&client_id=jerryhopper.com&state=1528031546&user_id=1234
		$state = time();
		
		$url = $this->idpServer.$this->authorize_endpoint."?response_type=code&client_id=".$client_id."&state=".$state."&user_id=".$user_id;
		echo "receiveAuthCode posts :\n";
		echo $url."\n";
		$client = new \GuzzleHttp\Client();
		#echo "<pre>";
		$options = [
			'timeout' => 5,
			
			'http_errors' => false,
			'form_params' => [
				'authorized' => $authorized
			],
			'allow_redirects' => false,
			'headers' => [
        		'User-Agent' => 'trustmaster/1.0',
				'Accept'     => 'application/json',
				'Authorization'=> 'Bearer '.$this->accessToken
			],
		];
		
		#print_r($options);
		#echo "<pre>";
		$response = $client->request('POST', $url , $options);
		
		
		if($response->getStatusCode()==302){
			
			
			
			$result_array['url']=$response->getHeader('Location')[0];
			
			$status = array('code'=>$response->getStatusCode(),"data"=>$result_array);
		}else{
			$status = array('code'=>$response->getStatusCode(),"data"=>json_decode($response->getBody()->getContents()));
			
		}
		return $status;		
	}
	
		
	public function exchangeAuthCode($code){
		// Exchange a received auth code for a token.
		//curl -u TestClient:TestSecret https://api.mysite.com/token -d 'grant_type=authorization_code&code=xyz'
		$client = new \GuzzleHttp\Client();
		#echo "<pre>";
		$options = [
			'timeout' => 5,
			'debug' => false,
			'http_errors' => false,
			'auth' => [$this->client_id, $this->client_secret],
			'form_params' => [
				'grant_type' => 'authorization_code',
				'code' =>$code
			],
			'headers' => [
        		'User-Agent' => 'trustmaster/1.0'
			]
		];
		
		
		#print_r($options);
		echo "<pre>";
		echo $this->idpServer.$this->token_endpoint;
		$response = $client->request('POST', $this->idpServer.$this->token_endpoint, $options);
		
		
		print_r($response->getBody()->getContents());
		die("___");
		
		#print_r($response->getStatusCode());
		$resp =	json_decode($response->getBody()->getContents(),true);
		
		#print_r($resp);
		#die();
		
		$status = array('code'=>$response->getStatusCode() , 'data'=>$resp);
		return $status;
		
		
		
		
	}
	
	
	
	
	public function setLogger($logger){
		$this->logger=$log;
	}
	
	private function createClient(){
		$this->client = new \GuzzleHttp\Client([
			// Base URI is used with relative requests
			'base_uri' => $this->idpServer,
			// You can set any number of default request options.
			'headers' => [
        		'User-Agent' => 'trustmaster/1.0',
				'Accept'     => 'application/json',
				'Authorization'=> 'Bearer '.$this->accessToken
			],
			'debug' => false,
			'connect_timeout' => 3.14,
			'timeout' => 3.14,
			'allow_redirects'=>[
				'protocols'=>['https']
			],
			'http_errors' => false
		]);
	}

	private function writeToken($data){
		return;
		$filename = "../idpComm.token";
		$handle = fopen($filename, "w");
		$contents = fwrite($handle, $data);
		fclose($handle);
	}
	
	private function readToken(){
		// get contents of a file into a string
		$filename = "../idpComm.token";
		$handle = fopen($filename, "r");
		$contents = fread($handle, filesize($filename));
		fclose($handle);
		//error_log("readToken: ".$contents);
		return $contents;
	}

	private function requestToken(){
		
		$client = new \GuzzleHttp\Client([
			// Base URI is used with relative requests
			'base_uri' => $this->idpServer,
			// You can set any number of default request options.
			'headers' => [
        		'User-Agent' => 'trustmaster/1.0',
				'Accept'     => 'application/json'
			],
			'connect_timeout' => 3.14,
			'timeout' => 3.14,
			'allow_redirects'=>[
				'protocols'=>['https']
			],
			'http_errors' => false
		]);
		
		
		$response = $client->request('POST', $this->idpServer.'/token',[
				'auth'=>[$this->client_id,$this->client_secret],
				'json'=>['grant_type'=>'client_credentials']
			]);

		$tokenData = json_decode($response->getBody()->getContents(),true);
		#error_log($response->getStatusCode()." requestToken()" );
		if($response->getStatusCode()==200){

			$tokenData['expires'] = time()+(int)$tokenData['expires_in'];
			return $tokenData;
		}
		throw new \Exception($response->getStatusCode()." ".$tokenData['error_description']);
	}


	public function get($path){
		$options = ['headers' => ['User-Agent' => 'trustmaster/1.0']];
		$response = $this->call('GET',$path,$options);
		return array("code"=>$response->getStatusCode(),"data"=>$response->getBody()->getContents());
	}
	
	public function post($path,$json=array()){
		error_log("idpconn->post()");
		$options = ['json' => $json,'headers' => ['User-Agent' => 'trustmaster/1.0']];
		$response = $this->call('POST',$path,$options);
		return array("code"=>$response->getStatusCode(),"data"=>$response->getBody()->getContents());
	}
	
	public function put($path,$json=array()){
		$options = ['json' => $json,'headers' => ['User-Agent' => 'trustmaster/1.0']];
		$response = $this->call('PUT',$path,$options);
		return array("code"=>$response->getStatusCode(),"data"=>$response->getBody()->getContents());
	}

	
	private function call($type='GET',$path,$options){
		error_log("idpconn->call($type)");
		$response = $this->client->request(strtoupper($type), $this->idpServer.$path,$options);
		
		error_log( "this->client->request(".strtoupper($type).", ".$this->idpServer.$path.",".json_encode($options).")");
				  
		if($response->getStatusCode()==500){
			return $response;
			//error_log($response->getBody()->getContents());
			//throw new \Exception($response->getBody()->getContents());
	   	}
		#error_log($response->getStatusCode()." call(".$type.",".$path.")");
		$tt = $response->getBody()->getContents($response->getHeaderLine('Content-Length'));
		error_log(">>".$tt);
		$responseJson = json_decode($tt ,true);
		if(array_key_exists('error_description',$responseJson) && $responseJson['error_description']=="The access token provided has expired" ){
		
			#if($responseJson['error_description']=="The access token provided has expired"){
			$tokenstruct = $this->requestToken();
			$this->writeToken(json_encode($tokenstruct) );
			$this->accessToken=$tokenstruct['access_token'];
			$this->createClient();
		}

		$response = $this->client->request(strtoupper($type), $this->idpServer.$path,$options);
		if($response->getStatusCode()==500){
			//error_log($response->getBody()->getContents());
			//throw new \Exception($response->getBody()->getContents());
	   	}
		return $response;
	}




}
