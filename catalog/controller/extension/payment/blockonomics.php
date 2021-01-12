<?php
/**
 * Blockonomics Payment Controller
 */
class ControllerExtensionPaymentBlockonomics extends Controller {

	/** @var boolean $ajax Whether the request was made via AJAX */
	private $ajax = false;

	/** @var BlockonomicsLibrary $blockonomics */
	private $blockonomics;

	/**
	 * Blockonomics Payment Controller Constructor
	 * @param Registry $registry
	 */
	public function __construct($registry) {
		parent::__construct($registry);

		// Make langauge strings and Blockonomics Library available to all
		$this->load->language('extension/payment/blockonomics');
		$this->blockonomics = new Blockonomics($registry);

    // Setup logging
		$this->logger = new Log('blockonomics.log');

		// Is this an ajax request?
		if (!empty($this->request->server['HTTP_X_REQUESTED_WITH']) &&
			strtolower($this->request->server['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest')
		{
			$this->ajax = true;
		}
	}

	/**
	 * Displays the Payment Method (a redirect button)
	 * @return void
	 */
	public function index() {

		$data['url_redirect'] = $this->url->link('extension/payment/blockonomics/invoice', $this->config->get('config_secure'));
		$data['button_confirm'] = $this->language->get('button_confirm');

		if (isset($this->session->data['error_blockonomics'])) {
			unset($this->session->data['error_blockonomics']);
		}

		if (file_exists(DIR_TEMPLATE . $this->config->get('config_template') . '/template/extension/payment/blockonomics')) {
			return $this->load->view($this->config->get('config_template') . '/template/extension/payment/blockonomics', $data);
		} else {
			return $this->load->view('extension/payment/blockonomics', $data);
		}
	}

  /**
   * Convenience wrapper for logs
   * @param string $level The type of log.
   *					  Should be 'error', 'warn', 'info', 'debug', 'trace'
   *					  In normal mode, 'error' and 'warn' are logged
   *					  In debug mode, all are logged
   * @param string $message The message of the log
   * @param int $depth Depth addition for debug backtracing
   * @return void
   */
  public function log($level, $message, $depth = 0) {
    $depth += 1;
    $this->blockonomics->log($level, $message, $depth);
  }

	/**
	 * Generatew new bitcoin addres and show the invoice
	 * @return void
	 */
	public function invoice() {

		$this->load->model('checkout/order');

		if (!isset($this->session->data['order_id'])) {
			$this->response->redirect($this->url->link('checkout/cart'));
			return;
		}
		$order_info = $this->model_checkout_order->getOrder($this->session->data['order_id']);
		if (false === $order_info) {
			$this->response->redirect($this->url->link('checkout/cart'));
			return;
		}

		$this->document->setTitle($this->language->get('text_title'));

		$data['breadcrumbs'] = array();

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/home')
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_basket'),
			'href' => $this->url->link('checkout/cart')
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_checkout'),
			'href' => $this->url->link('checkout/checkout', '', true)
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_invoice'),
			'href' => $this->url->link('extension/payment/blockonomics/invoice')
		);

    if (empty($order_info['currency_code'])) {
			$this->log('error', 'Cannot prepare invoice without `currency_code`');
			throw Exception('Cannot prepare invoice without `currency_code`');
		}

		if (empty($order_info['total'])) {
			$this->log('error', 'Cannot prepare invoice without `total`');
			throw Exception('Cannot prepare invoice without `total`');
		}
		
		$this->document->addScript('catalog/view/javascript/qrcode.min.js');
		$this->document->addScript('catalog/view/javascript/reconnecting-websocket.min.js');
		
    $data['column_left'] = $this->load->controller('common/column_left');
		$data['column_right'] = $this->load->controller('common/column_right');
		$data['content_top'] = $this->load->controller('common/content_top');
		$data['content_bottom'] = $this->load->controller('common/content_bottom');
		$data['footer'] = $this->load->controller('common/footer');
		$data['header'] = $this->load->controller('common/header');

    $data['currency_code'] = $this->config->get('config_currency');
    $price = $this->blockonomics->getBTCPrice();

    $fiat_amount = $order_info['total'];
    $bits = intval(1.0e8*$fiat_amount/$price);
    $satoshi_amount = $bits/1.0e8;
    $data['satoshi_amount'] = $satoshi_amount;
    $data['fiat_amount'] = $fiat_amount;
    $order_id = $order_info['order_id'];
    
    $this->model_checkout_order->addOrderHistory($order_id, 1, "", true);

    $current_time = time();
    $data['orderTimestamp'] = $current_time;
    $data['time_period'] = $this->setting('time_period');
    $data['order_id'] = $order_id;

    $data['success_url'] = $this->url->link('checkout/success');
    $data['websocket_url'] = $this->blockonomics->blockonomics_websocket_url;
    $data['timeout_url'] = $this->url->link('extension/payment/blockonomics/timeout', $this->config->get('config_secure'));
    
    $sql = $this->db->query("SELECT * FROM ".DB_PREFIX."blockonomics_bitcoin_orders WHERE `id_order` = '".$order_id."' LIMIT 1");
    $order = $sql->row;

    // If there is no existing order in database, generate Bitcoin address
    if(!isset($order['id_order'])){
      $response = $this->blockonomics->genBTCAddress();
      if(!isset($response->error)) {
        $btc_address=$response->address;
        $data['btc_address'] = $btc_address;
        $data['btc_href'] = "bitcoin:".$btc_address."?amount=".$satoshi_amount;

        $this->blockonomics->log('info', $btc_address, 1);
        $this->blockonomics->log('info', $price, 1);

        //Insert into blockonomics orders table
        $this->db->query("INSERT IGNORE INTO ".DB_PREFIX."blockonomics_bitcoin_orders (id_order, timestamp,  addr, txid, status,value, bits, bits_payed) VALUES
          ('".(int)$order_id."','".(int)$current_time."','".$btc_address."', '', -1,'".(float)$fiat_amount."','".(int)$bits."', 0)");
      } else {
        $data['address_error'] = $response->error;
      }
    // If existing order is found, use existing BTC address and update price and timestamp
    } else {
      $data['btc_address'] = $order['addr'];
      $data['btc_href'] = "bitcoin:".$order['addr']."?amount=".$satoshi_amount;

      $query="UPDATE ".DB_PREFIX."blockonomics_bitcoin_orders SET bits='".$bits."',value='".$fiat_amount."',timestamp=".$current_time." WHERE addr='".$order['addr']."'";
      $this->db->query($query);
    }

    $this->response->setOutput($this->load->view('extension/payment/blockonomicsinvoice', $data));
  }

	/**
	 * Shows timeout message
	 * @return void
	 */
	public function timeout() {
    
    $this->document->setTitle($this->language->get('text_title'));

		$data['breadcrumbs'] = array();

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/home')
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_basket'),
			'href' => $this->url->link('checkout/cart')
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_checkout'),
			'href' => $this->url->link('checkout/checkout', '', true)
		);

    $data['column_left'] = $this->load->controller('common/column_left');
		$data['column_right'] = $this->load->controller('common/column_right');
		$data['content_top'] = $this->load->controller('common/content_top');
		$data['content_bottom'] = $this->load->controller('common/content_bottom');
		$data['footer'] = $this->load->controller('common/footer');
		$data['header'] = $this->load->controller('common/header');

		$this->response->setOutput($this->load->view('extension/payment/blockonomicstimeout', $data));
  }

	/**
	 * Convenience wrapper for blockonomics settings
	 *
	 * Automatically persists to database on set and combines getting and setting into one method
	 * Assumes 'blockonomics_' prefix
	 *
	 * @param string $key Setting key
	 * @param string $value Setting value if setting the value
	 * @return string|null|void Setting value, or void if setting the value
	 */
	public function setting($key, $value = null) {
		// Set the setting
		if (func_num_args() === 2) {
			return $this->blockonomics->setting($key, $value);
		}

		// Get the setting
		return $this->blockonomics->setting($key);
	}

	/**
	 * Callback Handler
	 * @return void
	 */
	public function callback() {
    $this->load->model('checkout/order');

    //Get parameters from callback url
    $secret = $_GET['secret'];
    $txid = $_GET['txid'];
    $value = $_GET['value'];
    $status = $_GET['status'];
    $addr = $_GET['addr'];

		$this->log('info', 'Callback Handler called');

    if($this->setting('callback_secret') != $secret) {
      die('Invalid secret');
    }

    //Upate order info
    $query="UPDATE ".DB_PREFIX."blockonomics_bitcoin_orders SET status='".(int)$status."',txid='".$txid."',bits_payed=".(int)$value." WHERE addr='".$addr."'";
    $this->db->query($query);

    $sql = $this->db->query("SELECT * FROM ".DB_PREFIX."blockonomics_bitcoin_orders WHERE `addr` = '".$addr."' LIMIT 1");
    $order = $sql->row;

    if(!isset($order['id_order'])){
			$this->log('error', 'Order with bitcoin address '.$addr.' not found in the records.');
      return;
    }

    $comment = "";
    $expected = $order['bits'] / 1.0e8;
		$paid = $value / 1.0e8;

		switch ($status) {
			case 0:
				$order_status_id = $this->setting('paid_status');
				$order_message = $this->language->get('text_progress_paid');
				$comment = "Waiting for Confirmation on Bitcoin network<br>" .
								"Bitcoin transaction id: $txid <br>" .
								"You can view the transaction at: <br>" .
								"<a href='https://www.blockonomics.co/api/tx?txid=$txid&addr=$addr' target='_blank'>https://www.blockonomics.co/api/tx?txid=$txid&addr=$addr</a>";
				break;
			case 1:
				$order_status_id = $this->setting('confirmed_status');
				$order_message = $this->language->get('text_progress_confirmed');
				break;
			case 2:
				if ($paid < $expected) {
					$order_status_id = '7'; // 7 = Canceled
					$order_message = 'Canceled';
					$comment = "<b>Warning: Invoice canceled as Paid Amount was less than expected</b><br>";
				} else {
					$order_status_id = $this->setting('complete_status');
					$order_message = $this->language->get('text_progress_complete');
				}
				$comment .= "Bitcoin transaction id: $txid\r" .
						"Expected amount: $expected BTC\r" .
						"Paid amount: $paid BTC\r" .
						"You can view the transaction at:\r" .
						"<a href='https://www.blockonomics.co/api/tx?txid=$txid&addr=$addr' target='_blank'>https://www.blockonomics.co/api/tx?txid=$txid&addr=$addr</a>";
				break;
			default:
				$this->log('info', 'Status is not paid/confirmed/complete. Redirecting to checkout/checkout');
				$this->response->redirect($this->url->link('checkout/checkout'));
				return;
		}

		// Progress the order status
		$this->model_checkout_order->addOrderHistory($order['id_order'], $order_status_id, $comment, true);
	}
}
