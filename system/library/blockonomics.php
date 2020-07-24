<?php

/**
 * Blockonomics Library for OpenCart
 */
class Blockonomics {

	/** @var int $version */
	public $version = '0.2.0';

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
    $this->blockonomics_get_callback_url = $blockonomics_base_url.'/api/address?&no_balance=true&only_xpub=true&get_callback=true';
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

  public function genBTCAddress(){
    $url = $this->blockonomics_new_address_url."?match_callback=".$this->setting('callback_url');

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    curl_setopt($ch, CURLOPT_POSTFIELDS, "");
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
      'Authorization: Bearer '.$this->setting('api_key'),
      'Content-type: application/x-www-form-urlencoded'
    ));

    $data = curl_exec($ch);
    $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $responseObj = json_decode($data);
    if($httpcode != 200) {
      if (isset($responseObj->message)) {
        if ($responseObj->message=='Could not find matching xpub') {
          $responseObj->error = 'There is a problem in your callback url';
        } else {
          $responseObj->error = $responseObj->message;
        }
      }
      if($httpcode == 401) {
        $responseObj = new stdClass();
        $responseObj->error = 'API Key is invalid';
      }
    }

    return $responseObj;
  }

  public function testsetup()
  {
    $url = $this->blockonomics_new_address_url."?match_callback=".$this->setting('callback_url');
    $callback_url =  $this->blockonomics_get_callback_url;

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    curl_setopt($ch, CURLOPT_POSTFIELDS, "");
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
      'Authorization: Bearer '.$this->setting('api_key'),
      'Content-type: application/x-www-form-urlencoded'
    ));
    $data = curl_exec($ch);
    $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $callback_ch = curl_init();
    curl_setopt($callback_ch, CURLOPT_URL, $callback_url);
    curl_setopt($callback_ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($callback_ch, CURLOPT_HTTPHEADER, array(
      'Authorization: Bearer '.$this->setting('api_key'),
      'Content-type: application/x-www-form-urlencoded'
    ));

    $callback_data = curl_exec($callback_ch);
    $callback_httpcode = curl_getinfo($callback_ch, CURLINFO_HTTP_CODE);
    curl_close($callback_ch);

    $responseObj = json_decode($data);
    $callback_responseObj = json_decode($callback_data);

    if($httpcode != 200) {
      if (isset($responseObj->message)) {
        if ($responseObj->message=='Could not find matching xpub' ) {
          $responseObj->error = 'There is a problem in your callback url';
        } else {
          $responseObj->error = $responseObj->message;
        }
      }
      if($httpcode == 401) {
        $responseObj = new stdClass();
        $responseObj->error = 'API Key is invalid';
      }

      $responseerror = $responseObj->error;
      return $responseerror;
    }
    $callback_url_without_schema = preg_replace('/https?:\/\//', '', $this->setting('callback_url'));
    $response_callback_without_schema = preg_replace('/https?:\/\//', '', $callback_responseObj[0]->callback);

    if(levenshtein(trim($callback_url_without_schema), trim($response_callback_without_schema)) > 4){
      $responseObj->error = 'You have an existing callback URL. Refer instructions on integrating multiple websites';
      $responseerror = $responseObj->error;
      return $responseerror;
    }

    return false;
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
