<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

require_once PATH_THIRD.'mc_cart/config.php';

class Mc_cart_ext
{
    public $name = MC_CART_NAME;
    public $class_name = 'Mc_cart';
    public $version = MC_CART_VER;
    public $description = MC_CART_DESC;
    public $settings_exist = 'n';
    public $docs_url = 'https://www.mailchimp.com/';

    private $settings = array();

    private $initialized = FALSE;
    private $module_name = MC_CART_MACHINE;
    private $api_loader = null;
    public $general_settings = array();     // mc_cart_settings {key: value}

    private $default_settings = array(
        'mc_profile' => '',
        'mc_api_key' => '',
        'mc_api_key_valid' => 'n',
        'mc_list_id' => '',
        'mc_debugging' => 'y',
        'mc_store_name' => '',
        'mc_store_email' => '',
        'mc_store_address' => '',
        'mc_store_city' => '',
        'mc_store_state' => '',
        'mc_store_postal_code' => '',
        'mc_store_country' => 'US',
        'mc_store_phone' => '',

        'mc_lists' => '',
        'mc_timezone' => 'America/New_York',
        'mc_locale' => 'en',
        'mc_currency' => 'USD',
        'mc_store_sync' => 'n',
    );

    public function __construct($settings = '') {
        $this->settings = $settings;

        ee()->load->helper('data');
        ee()->load->library('mailchimp_api');
        ee()->load->model('settings_model');

        ee()->load->add_package_path(PATH_THIRD.'cartthrob/');
        ee()->load->model(array('field_model', 'channel_model', 'product_model'));
        ee()->load->library('cartthrob_loader');
        ee()->load->library('get_settings');
    }

    private function initialize() {
        //
        // Get the generic setting values
        //
        $this->general_settings = ee()->settings_model->get_all_settings();

        if ($this->initialized === TRUE)
            return;

        // check store sync
        if ($this->get_general_setting('mc_store_sync') == 'y') {
            $reset_sync = false;
            if (!isset($this->general_settings['mc_list_id']) ||
                !isset($this->general_settings['mc_api_key'])) {
                $reset_sync = true;
            } else if (strlen($this->general_settings['mc_api_key']) < 10 &&
                strlen($this->general_settings['mc_list_id']) < 5) {
                $reset_sync = true;
            }

            if ($reset_sync == true) {
                $this->general_settings['mc_store_sync'] = 'n';
                ee()->settings_model->save_setting('mc_store_sync', 'n');
            }
        }

        //
        // Initialize the default settings with cartthrob
        //
        $this->default_settings['mc_store_name'] = ee()->get_settings->get_setting("cartthrob","store_name");
        $this->default_settings['mc_store_address'] = ee()->get_settings->get_setting("cartthrob","store_address1");
        $this->default_settings['mc_store_city'] = ee()->get_settings->get_setting("cartthrob","store_city");
        $this->default_settings['mc_store_state'] = ee()->get_settings->get_setting("cartthrob","store_state");
        $this->default_settings['mc_store_postal_code'] = ee()->get_settings->get_setting("cartthrob","store_zip");
        $this->default_settings['mc_store_country'] = ee()->get_settings->get_setting("cartthrob","store_country");
        $this->default_settings['mc_store_phone'] = ee()->get_settings->get_setting("cartthrob","store_phone");

        //
        // Initialize the mailchimp api class
        //
        $this->api_loader = ee()->mailchimp_api->create_loader(
            $this->get_general_setting('mc_api_key'));

        $this->initialized = TRUE;

        return;
    }

    // Get general setting from $general_settings.
    // $key must be exist in the $default_settings array.
    private function get_general_setting($key) {
        if (isset($this->general_settings[$key]))
            return $this->general_settings[$key];

        return $this->default_settings[$key];
    }


    public function activate_extension() {
        return TRUE;
    }

    public function disable_extension() {
        ee()->db->delete('extension', array('class' => ucfirst(get_class($this))));
    }

    public function update_extension($current = '') {
        return FALSE;
    }


    public function mc_cartthrob_pre_process($data) {
        $this->initialize();

        if ($this->check_store_sync() == false) return false;

        return true;
    }

    //
    // Called during checkout if you are attempting to create a member during checkout.
    //
    public function mc_cartthrob_create_member($data, &$obj) {
        $this->initialize();

        if ($this->check_store_sync() == false) return false;

        return true;
    }

    // Check store sync (Should be called after initialize())
    private function check_store_sync() {
        $api_key = $this->get_general_setting('mc_api_key');
        $list_id = $this->get_general_setting('mc_list_id');
        $store_sync = $this->get_general_setting('mc_store_sync');

        if (empty($api_key) || empty($list_id) || $list_id == '0' || $store_sync != 'y') {
            return false;
        }

        return true;
    }

    // Subscribe when member login
    public function mc_member_login($userdata) {
        $this->initialize();
        // $user_session = ee()->cartthrob_session->to_array();
        // $site_url = md5(ee()->config->item('site_url'));

        if ($this->check_store_sync() == false) return false;

        $email = $userdata->email;
        $api_key = $this->get_general_setting('mc_api_key');
        $list_id = $this->get_general_setting('mc_list_id');
        $store_sync = $this->get_general_setting('mc_store_sync');

        $merge_vars = array(
            'FNAME' => $userdata->screen_name,
            'LNAME' => ''
        );

        try {
            $ret = $this->api_loader->member($list_id, $email);

            if ($merge_vars['FNAME'] != $ret['merge_fields']['FNAME'] ||
                $merge_vars['LNAME'] != $ret['merge_fields']['LNAME'])
            {
                $this->api_loader->deleteMember($list_id, $email);
                $this->api_loader->subscribe($list_id, $email, true, $merge_vars);

                mailchimp_log('member.sync', 'Subscriber Swap '.$email, $merge_vars);

                return false;
            }
        } catch (\Exception $e) {
            if ($e->getCode() == 404) {
                try {
                    $this->api_loader->subscribe($list_id, $email, true, $merge_vars);
                } catch (\Exception $e) {
                    mailchimp_log('member.sync', $e->getMessage());
                }

                return false;
            }

            mailchimp_log('member.sync', $e->getMessage());
        }

        return false;
    }

    private function parse_file_server_paths($string)
    {
        static $upload_urls;

        if (preg_match_all('/{filedir_(\d+)}/', $string, $matches))
        {
            foreach ($matches[1] as $i => $upload_dir)
            {
                if ( ! isset($upload_urls[$upload_dir]))
                {
                    if (version_compare(APP_VER, '2.4', '<'))
                    {
                        ee()->load->model('tools_model');

                        $query = ee()->tools_model->get_upload_preferences(1, $upload_dir);

                        $upload_preferences = $query->row_array();

                        $query->free_result();
                    }
                    else
                    {
                        ee()->load->model('file_upload_preferences_model');

                        $upload_preferences = ee()->file_upload_preferences_model->get_file_upload_preferences(1, $upload_dir);
                    }

                    $upload_urls[$upload_dir] = ($upload_preferences) ? $upload_preferences['url'] : '';
                }

                $string = str_replace($matches[0][$i], $upload_urls[$upload_dir], $string);
            }
        }

        return $string;
    }

    // Called before the channel entry object is inserted or updated.
    // Changes made to the object will be saved automatically.
    public function mc_after_channel_entry_save($entry, $values) {

        $this->initialize();

        if ($this->check_store_sync() == false) return false;

        //
        // Get products channel id
        //
        $products_channel_id = 0;
        $query = ee()->channel_model->get_channels(NULL, array(), array(array('channel_name' => array('products'))));
        if (count($ret = $query->result()) > 0)
            $products_channel_id = $ret[0]->channel_id;

        if ($products_channel_id == 0) {    // Check whether the product channel exists
            return false;
        }

        //
        // Get the field_id_x
        //
        $product_description_id = '';
        $product_thumbnail_id = '';
        $product_detail_image_id = '';
        $product_inventory_id = '';
        $product_sku_id = '';
        $product_price_id = '';

        foreach($ret as $channel) {
            $query = ee()->field_model->get_fields($channel->field_group);
            foreach ($query->result() as $field) {
                switch ($field->field_name) {
                    case 'product_description':
                        $product_description_id = 'field_id_'.$field->field_id;
                        break;
                    case 'product_thumbnail':
                        $product_thumbnail_id = 'field_id_'.$field->field_id;
                        break;
                    case 'product_detail_image':
                        $product_detail_image_id = 'field_id_'.$field->field_id;
                        break;
                    case 'product_price':
                        $product_price_id = 'field_id_'.$field->field_id;
                        break;
                    case 'product_inventory':
                        $product_inventory_id = 'field_id_'.$field->field_id;
                        break;
                    case 'product_sku':
                        $product_sku_id = 'field_id_'.$field->field_id;
                        break;
                }
            }
        }

        if ($values['channel_id'] == $products_channel_id) {    // It is the entry that being updated in the product channel
            $store_id = mailchimp_get_store_id();
            $product_id = $values['entry_id'];

            if ($this->api_loader->getStoreProduct($store_id, $product_id)) {
                $this->api_loader->deleteStoreProduct($store_id, $product_id);
            }

            if (isset($values['submit'])) {
                try {
                    $product = new MailChimp_WooCommerce_Product();

                    $product->setId($product_id);
                    $product->setTitle($values['title']);
                    $product->setHandle($values['title']);
                    $product->setImageUrl($this->parse_file_server_paths($values[$product_thumbnail_id]));
                    $product->setDescription($values[$product_description_id]);
                    $product->setPublishedAtForeign(mailchimp_date_utc($values['entry_date']));
                    $product->setUrl($values['url_title']);

                    // Create a new Variant
                    $variant = new MailChimp_WooCommerce_ProductVariation();
                    $variant->setId($product_id);
                    $variant->setTitle($values['title']);
                    $variant->setUrl($values['url_title']);
                    $variant->setPrice($values[$product_price_id]);
                    $variant->setImageUrl($this->parse_file_server_paths($values[$product_thumbnail_id]));
                    if (!empty($values[$product_inventory_id])) $variant->setInventoryQuantity($values[$product_inventory_id]);
                    if (!empty($values[$product_sku_id])) $variant->setSku($values[$product_sku_id]);
                    if ($values['status'] == 'open') $variant->setVisibility('visible');

                    $product->addVariant($variant);

                    mailchimp_log('product_submit.submitting', "addStoreProduct :: #{$product->getId()}");

                    $this->api_loader->addStoreProduct($store_id, $product);

                    mailchimp_log('product_submit.success', "addStoreProduct :: #{$product->getId()}");

                    // update_option('mailchimp-woocommerce-last_product_updated', $product->getId());

                } catch (MailChimp_WooCommerce_Error $e) {
                    mailchimp_log('product_submit.error', "addStoreProduct :: MailChimp_WooCommerce_Error :: {$e->getMessage()}");
                } catch (MailChimp_WooCommerce_ServerError $e) {
                    mailchimp_log('product_submit.error', "addStoreProduct :: MailChimp_WooCommerce_ServerError :: {$e->getMessage()}");
                } catch (Exception $e) {
                    mailchimp_log('product_submit.error', "addStoreProduct :: Uncaught Exception :: {$e->getMessage()}");
                }
            }
        }

        return true;
    }


    public function mc_cartthrob_add_to_cart_end($item) {
        $this->initialize();

        if ($this->check_store_sync() == false) return false;

        mc_log($item);

        return true;
    }

    public function mc_cartthrob_add_to_cart_start() {
        $this->initialize();

        if ($this->check_store_sync() == false) return false;

        return true;
    }
}