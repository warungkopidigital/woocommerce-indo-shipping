<?php

/*
  Class to keep the global variable such as API key
*/
class WCIS_Method extends WC_Shipping_Method {
  private $api;

  public function __construct($instance_id = 0) {
		$this->id = 'wcis';
    $this->title = __('Indo Shipping', 'wcis');
		$this->method_title = __('Indo Shipping', 'wcis');
		$this->method_description = __('Indonesian domestic shipping with JNE, TIKI, or POS', 'wcis');

    $this->api = isset($this->settings['key']) ? new WCIS_API($this->settings['key']) : null;

    $this->enabled = $this->get_option('enabled');
    $this->init_form_fields();

    // allow save setting
    add_action('woocommerce_update_options_shipping_' . $this->id, array($this, 'process_admin_options') );
    // TODO: add action whenever it's saved, check API
	}

  /*
    Initiate global setting page for WCIS
  */
  function init_form_fields() {
    $enabled_field = array(
      'title' => __('Enable/Disable', 'wcis'),
      'type' => 'checkbox',
      'label' => __('Enable Indo Shipping', 'wcis'),
      'default' => 'yes'
    );

    $key_field = array(
      'title' => __('API Key', 'wcis'),
      'type' => 'text',
      'description' => __('Signup at <a href="http://rajaongkir.com/akun/daftar" target="_blank">rajaongkir.com</a> and choose Pro license (Paid). Paste the API Key here', 'wcis'),
    );

    $city_field = array(
      'title' => __('City Origin', 'wcis'),
      'type' => 'select',
      'class'    => 'wc-enhanced-select',
      'description' => __('Ship from where? <br> Change your province at General > Base Location', 'wcis'),
      'options' => array()
    );

    $this->form_fields = array(
      'key' => $key_field
    );

    // if key is valid, show the other setting fields
    if($this->check_key_valid() ) {
      $city_field['options'] = $this->get_cities_origin();

      $this->form_fields['enabled'] = $enabled_field;
      $this->form_fields['city'] = $city_field;

      // set service fields by each courier
      $couriers = WCIS_Data::get_couriers();
      foreach($couriers as $id => $name) {
        $this->form_fields[$id . '_services'] = array(
          'title' => $name,
          'type' => 'multiselect',
          'class' => 'wc-enhanced-select',
          'description' => __("Choose allowed services by { $name }.", 'wcis'),
          'options' => WCIS_Data::get_services($id, true)
        );
      }

    } // if valid
  }


  /////


  /*
    Check validation of Key by doing a sample AJAX call

    @return bool - Valid or not
  */
  private function check_key_valid() {
    $key = isset($this->settings['key']) ? $this->settings['key'] : null;

    // TODO: broken! create a code to check API whenever this setting is saved
    $license = get_transient('wcis_license');

    if(!$key || $license) { return false; } // ABORT if key is empty



    // if never checked OR key different from cache
    if(empty($license) || $license['key'] !== $key ) {
      $license = array(
        'key' => $key,
        'valid' => $this->api->is_valid()
      );

      if($license['valid']) { set_transient('wcis_license', $license, 60*60*24); }
    }

    // set message label
    if($license['valid']) {
      $msg = __('API Connected!', 'wcis');
      $this->form_fields['key']['description'] = '<span style="color: #4caf50;">' . $msg . '</span>';
    } else {
      $msg = __('Invalid API Key. Is there empty space before / after it?', 'wcis');
      $this->form_fields['key']['description'] = '<span style="color:#f44336;">' . $msg . '</span>';
    }

    return $license['valid'];
  }

  /*
    Get cities origin from API
    @return array - List of cities in base province
  */
  private function get_cities_origin() {
    $country = wc_get_base_location();
    $province = WCIS_Data::get_province_id($country['state']);

    $location = get_transient('wcis_location_origin');

    // if cities not in cache OR province has changed
    if($location === false || $location['province'] !== $province) {

      // get raw cities and parse it
      $cities_raw = $this->api->get_cities($province);
      $cities = array_reduce($cities_raw, function($result, $i) {
        $result[$i['city_id']] = $i['city_name'];
        return $result;
      }, array() );

      $location = array('province' => $province, 'cities' => $cities);
      set_transient('wcis_location_origin', $location, 60*60*24*30);
    }

    return $location['cities'];
  }

}
