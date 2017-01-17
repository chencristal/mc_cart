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

    private $user_email = null;
    private $is_admin = false;
    private $previous_email = null;
    private $cart = array();
    private $pushed_orders = array();
    private $cart_was_submitted = false;
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
        ee()->load->model(array('settings_model', 'carts_model'));

        ee()->load->add_package_path(PATH_THIRD.'cartthrob/');
        ee()->load->model(array('field_model', 'channel_model', 'product_model'));
        ee()->load->library('cartthrob_loader');
        ee()->load->library('get_settings');

        $this->is_admin = in_array(ee()->session->userdata('group_id'), 
            ee()->config->item('cartthrob:admin_checkout_groups'));
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




    //
    // When checkout form is submitted, but before the data is sent to the payment gateway file.
    //
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


    //
    // Check store sync (Should be called after initialize())
    //
    private function check_store_sync() {
        $api_key = $this->get_general_setting('mc_api_key');
        $list_id = $this->get_general_setting('mc_list_id');
        $store_sync = $this->get_general_setting('mc_store_sync');

        if (empty($api_key) || empty($list_id) || $list_id == '0' || $store_sync != 'y') {
            return false;
        }

        return true;
    }


    //
    // Additional processing when add to cart action ends. (refer to `woocommerce_add_to_cart`)
    // At this point the item data has been aggregated into an item object
    //
    public function mc_cartthrob_add_to_cart_end($item) {
        $this->initialize();

        if ($this->check_store_sync() == false) return false;

        mc_log('cartthrob_add_to_cart_end');

        if (/*$this->is_admin ||*/ $this->cart_was_submitted) return false;

        if (empty($this->cart)) {
            $this->cart = $this->getCartItems();
        }

        /*mc_log($this->cart);

        return true;*/

        if (($user_email = $this->getCurrentUserEmail())) {

            $previous = $this->getPreviousEmailFromSession();

            $uid = md5(trim(strtolower($user_email)));

            // delete the previous records.
            if (!empty($previous) && $previous !== $user_email) {

                mc_log("Previous Email = ".$previous);

                if ($this->api_loader->deleteCartByID(mailchimp_get_store_id(), $previous_email = md5(trim(strtolower($previous))))) {
                    mailchimp_log('ac.cart_swap', "Deleted cart [$previous] :: ID [$previous_email]");
                }

                // going to delete the cart because we are switching.
                $this->deleteCart($previous_email);
            }

            if ($this->cart && !empty($this->cart)) {

                // track the cart locally so we can repopulate things for cross device compatibility.
                $this->trackCart($uid, $user_email);

                $this->cart_was_submitted = true;

                // grab the cookie data that could play important roles in the submission
                $campaign = $this->getCampaignTrackingID();

                // fire up the job handler
                // $handler = new MailChimp_WooCommerce_Cart_Update($uid, $user_email, $campaign, $this->cart);
                // wp_queue($handler);

                try {
                    $store_id = mailchimp_get_store_id();

                    if (!empty($store_id)) {

                        // delete it and the add it back.
                        $this->api_loader->deleteCartByID($store_id, $uid);

                        // if they emptied the cart ignore it.
                        if (!is_array($this->cart) || empty($this->cart)) {
                            return false;
                        }

                        // $checkout_url = wc_get_checkout_url();
                        $checkout_url = ee()->functions->create_url(ee()->uri->uri_string());   // check `checkout_url` again

                        if (mailchimp_string_contains($checkout_url, '?')) {
                            $checkout_url .= '&mc_cart_id='.$uid;
                        } else {
                            $checkout_url .= '?mc_cart_id='.$uid;
                        }

                        $customer = new MailChimp_WooCommerce_Customer();
                        $customer->setId($uid);
                        $customer->setEmailAddress($user_email);
                        $customer->setOptInStatus(false);

                        $cart = new MailChimp_WooCommerce_Cart();
                        $cart->setId($uid);
                        $cart->setCampaignID($campaign);
                        $cart->setCheckoutUrl($checkout_url);
                        $cart->setCurrencyCode($this->get_general_setting('mc_currency'));

                        $cart->setCustomer($customer);

                        $order_total = 0;
                        $products = array();

                        foreach ($this->cart as $hash => $item) {
                            try {
                                $line = $this->transformLineItem($hash, $item);
                                $cart->addItem($line);
                                $order_total += ($item['quantity'] * $line->getPrice());
                                $products[] = $line;
                            } catch (\Exception $e) {}
                        }

                        if (empty($products)) {
                            return false;
                        }

                        $cart->setOrderTotal($order_total);

                        mc_log($cart->toArray());

                        try {
                            mailchimp_log('abandoned_cart.submitting', "email: {$customer->getEmailAddress()}");

                            // if the post is successful we're all good.
                            $this->api_loader->addCart($store_id, $cart, false);

                            mailchimp_log('abandoned_cart.success', "email: {$customer->getEmailAddress()} :: checkout_url: $checkout_url");

                        } catch (\Exception $e) {

                            mailchimp_log('abandoned_cart.error', "email: {$customer->getEmailAddress()}");

                            // if we have an error it's most likely due to a product not being found.
                            // let's loop through each item, verify that we have the product or not.
                            // if not, we will add it.
                            /*foreach ($products as $item) {
                                // @var MailChimp_WooCommerce_LineItem $item
                                $transformer = new MailChimp_WooCommerce_Single_Product($item->getProductID());
                                if (!$transformer->api()->getStoreProduct($store_id, $item->getProductId())) {
                                    $transformer->handle();
                                }
                            }*/

                            mailchimp_log('abandoned_cart.submitting', "email: {$customer->getEmailAddress()}");

                            // if the post is successful we're all good.
                            $this->api_loader->addCart($store_id, $cart, false);

                            mailchimp_log('abandoned_cart.success', "email: {$customer->getEmailAddress()}");
                        }
                    }

                } catch (\Exception $e) {
                    mailchimp_log('abandoned_cart.error', "{$e->getMessage()} on {$e->getLine()} in {$e->getFile()}");
                }

            }

            return true;
        }

        return false;
    }


    /**
     * @param string $hash
     * @param $item
     * @return MailChimp_WooCommerce_LineItem
     */
    private function transformLineItem($hash, $item)
    {
        $price = ee()->cartthrob->cart->item($hash)->price();

        $line = new MailChimp_WooCommerce_LineItem();
        $line->setId($hash);
        $line->setProductId($item['product_id']);

        // Not consider `variation_id`
        if (isset($item['variation_id']) && $item['variation_id'] > 0) {
            $line->setProductVariantId($item['variation_id']);
        } else {
            $line->setProductVariantId($item['product_id']);
        }

        $line->setQuantity($item['quantity']);
        $line->setPrice($price);

        return $line;
    }

    //
    // Additional processing when update cart action ends. (refer to `woocommerce_cart_updated`)
    //
    public function mc_cartthrob_update_cart_end() {
        $this->initialize();

        if ($this->check_store_sync() == false) return false;

        /*$customer_info = ee()->cartthrob->cart->customer_info();
        $ret = ee()->session->cache;*/
        mc_log('cartthrob_update_cart_end');

        /*$item = ee()->cartthrob->cart->item(0);
        mc_log($item->price());*/

        /*if (!empty($customer_info['email_address']))
            mc_log($customer_info);*/

        return true;
    }

    //
    // Additional processing when a delete from cart action is about to end. (refer to `woocommerce_cart_item_removed`)
    //
    public function mc_cartthrob_delete_from_cart_end() {
        $this->initialize();

        if ($this->check_store_sync() == false) return false;

        mc_log('cartthrob_delete_from_cart_end');

        return true;
    }

    //
    // Additional processing when the payment has been authorized.
    //
    public function mc_cartthrob_on_authorize() {
        $this->initialize();

        if ($this->check_store_sync() == false) return false;

        mc_log('cartthrob_on_authorize');

        return true;
    }

    //
    // Subscribe when member login (For the subscribe, it should be changed)
    //
    public function mc_member_login($userdata) {

        $this->initialize();
        // $user_session = ee()->cartthrob_session->to_array();
        // $site_url = md5(ee()->config->item('site_url'));

        if ($this->check_store_sync() == false) return false;

        mc_log('member_login => '.$userdata->email);

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

    //
    // Get the image URI for the product tracking
    //
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

    //
    // Called before the channel entry object is inserted or updated. (refer to `save_post`)
    // Changes made to the object will be saved automatically.
    //
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

            mc_log('mc_after_channel_entry_save => product_id : '. $product_id);    // chen_debug

            if ($this->api_loader->getStoreProduct($store_id, $product_id)) {
                $this->api_loader->deleteStoreProduct($store_id, $product_id);
            }

            if (isset($values['submit'])) {     // Only for `save` or `update` product
                try {
                    $product = new MailChimp_WooCommerce_Product();

                    $product->setId($product_id);
                    $product->setTitle($values['title']);
                    $product->setHandle($values['title']);
                    $product->setImageUrl($this->parse_file_server_paths($values[$product_thumbnail_id]));
                    $product->setDescription($values[$product_description_id]);
                    $product->setPublishedAtForeign(mailchimp_date_utc($values['entry_date']));
                    $product->setUrl($values['url_title']);

                    // Create a new Variant for the product
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



    /**
     * @return mixed|null
     */
    private function getCampaignTrackingID()
    {
        $cookie = $this->cookie('mailchimp_campaign_id', false);
        if (empty($cookie)) {
            $cookie = $this->getWooSession('mailchimp_tracking_id', false);
        }

        return $cookie;
    }

    private function setCampaignTrackingID($id, $cookie_duration) {
        $cid = trim($id);

        @setcookie('mailchimp_campaign_id', $cid, $cookie_duration, '/');
        ee()->session->userdata['mailchimp_campaign_id'] = $cid;
    }

    private function getEmailFromSession() {
        return $this->cookie('mailchimp_user_email', false);
    }

    private function cookie($key, $default = null) {
        if ($this->is_admin) {
            return $default;
        }

        return isset($_COOKIE[$key]) ? $_COOKIE[$key] : $default;
    }

    /**
     * @return bool
     */
    private function getPreviousEmailFromSession()
    {
        if ($this->previous_email) {
            return $this->previous_email = strtolower($this->previous_email);
        }
        $email = $this->cookie('mailchimp_user_previous_email', false);
        return $email ? strtolower($email) : false;
    }

    /**
     * @param $key
     * @param null $default
     * @return mixed|null
     */
    private function getWooSession($key, $default = null)
    {
        if (empty(ee()->session->userdata)) {
            return $default;
        }

        if (isset(ee()->session->userdata[$key])) {
            return ee()->session->userdata[$key];
        }

        return $default;
    }

    /**
     * @param $key
     * @param $value
     * @return $this
     */
    private function setWooSession($key, $value)
    {
        if (empty(ee()->session->userdata)) {
            return $this;
        }

        ee()->session->userdata[$key] = $value;

        return $this;
    }

    /**
     * @param $key
     * @return $this
     */
    private function removeWooSession($key)
    {
        if (empty(ee()->session->userdata)) {
            return $this;
        }

        unset(ee()->session->userdata[$key]);
        return $this;
    }


    /**
     *
     */
    public function get_user_by_hash()
    {
        $this->initialize();

        if (ee()->input->is_ajax_request() && isset($_GET['hash'])) {
            if (($cart = $this->getCart($_GET['hash']))) {
                $this->respondJSON(array('success' => true, 'email' => $cart->email));
            }
        }

        $this->respondJSON(array('success' => false, 'email' => false));
    }

    /**
     *
     */
    public function set_user_by_email()
    {
        $this->initialize();

        if ($this->is_admin) {
            $this->respondJSON(array('success' => false));
        }

        if (ee()->input->is_ajax_request() && isset($_GET['email'])) {

            $cookie_duration = $this->getCookieDuration();

            $this->user_email = trim(str_replace(' ','+', $_GET['email']));

            if (($current_email = $this->getEmailFromSession()) && $current_email !== $this->user_email) {
                $this->previous_email = $current_email;
                $this->force_cart_post = true;
                @setcookie('mailchimp_user_previous_email',$this->user_email, $cookie_duration, '/' );
            }

            @setcookie('mailchimp_user_email', $this->user_email, $cookie_duration, '/' );

            $this->getCartItems();

            $this->handleCartUpdated();

            $this->respondJSON(array(
                'success' => true,
                'email' => $this->user_email,
                'previous' => $this->previous_email,
                'cart' => $this->cart,
            ));
        }

        $this->respondJSON(array('success' => false, 'email' => false));
    }


    /**
     * @return bool|array
     */
    private function getCartItems()
    {
        if (!($this->cart = $this->getWooSession('cart', false))) {
            $this->cart = ee()->cartthrob->cart->items_array();
        } else {
            $cart_session = array();
            foreach ( $this->cart as $key => $values ) {
                $cart_session[$key] = $values;
                unset($cart_session[$key]['data']); // Unset product object
            }
            return $this->cart = $cart_session;
        }

        return is_array($this->cart) ? $this->cart : false;
    }

    /**
     * @param string $time
     * @return int
     */
    private function getCookieDuration($time = 'thirty_days')
    {
        $durations = array(
            'one_day' => 86400, 'seven_days' => 604800, 'fourteen_days' => 1209600, 'thirty_days' => 2419200,
        );

        if (!array_key_exists($time, $durations)) {
            $time = 'thirty_days';
        }

        return time() + $durations[$time];
    }

    /**
     * @param $uid
     * @return array|bool|null|object|void
     */
    private function getCart($uid)
    {
        $ret = ee()->carts_model->get_saved_cart($uid);

        return $ret;
    }

    /**
     * @param $uid
     * @return true
     */
    private function deleteCart($uid)
    {
        ee()->carts_model->delete_cart($uid);

        return true;
    }

    /**
     * @param $uid
     * @param $email
     * @return bool
     */
    private function trackCart($uid, $email)
    {
        $user_id = $this->get_current_user_id();
        $saved_cart = ee()->carts_model->get_saved_cart($uid);

        ee()->carts_model->save_cart($uid, $email, $user_id, json_encode($this->cart));

        return true;
    }


    private function get_current_user_id() {
        return ee()->cartthrob_members_model->get_member_id();
    }

    /**
     * @param $data
     */
    private function respondJSON($data)
    {
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }

    /**
     * @return bool|string
     */
    private function getCurrentUserEmail()
    {
        if (isset($this->user_email) && !empty($this->user_email)) {
            return $this->user_email = strtolower($this->user_email);
        }


        $customer_info = ee()->cartthrob->cart->customer_info();
        
        $email = (!empty($customer_info['email_address'])) ? $customer_info['email_address'] : $this->getEmailFromSession();

        return $this->user_email = strtolower($email);
    }

}