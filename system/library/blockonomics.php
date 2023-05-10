<?php

/**
 * Blockonomics Library for OpenCart
 */
class Blockonomics {

	/** @var int $version */
	public $version = '3.2.4';

	/** @var Registry $registry */
	private $registry;

	/** @var Log $logger */
	public $logger;

  /** @var string $blockonomics_websocket_url */
  public $blockonomics_websocket_url;

	/**
	 * Blockonomics Library constructor
	 * @param Registry $registry
	 */
	public function __construct($registry) {
		$this->registry = $registry;

		// Setup encryption
		$fingerprint = substr(sha1(sha1(__DIR__)), 0, 24);
    $this->encryption = new Encryption($fingerprint);

    // Setup logging
		$this->logger = new Log('blockonomics.log');

    $blockonomics_base_url = 'https://www.blockonomics.co';
    $this->blockonomics_websocket_url = 'wss://www.blockonomics.co';
    //$blockonomics_base_url = 'http://localhost:8080';
    //$this->blockonomics_websocket_url = 'ws://localhost:8080';
    $this->blockonomics_new_address_url = $blockonomics_base_url.'/api/new_address';
    $this->blockonomics_price_url = $blockonomics_base_url.'/api/price?currency=';
    $this->setting('debug', 0);
	}

	/**
	 * Magic getter for Registry items
	 *
	 * Allows use of $this->db instead of $this->registry->get('db') for example
	 *
	 * @return mixed
	 */
	public function __get($name) {
		return $this->registry->get($name);
	}

  /*
   * Make a request using curl
   */
  public function doCurlCall($url, $post_content = '')
  {
      $ch = curl_init();
      curl_setopt($ch, CURLOPT_URL, $url);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
      if ($post_content) {
          curl_setopt($ch, CURLOPT_POST, 1);
          curl_setopt($ch, CURLOPT_POSTFIELDS, $post_content);
      }
      curl_setopt($ch, CURLOPT_TIMEOUT, 60);
      curl_setopt(
          $ch,
          CURLOPT_HTTPHEADER,
          [
              'Authorization: Bearer ' . $this->setting('api_key'),
              'Content-type: application/x-www-form-urlencoded',
          ]
      );
      $data = curl_exec($ch);
      $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
      curl_close($ch);

      $responseObj = new stdClass();
      $responseObj->data = json_decode($data);
      $responseObj->response_code = $httpcode;
      return $responseObj;
  }

  public function getBTCPrice() {
    //Getting price
    $currency_code = $this->config->get('config_currency');
    $url = $this->blockonomics_price_url.$currency_code;

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);

    $data = curl_exec($ch);
    curl_close($ch);

    $responseObj = json_decode($data);
    if (!isset($responseObj)) {
      return '';
    }

    return $responseObj->price;
  }

  /**
   * Logs with an arbitrary level.
   * @param string $level The type of log.
   *						Should be 'error', 'warn', 'info', 'debug', 'trace'
   *						In normal mode, 'error' and 'warn' are logged
   *						In debug mode, all are logged
   * @param string $message The message of the log
   * @param int $depth How deep to go to find the calling function
   * @return void
   */
  public function log($level, $message, $depth = 0) {
    $level = strtoupper($level);
    $prefix = '[' . $level . ']';

    if ($this->setting('debug') === '1') {
      $depth += 1;
      $prefix .= '{';
      $backtrace = debug_backtrace();
      if (isset($backtrace[$depth]['class'])) {
        $class = preg_replace('/[a-z]/', '', $backtrace[$depth]['class']);
        $prefix .= $class . $backtrace[$depth]['type'];
      }
      if (isset($backtrace[$depth]['function'])) {
        $prefix .= $backtrace[$depth]['function'];
      }
      $prefix .= '}';
    }

    if ('ERROR' === $level || 'WARN' === $level || $this->setting('debug') === '1') {
      $this->logger->write($prefix . ' ' . $message);
    }
  }

  /*
   * Get new address from Blockonomics Api
   */
  public function getNewAddress($reset = false)
  {
      $api_key = $this->setting('api_key');
      $callback_secret = $this->setting('callback_secret');

      if ($reset) {
          $get_params = "?match_callback=$callback_secret&reset=1";
      } else {
          $get_params = "?match_callback=$callback_secret";
      }

      $ch = curl_init();

      curl_setopt($ch, CURLOPT_URL, 'https://www.blockonomics.co/api/new_address' . $get_params);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
      curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');

      $header = 'Authorization: Bearer ' . $api_key;
      $headers = [];
      $headers[] = $header;
      curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

      $contents = curl_exec($ch);
      if (curl_errno($ch)) {
          exit('Error:' . curl_error($ch));
      }

      $responseObj = json_decode($contents);
      //Create response object if it does not exist
      if (!isset($responseObj)) {
          $responseObj = new stdClass();
      }
      $responseObj->{'response_code'} = curl_getinfo($ch, CURLINFO_HTTP_CODE);
      curl_close($ch);
      return $responseObj;
  }

  public function get_callbacks(){
    $url =  $this->blockonomics_get_callback_url;

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
      'Authorization: Bearer '.$this->setting('api_key'),
      'Content-type: application/x-www-form-urlencoded'
    ));

    $callback_data = curl_exec($ch);
    $callback_httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $callback_responseObj = json_decode($callback_data);
    $response["httpcode"] = $callback_httpcode;
      foreach($callback_responseObj as $key => $value){
        if(isset($value->callback)){
          $response[$key] = new stdClass();
          $response[$key]->callback = $value->callback;
          $response[$key]->address = $value->address;
        }
      }

    return $response;
  }

  public function testsetup()
  {
    $xpub_fetch_url = 'https://www.blockonomics.co/api/address?&no_balance=true&only_xpub=true&get_callback=true';
    $set_callback_url = 'https://www.blockonomics.co/api/update_callback';
    $error_str = '';

    $response = $this->doCurlCall($xpub_fetch_url);

    $secret = $this->setting('callback_secret');
    $callback_url = htmlspecialchars_decode($this->setting('callback_url'));
    
    if (!isset($response->response_code)) {
        $error_str = 'blockedHttps';
    } elseif ($response->response_code == 401) {
        $error_str = 'incorrectApi';
    } elseif ($response->response_code != 200) {
        $error_str = $response->data;
    } elseif (!isset($response->data) || count($response->data) == 0) {
        $error_str = 'noXpub';
    } elseif (count($response->data) == 1) {
        if (!$response->data[0]->callback || $response->data[0]->callback == null) {
            //No callback URL set, set one
            $post_content = '{"callback": "' . $callback_url . '", "xpub": "' . $response->data[0]->address . '"}';
            $this->doCurlCall($set_callback_url, $post_content);
        } elseif ($response->data[0]->callback != $callback_url) {
            // Check if only secret differs
            $base_url = substr($callback_url, 0, -48);
            if (strpos($response->data[0]->callback, $base_url) !== false) {
                //Looks like the user regenrated callback by mistake
                //Just force Update_callback on server
                $post_content = '{"callback": "' . $callback_url . '", "xpub": "' . $response->data[0]->address . '"}';
                $this->doCurlCall($set_callback_url, $post_content);
            } else {
                $error_str = 'existingCallbackUrl';
            }
        }
    } else {
        $error_str = 'multipleXpubs';

        foreach ($response->data as $resObj) {
            if ($resObj->callback == $callback_url) {
                // Matching callback URL found, set error back to empty
                $error_str = '';
            }
        }
    }

    if ($error_str == '') {
        // Test new address generation
        $new_addresss_response = $this->getNewAddress(true);
        if ($new_addresss_response->status != 200) {
            $error_str = $new_addresss_response->message;
        }
    }

    return $error_str;

  }

	/**
	 * Constructs some helpful diagnostic info.
	 * @return string
	 */
	public function getServerInfo() {
		$gmp	= extension_loaded('gmp') ? 'enabled' : 'missing';
		$bcmath = extension_loaded('bcmath') ? 'enabled' : 'missing';
		$info   = "<pre><strong>Server Information:</strong>\n" .
					"PHP: " . phpversion() . "\n" .
					"PHP-GMP: " . $gmp . "\n" .
					"PHP-BCMATH: " . $bcmath . "\n" .
					"OpenCart: " . VERSION . "\n" .
					"Blockonomics Plugin: " . $this->version . "\n" .
					"Blockonomics Lib: v2.2.20\n";
		return $info;
	}

  /**
	 * Better setting method for blockonomics settings
	 *
	 * Automatically persists to database on set and combines getting and setting into one method
	 * Assumes blockonomics_ prefix
	 *
	 * @param string $key Setting key
	 * @param string $value Setting value if setting the value
	 * @return string|null|void Setting value, or void if setting the value
	 */
	public function setting($key, $value = null) {
		// Normalize key
		$code = 'payment_blockonomics';
		$key = $code.'_' . $key;

		// Set the setting
		if (func_num_args() === 2) {
			if (!is_array($value)) {
				$this->db->query("UPDATE " . DB_PREFIX . "setting SET `value` = '" . $this->db->escape($value) . "', serialized = '0' WHERE `code` = '".$code."' AND `key` = '" . $this->db->escape($key) . "' AND store_id = '0'");
			} else {
				$this->db->query("UPDATE " . DB_PREFIX . "setting SET `value` = '" . $this->db->escape(serialize($value)) . "', serialized = '1' code `group` = 'blockonomics' AND `key` = '" . $this->db->escape($key) . "' AND store_id = '0'");
			}
			return $this->config->set($key, $value);
		}

		// Get the setting
		return $this->config->get($key);
	}
}
