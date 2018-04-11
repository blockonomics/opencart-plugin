<?php
/**
 * Blockonomics Payment Model
 */
class ModelExtensionPaymentBlockonomics extends Model {

	/** @var BlockonomicsLibrary $blockonomics */
	private $blockonomics;

	/**
	 * Blockonomics Payment Model Construct
	 * @param Registry $registry
	 */
	public function __construct($registry) {
		parent::__construct($registry);
		$this->load->language('extension/payment/blockonomics');
		$this->blockonomics = new Blockonomics($registry);
	}

	/**
	 * Returns the Blockonomics Payment Method if available
	 * @param  array $address Customer billing address
	 * @return array|void Blockonomics Payment Method if available
	 */
	public function getMethod($address)	{
		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "zone_to_geo_zone WHERE geo_zone_id = '" . (int)$this->blockonomics->setting('geo_zone_id') . "' AND country_id = '" . (int)$address['country_id'] . "' AND (zone_id = '" . (int)$address['zone_id'] . "' OR zone_id = '0')");

		// All Geo Zones configured or address is in configured Geo Zone
		if (!$this->config->get('blockonomics_geo_zone_id') || $query->num_rows) {
			return array(
				'code'	   => 'blockonomics',
				'title'	  => $this->language->get('text_title'),
				'terms'	  => '',
				'sort_order' => $this->blockonomics->setting('sort_order')
			);
		}
	}
}
