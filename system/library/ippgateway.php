<?php
namespace Opencart\System\Library;
class IPPGateway {
	private $server = array(
		'sandbox' => 'https://api.sandbox.ippeurope.com',
		'production' => 'https://api.ippeurope.com'
	);
	private $environment = 'production';
	private $client_id = '';
	private $secret = '';
	private $errors = array();
	private $last_response = array();
		
	//IN:  ippgateway info
	public function __construct($ippgateway_info) {
		if (!empty($ippgateway_info['client_id'])) {
			$this->client_id = $ippgateway_info['client_id'];
		}

		if (!empty($ippgateway_info['secret'])) {
			$this->secret = $ippgateway_info['secret'];
		}

		if (!empty($ippgateway_info['environment']) && (($ippgateway_info['environment'] == 'production') || ($ippgateway_info['environment'] == 'sandbox'))) {
			$this->environment = $ippgateway_info['environment'];
		}
	}

	//OUT: number of errors
	public function hasErrors()	{
		return count($this->errors);
	}
	
	//OUT: array of errors
	public function getErrors()	{
		return $this->errors;
	}
	
	//OUT: last response
	public function getResponse() {
		return $this->last_response;
	}

    public function checkout_id($data){
        return $this->execute("GET", "/payments/checkout_id", $data)->content;
    }
    public function rebilling($data){
        return $this->execute("GET", "/payments/rebilling", $data)->content;
    }
    public function payment_status($transaction_id,$transaction_key){
        $data = ["transaction_id" => $transaction_id, "transaction_key" => $transaction_key];
        return $this->execute("GET","/payments/status", $data, true)->content;
    }

	private function execute($method, $command, $params = array(), $json = false) {
		$this->errors = array();

		if ($method && $command) {
            $params["id"] = $this->client_id;
            $params["key2"] = $this->secret;
            $params["origin"] = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]";

			$curl_options = array(
				CURLOPT_URL => $this->server[$this->environment] . $command,
				CURLOPT_HEADER => true,
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_INFILESIZE => Null,
				CURLOPT_HTTPHEADER => array(),
				CURLOPT_CONNECTTIMEOUT => 10,
				CURLOPT_TIMEOUT => 10
			);
			
			$curl_options[CURLOPT_HTTPHEADER][] = 'Accept-Charset: utf-8';
			$curl_options[CURLOPT_HTTPHEADER][] = 'Accept: application/json';
			$curl_options[CURLOPT_HTTPHEADER][] = 'Accept-Language: en_US';
			$curl_options[CURLOPT_HTTPHEADER][] = 'Content-Type: application/json';
			$curl_options[CURLOPT_HTTPHEADER][] = 'IPPGateway-Request-Id: ' . $this->token(50);

			switch (strtolower(trim($method))) {
				case 'get':
					$curl_options[CURLOPT_HTTPGET] = true;
					$curl_options[CURLOPT_URL] .= '?' . $this->buildQuery($params, $json);
										
					break;
				case 'post':
					$curl_options[CURLOPT_POST] = true;
					$curl_options[CURLOPT_POSTFIELDS] = $this->buildQuery($params, $json);
										
					break;
				case 'patch':
					$curl_options[CURLOPT_POST] = true;
					$curl_options[CURLOPT_POSTFIELDS] = $this->buildQuery($params, $json);
					$curl_options[CURLOPT_CUSTOMREQUEST] = strtoupper($method);
										
					break;
				case 'delete':
					$curl_options[CURLOPT_POST] = true;
					$curl_options[CURLOPT_POSTFIELDS] = $this->buildQuery($params, $json);
					$curl_options[CURLOPT_CUSTOMREQUEST] = strtoupper($method);
										
					break;
				case 'put':
					$curl_options[CURLOPT_PUT] = true;
					
					if ($params) {
						if ($buffer = fopen('php://memory', 'w+')) {
							$params_string = $this->buildQuery($params, $json);
							fwrite($buffer, $params_string);
							fseek($buffer, 0);
							$curl_options[CURLOPT_INFILE] = $buffer;
							$curl_options[CURLOPT_INFILESIZE] = strlen($params_string);
						} else {
							$this->errors[] = array('name' => 'FAILED_OPEN_TEMP_FILE', 'message' => 'Unable to open a temporary file');
						}
					}
					
					break;
				case 'head':
					$curl_options[CURLOPT_NOBODY] = true;
					
					break;
				default:
					$curl_options[CURLOPT_CUSTOMREQUEST] = strtoupper($method);
			}
			
			$ch = curl_init();
			curl_setopt_array($ch, $curl_options);
			$response = curl_exec($ch);
			
			if (curl_errno($ch)) {
				$curl_code = curl_errno($ch);
				
				$constant = get_defined_constants(true);
				$curl_constant = preg_grep('/^CURLE_/', array_flip($constant['curl']));
				
				$this->errors[] = array('name' => $curl_constant[$curl_code], 'message' => curl_strerror($curl_code));
			}

			$head = '';
			$body = '';
			
			$parts = explode("\r\n\r\n", $response, 3);
			
			if (isset($parts[0]) && isset($parts[1])) {
				if (($parts[0] == 'HTTP/1.1 100 Continue') && isset($parts[2])) {
					list($head, $body) = array($parts[1], $parts[2]);
				} else {
					list($head, $body) = array($parts[0], $parts[1]);
				}
            }
			
            $response_headers = array();
            $header_lines = explode("\r\n", $head);
            array_shift($header_lines);
			
            foreach ($header_lines as $line) {
                list($key, $value) = explode(':', $line, 2);
                $response_headers[$key] = $value;
            }
			
			curl_close($ch);
			
			if (isset($buffer) && is_resource($buffer)) {
                fclose($buffer);
            }

			$this->last_response = json_decode($body, true);
			
			return $this->last_response;		
		}
	}
	
	private function buildQuery($params, $json) {
		if (is_string($params)) {
            return $params;
        }
		
		if ($json) {
			return json_encode($params);
		} else {
			return http_build_query($params);
		}
    }
	
	private function token($length = 32) {
		// Create random token
		$string = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
	
		$max = strlen($string) - 1;
	
		$token = '';
	
		for ($i = 0; $i < $length; $i++) {
			$token .= $string[mt_rand(0, $max)];
		}	
	
		return $token;
	}
}