<?php


namespace botnyx\tmidpconn;



class idpconn {

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
			'base_uri' => 'https://idp.trustmaster.nl',
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
		$options = [];
		$response = $this->call('GET',$path,$options);
		return array("code"=>$response->getStatusCode(),"data"=>$response->getBody()->getContents());
	}
	public function post($path,$json=array()){
		error_log("idpconn->post()");
		$options = ['json' => $json];
		$response = $this->call('POST',$path,$options);
		return array("code"=>$response->getStatusCode(),"data"=>$response->getBody()->getContents());
	}
	public function put($path,$json=array()){
		$options = ['json' => $json];
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


	public function oauthLogin($user,$pass){
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
				'grant_type' => 'password',
				'username' => $user,
				'password' => $pass
			]
		]);
		
		
		#print_r($response->getStatusCode());
		$resp =	json_decode($response->getBody()->getContents(),true);
		
		#print_r($resp);
		#die();
		
		$status = array('code'=>$response->getStatusCode() , 'data'=>$resp);
		return $status;
		
		
	}

}
