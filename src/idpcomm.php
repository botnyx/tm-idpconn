<?php


namespace botnyx\tmidpcomm;



class idpcomm {

	var $client_id;
	var $client_secret;

	var $accessToken ;
	var $idpServer;

	function __construct($server,$clientid,$clientsecret){

		$this->client_id 	= $clientid;
		$this->client_secret= $clientsecret;
		$this->idpServer = $server;


		// read the token, or fetch it.
		//if(file_exists("../idpComm.token")){
			//error_log('idpComm.token exists()');
		//	$tokenData=json_decode($this->readToken(),true);
		//	error_log("Using cached token");
		//}else{
			$tokenData=$this->requestToken();
			//$this->writeToken(json_encode($tokenData));
		//}

		// set the accesstoken var.
		$this->accessToken=$tokenData['access_token'];
		// create client with correct token
		$this->createClient();


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

		$response = $this->client->request(strtoupper($type), $this->idpServer.$path,$options);
		if($response->getStatusCode()==500){
			throw new \Exception("500 IDP Server error");
	   	}
		#error_log($response->getStatusCode()." call(".$type.",".$path.")");
		$tt = $response->getBody()->getContents();
		#error_log(">>".$tt);
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
			throw new Exception("500 IDP Server error");
	   	}
		return $response;
	}



}
