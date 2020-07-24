<?php
/**
 * Blockonomics Payment Admin Controller
 */
class ControllerExtensionPaymentBlockonomics extends Controller {

	/** @var array $error Validation errors */
	private $error = array();

	/** @var boolean $ajax Whether the request was made via AJAX */
	private $ajax = false;

	/** @var BlockonomicsLibrary $blockonomics */
	private $blockonomics;

	/**
	 * Blockonomics Payment Admin Controller Constructor
	 * @param Registry $registry
	 */
	public function __construct($registry) {
		parent::__construct($registry);

		// Make langauge strings and Blockonomics Library available to all
		$this->load->language('extension/payment/blockonomics');

		$this->blockonomics = new Blockonomics($registry);
	}

	/**
	 * Primary settings page
	 * @return void
	 */
	public function index() {
		// Saving settings
		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->request->post['action'] === 'save' && $this->validate()) {

			$this->session->data['success'] = $this->language->get('text_success');
			$this->setting('geo_zone_id', $this->request->post['payment_blockonomics_geo_zone_id']);
			$this->setting('status', $this->request->post['payment_blockonomics_status']);
			$this->setting('sort_order', $this->request->post['payment_blockonomics_sort_order']);
			$this->setting('callback_secret', $this->request->post['payment_blockonomics_callback_secret']);
			$this->setting('callback_url', $this->request->post['payment_blockonomics_callback_url']);
			$this->setting('api_key', $this->request->post['payment_blockonomics_api_key']);
      $this->setting('paid_status', $this->request->post['payment_blockonomics_paid_status']);
			$this->setting('confirmed_status', $this->request->post['payment_blockonomics_confirmed_status']);
			$this->setting('complete_status', $this->request->post['payment_blockonomics_complete_status']);
			$this->session->data['success'] = $this->language->get('text_success');
			$this->response->redirect($this->url->link('extension/payment/blockonomics', 'user_token=' . $this->session->data['user_token'], true));
		}

		$this->document->setTitle($this->language->get('heading_title'));

    $data['text_statuses'] = $this->language->get('text_statuses');
    $data['text_gen_secret'] = $this->language->get('text_gen_secret');

		$data['entry_geo_zone'] = $this->language->get('entry_geo_zone');
		$data['entry_status'] = $this->language->get('entry_status');
		$data['entry_sort_order'] = $this->language->get('entry_sort_order');
		$data['entry_callback_url'] = $this->language->get('entry_callback_url');
		$data['entry_api_key'] = $this->language->get('entry_api_key');
		$data['entry_callback_secret'] = $this->language->get('entry_callback_secret');
    $data['entry_paid_status'] = $this->language->get('entry_paid_status');
		$data['entry_confirmed_status'] = $this->language->get('entry_confirmed_status');
		$data['entry_complete_status'] = $this->language->get('entry_complete_status');

    $data['help_paid_status'] = $this->language->get('help_paid_status');
		$data['help_confirmed_status'] = $this->language->get('help_confirmed_status');
    $data['help_complete_status'] = $this->language->get('help_complete_status');

		$data['button_save'] = $this->language->get('button_save');
		$data['button_cancel'] = $this->language->get('button_cancel');
		$data['button_gen_secret'] = $this->language->get('button_save');

		$data['url_action'] = $this->url->link('extension/payment/blockonomics', 'user_token=' . $this->session->data['user_token'], 'SSL');
		$data['cancel'] = $this->url->link('extension/extension', 'user_token=' . $this->session->data['user_token'], 'SSL');
		$data['url_reset'] = $this->url->link('extension/payment/blockonomics/reset', 'user_token=' . $this->session->data['user_token'], 'SSL');
		$data['url_gen_secret'] = $this->url->link('extension/payment/blockonomics/gensecret', 'user_token=' . $this->session->data['user_token'], 'SSL');
		$data['url_test_setup'] = $this->url->link('extension/payment/blockonomics/testsetup', 'user_token=' . $this->session->data['user_token'], 'SSL');

		$data['breadcrumbs'] = array();

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_payment'),
			'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment', true)
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('extension/payment/blockonomics', 'user_token=' . $this->session->data['user_token'], true)
		);

		// #GENERAL
		$data['blockonomics_connection'] = $this->config->get('payment_blockonomics_connection');
		$this->load->model('localisation/geo_zone');
		$data['geo_zones'] = $this->model_localisation_geo_zone->getGeoZones();
    $data['blockonomics_geo_zone_id'] = (isset($this->request->post['blockonomics_geo_zone_id'])) ? $this->request->post['blockonomics_geo_zone_id'] : $this->setting('geo_zone_id');
		$data['blockonomics_status'] = (isset($this->request->post['blockonomics_status'])) ? $this->request->post['blockonomics_status'] : $this->setting('status');
		$data['blockonomics_sort_order'] = (isset($this->request->post['blockonomics_sort_order'])) ? $this->request->post['blockonomics_sort_order'] : $this->setting('sort_order');

		// #ADVANCED
		$data['blockonomics_callback_url'] = (isset($this->request->post['blockonomics_callback_url'])) ? $this->request->post['blockonomics_callback_url'] : $this->setting('callback_url');
		$data['blockonomics_callback_secret'] = (isset($this->request->post['blockonomics_callback_secret'])) ? $this->request->post['blockonomics_callback_secret'] : $this->setting('callback_secret');
		$data['blockonomics_api_key'] = (isset($this->request->post['blockonomics_api_key'])) ? $this->request->post['blockonomics_api_key'] : $this->setting('api_key');

    // #ORDER STATUSES
		$this->load->model('localisation/order_status');
		$data['order_statuses'] = $this->model_localisation_order_status->getOrderStatuses();
		$data['blockonomics_paid_status'] = (isset($this->request->post['blockonomics_paid_status'])) ? $this->request->post['blockonomics_paid_status'] : $this->setting('paid_status');
		$data['blockonomics_confirmed_status'] = (isset($this->request->post['blockonomics_confirmed_status'])) ? $this->request->post['blockonomics_confirmed_status'] : $this->setting('confirmed_status');
    $data['blockonomics_complete_status'] = (isset($this->request->post['blockonomics_complete_status'])) ? $this->request->post['blockonomics_complete_status'] : $this->setting('complete_status');

		// #LAYOUT
		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		// #NOTIFICATIONS
		$data['error_warning'] = '';
		if (isset($this->error['warning'])) {
			$data['error_warning'] = $this->error['warning'];
		} elseif (isset($this->session->data['warning'])) {
			$data['error_warning'] = $this->session->data['warning'];
			unset($this->session->data['warning']);
		} else {
			$data['error_warning'] = '';
		}

		$data['success'] = '';
		if (isset($this->session->data['success'])) {
			$data['success'] = $this->session->data['success'];
			unset($this->session->data['success']);
		}

		$data['test_setup_error'] = '';
		if (isset($this->session->data['test_setup_error'])) {
			$data['test_setup_error'] = $this->session->data['test_setup_error'];
			unset($this->session->data['test_setup_error']);
		}

		$data['test_setup_success'] = '';
		if (isset($this->session->data['test_setup_success'])) {
			$data['test_setup_success'] = $this->session->data['test_setup_success'];
			unset($this->session->data['test_setup_success']);
		}

		$data['error_callback_url'] = '';
		if (isset($this->error['callback_url'])) {
			$data['error_callback_url'] = $this->error['callback_url'];
		}

		$data['error_api_key'] = '';
		if (isset($this->error['api_key'])) {
			$data['error_api_key'] = $this->error['api_key'];
		}

		$this->response->setOutput($this->load->view('extension/payment/blockonomics', $data));
	}

  /**
	 * Attempts to connect to BitPay API
	 * @return void
	 */
	public function gensecret() {
    //Generate callback secret
    $secret = md5(uniqid(rand(), true));

    $default_callback_url = $this->url->link('extension/payment/blockonomics/callback&secret='.$secret , $this->config->get('config_secure'));
    $default_callback_url = str_replace(HTTP_SERVER, HTTP_CATALOG, $default_callback_url);
    $default_callback_url = str_replace(HTTPS_SERVER, HTTPS_CATALOG, $default_callback_url);
    $default_callback_url = str_replace('&amp;1', '', $default_callback_url);

    $this->setting('callback_url', $default_callback_url);
    $this->setting('callback_secret', $secret);

		$this->response->redirect($this->url->link('extension/payment/blockonomics', 'user_token=' . $this->session->data['user_token'] . '', true));
  }

  public function testsetup() {
	  $response = $this->blockonomics->testsetup();
	  if($response){
		if($response == 'There is a problem in your callback url'){
			$this->session->data['test_setup_error'] = $this->language->get('error_callback_test_setup');;
		}elseif($response == 'API Key is invalid') {
			$this->session->data['test_setup_error']  = $this->language->get('error_apikeytest_test_setup');;
		}elseif($response == 'You have an existing callback URL. Refer instructions on integrating multiple websites'){
			$this->session->data['test_setup_error']  = $this->language->get('error_callback_doesnot_match_test_setup');;
		}
	  }else{
		$this->session->data['test_setup_success'] = $this->language->get('text_apikeytest_test_setup');
	  }
	  $this->response->redirect($this->url->link('extension/payment/blockonomics', 'user_token=' . $this->session->data['user_token'] . '', true));

  }


	/**
	 * Convenience wrapper for blockonomics settings
	 *
	 * Automatically persists to database on set and combines getting and setting into one method
	 * Assumes blockonomics_ prefix
	 *
	 * @param string $key Setting key
	 * @param string $value Setting value if setting the value
	 * @return string|null|void Setting value, or void if setting the value
	 */
	private function setting($key, $value = null) {
		// Set the setting
		if (func_num_args() === 2) {

			return $this->blockonomics->setting($key, $value);
		}

		// Get the setting
		return $this->blockonomics->setting($key);
	}

	/**
	 * Validate the primary settings for the Blockonomics extension
	 * @return boolean True if the settings provided are valid
	 */
	private function validate() {

		if (!$this->user->hasPermission('modify', 'extension/payment/blockonomics')) {
			$this->error['warning'] = $this->language->get('warning_permission');
		}

		if (!empty($this->request->post['payment_blockonomics_callback_url']) && false === filter_var($this->request->post['payment_blockonomics_callback_url'], FILTER_VALIDATE_URL)) {
			$this->error['callback_url'] = $this->language->get('error_callback_url');
		}

		if (empty($this->request->post['payment_blockonomics_api_key'])) {
			$this->error['api_key'] = $this->language->get('error_api_key');
		}

		return !$this->error;
	}

	/**
	 * Install the extension by setting up some smart defaults
	 * @return void
	 */
	public function install() {

    $this->load->model('localisation/order_status');
		$order_statuses = $this->model_localisation_order_status->getOrderStatuses();
		$default_paid = null;
		$default_confirmed = null;
		$default_complete= null;

		foreach ($order_statuses as $order_status) {
			if ($order_status['name'] == 'Processing') {
				$default_paid = $order_status['order_status_id'];
			} elseif ($order_status['name'] == 'Processed') {
				$default_confirmed = $order_status['order_status_id'];
			} elseif ($order_status['name'] == 'Complete') {
				$default_complete = $order_status['order_status_id'];
			}
    }

    $this->blockonomics->log("info", $default_paid );
    $this->blockonomics->log("info", $default_confirmed  );
    $this->blockonomics->log("info", $default_complete  );

    //Generate callback secret
    $secret = md5(uniqid(rand(), true));

    //Delete old settings from previous blockonomics installation
		$default_callback_url = $this->url->link('extension/payment/blockonomics/callback&secret='.$secret , $this->config->get('config_secure'));
		$default_callback_url = str_replace(HTTP_SERVER, HTTP_CATALOG, $default_callback_url);
		$default_callback_url = str_replace(HTTPS_SERVER, HTTPS_CATALOG, $default_callback_url);
		$default_callback_url = str_replace('&amp;1', '', $default_callback_url);

		$data['default_callback_url'] = $default_callback_url;
		$this->db->query("DELETE FROM ".DB_PREFIX."setting WHERE code = 'blockonomics'");
		$this->load->model('setting/setting');
		$default_settings = array(
			'payment_blockonomics_token' => null,
			'payment_blockonomics_geo_zone_id' => '0',
			'payment_blockonomics_status' => '0',
			'payment_blockonomics_sort_order' => null,
			'payment_blockonomics_callback_secret' => $secret,
			'payment_blockonomics_callback_url' => $default_callback_url,
			'payment_blockonomics_api_key' => null,
			'payment_blockonomics_version' => $this->blockonomics->version,
      'payment_blockonomics_paid_status' => $default_paid,
			'payment_blockonomics_confirmed_status' => $default_confirmed,
			'payment_blockonomics_complete_status' => $default_complete
		);
		$this->model_setting_setting->editSetting('payment_blockonomics', $default_settings);

    $this->db->query(
      "CREATE TABLE IF NOT EXISTS ".DB_PREFIX."blockonomics_bitcoin_orders (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            id_order INT UNSIGNED NOT NULL,
            timestamp INT(8) NOT NULL,
            addr varchar(255) NOT NULL,
            txid varchar(255) NOT NULL,
            status int(8) NOT NULL,
            value double(10,2) NOT NULL,
            bits int(8) NOT NULL,
            bits_payed int(8) NOT NULL,
            PRIMARY KEY (id),
        UNIQUE KEY order_table (addr))"
    );
	}

	/**
	 * Uninstall the extension by removing the settings
	 * @return void
	 */
	public function uninstall() {
		$this->load->model('setting/setting');
		$this->model_setting_setting->deleteSetting('payment_blockonomics');
    $this->db->query('DROP TABLE IF EXISTS `' .DB_PREFIX. 'blockonomics_bitcoin_orders`;');
	}
}
