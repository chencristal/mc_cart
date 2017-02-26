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
        'mc_product_fields' => '',
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
        // Initialize the default settings with cartthrob
        //
        $this->default_settings['mc_store_name']        = ee()->get_settings->get_setting("cartthrob", "store_name");
        $this->default_settings['mc_store_address']     = ee()->get_settings->get_setting("cartthrob", "store_address1");
        $this->default_settings['mc_store_city']        = ee()->get_settings->get_setting("cartthrob", "store_city");
        $this->default_settings['mc_store_state']       = ee()->get_settings->get_setting("cartthrob", "store_state");
        $this->default_settings['mc_store_postal_code'] = ee()->get_settings->get_setting("cartthrob", "store_zip");
        $this->default_settings['mc_store_country']     = ee()->get_settings->get_setting("cartthrob", "store_country");
        $this->default_settings['mc_store_phone']       = ee()->get_settings->get_setting("cartthrob", "store_phone");
        $this->default_settings['mc_store_email']       = ee()->session->userdata('email');


        //
        // Get general settings from the db
        //
        $this->general_settings = ee()->settings_model->get_all_settings($this->default_settings);

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


    public function activate_extension() {
        return TRUE;
    }

    public function disable_extension() {
        ee()->db->delete('extension', array('class' => ucfirst(get_class($this))));
    }

    public function update_extension($current = '') {
        return FALSE;
    }


    private function generate_cart_items($items) {

        ee()->cartthrob->cart->clear();
        
        foreach ($items as $item) {
            ee()->cartthrob->cart->add_item($item);
        }

        ee()->cartthrob->cart->save();

        return true;
    }


    //
    // Get product fields from MC
    //
    private function get_product_channel_fields($product_channel_id = null) {

        $ret = array();

        if ($this->get_general_setting('mc_product_fields') == '') {
            $mc_channel_fields = array();
        }
        else {
            $mc_channel_fields = unserialize($this->get_general_setting('mc_product_fields'));
        }

        foreach (ee()->cartthrob->store->config('product_channel_fields') as $id => $fields) {
            if (isset($mc_channel_fields[$id])) {
                $ret[$id] = $mc_channel_fields[$id];
            }
            else {
                $ret[$id] = array(
                    'price' => isset($fields['price']) ? $fields['price'] : null,
                    'shipping' => isset($fields['shipping']) ? $fields['shipping'] : null,
                    'weight' => isset($fields['weight']) ? $fields['weight'] : null,
                    'inventory' => isset($fields['inventory']) ? $fields['inventory'] : null,
                    'description' => null,
                    'image_url' => null,
                );
            }
        }

        if ($product_channel_id == null)
            return $ret;

        if (isset($ret[$product_channel_id]))
            return $ret[$product_channel_id];

        return false;
    }

    //
    // Reassign the template group and template loaded for parsing. 
    // refer to `init` hook and `handleCampaignTracking` function.
    //
    public function mc_core_template_route($uri_string) {

        // check $_GET parameters first
        if (empty($_GET)) return false;

        // initialize and check the mailchimp sync status
        $this->initialize();

        if ($this->check_store_sync() == false) return false;

        /*ee()->load->library('cartthrob_payments');
        mc_log(ee()->cartthrob_payments->paths());

        return false;*/

        $cookie_duration = $this->getCookieDuration();

        // if we have a query string of the mc_cart_id in the URL, that means we received a campaign from MC
        if (isset($_GET['mc_cart_id']) && !isset($_GET['removed_item'])) {
            
            mc_log('mc_core_template_route => mc_cart_id : '. $_GET['mc_cart_id']);

            // try to pull the cart from the database.
            if (($cart = $this->getCart($_GET['mc_cart_id'])) && !empty($cart)) {

                // set the current user email
                $this->user_email = trim(str_replace(' ','+', $cart['email']));

                if (($current_email = $this->getEmailFromSession()) && $current_email !== $this->user_email) {
                    $this->previous_email = $current_email;
                    @setcookie('mc_cart_user_previous_email',$this->user_email, $cookie_duration, '/' );
                }

                // cookie the current email
                @setcookie('mc_cart_user_email', $this->user_email, $cookie_duration, '/' );

                
                $cart_items = unserialize($cart['cart']);
                mc_log($cart_items);


                // set the cart data.
                $this->setWooSession('cart', $cart_items);
                $this->generate_cart_items($cart_items);
            }
        }

        if (isset($_REQUEST['mc_cid'])) {
            $this->setCampaignTrackingID($_REQUEST['mc_cid'], $cookie_duration);
        }

        if (isset($_REQUEST['mc_eid'])) {
            @setcookie('mc_cart_email_id', trim($_REQUEST['mc_eid']), $cookie_duration, '/' );
        }
        

        return false;
    }


    //
    // Return entry object by entry_id
    //
    private function get_entry_by_id($entry_id) {

        $entry = ee('Model')->get('ChannelEntry')
            ->with('Channel')
            ->filter('entry_id', $entry_id)
            ->filter('ChannelEntry.site_id', ee()->config->item('site_id'))
            ->first();

        if (!isset($entry)) {
            return false;
        }

        return $entry;
    }

    //
    // Add entry as a product to mailchimp (called by `handle_cart_updated` function)
    // 
    private function add_product_by_entry_id($entry_id) {

        $entry = $this->get_entry_by_id($entry_id);
        if ($entry == false)    // Check whether the entry exists
            return false;

        $values = $entry->toArray();

        //
        // Get products channel id
        //
        $products_channel_id_array = ee()->cartthrob->store->config('product_channels');
        if ($products_channel_id_array == false)    // Check whether the product channel exists
            return false;

        mc_log('add_product_by_entry_id (new product register to mailchimp) => ' . $entry_id);

        if (in_array($values['channel_id'], $products_channel_id_array)) {    // It is the entry that being updated in the product channel

            //
            // Get the field_id_x
            //
            $fields = $this->get_product_channel_fields($values['channel_id']);

            $product_description_id = 'field_id_'.(isset($fields['description']) ? $fields['description'] : '');
            $product_thumbnail_id = 'field_id_'.(isset($fields['image_url']) ? $fields['image_url'] : '');
            $product_detail_image_id = 'field_id_'.(isset($fields['detail_image_url']) ? $fields['detail_image_url'] : '');
            $product_price_id = 'field_id_'.(isset($fields['price']) ? $fields['price'] : '');
            $product_inventory_id = 'field_id_'.(isset($fields['inventory']) ? $fields['inventory'] : '');
            $product_sku_id = 'field_id_'.(isset($fields['sku']) ? $fields['sku'] : '');

            //
            // Get the values of field_id_x
            //
            $product_id = $values['entry_id'];
            $product_title = $values['title'];
            $product_url = $values['url_title'];
            $product_entry_date = $values['entry_date'];
            $product_status = $values['status'];
            $product_description = isset($values[$product_description_id]) ? $values[$product_description_id] : '';
            $product_thumbnail = isset($values[$product_thumbnail_id]) ? $values[$product_thumbnail_id] : '';
            $product_detail_image = isset($values[$product_detail_image_id]) ? $values[$product_detail_image_id] : '';
            $product_price = isset($values[$product_price_id]) ? $values[$product_price_id] : '';
            $product_inventory = isset($values[$product_inventory_id]) ? $values[$product_inventory_id] : '';
            $product_sku = isset($values[$product_sku_id]) ? $values[$product_sku_id] : '';

            $store_id = mailchimp_get_store_id();

            if ($this->api_loader->getStoreProduct($store_id, $product_id)) {
                $this->api_loader->deleteStoreProduct($store_id, $product_id);
            }

            try {
                $product = new MailChimp_WooCommerce_Product();

                $product->setId($product_id);
                $product->setTitle($product_title);
                $product->setHandle($product_title);
                $product->setImageUrl($this->parse_file_server_paths($product_thumbnail));
                $product->setDescription($product_description);
                $product->setPublishedAtForeign(mailchimp_date_utc($product_entry_date));
                $product->setUrl($product_url);

                // Create a new Variant for the product
                $variant = new MailChimp_WooCommerce_ProductVariation();
                $variant->setId($product_id);
                $variant->setTitle($product_title);
                $variant->setUrl($product_url);
                $variant->setPrice($product_price);
                $variant->setImageUrl($this->parse_file_server_paths($product_thumbnail));
                if (!empty($product_inventory)) $variant->setInventoryQuantity($product_inventory);
                if (!empty($product_sku)) $variant->setSku($product_sku);
                if ($product_status == 'open') $variant->setVisibility('visible');

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

        return true;
    }


    //
    // Called before the channel entry object is inserted or updated. (refer to `save_post`)
    // Changes made to the object will be saved automatically.
    //
    public function mc_after_channel_entry_save($entry, $values) {

        $this->initialize();

        if ($this->check_store_sync() == false) return false;

        // mc_log(get_class_methods(get_class($entry->getStructure()->getFields())));
        // mc_log($entry->getStructure()->getFields());
        // mc_log($entry->getStructure()->toArray());    line: 11599


        //
        // Get products channel id
        //
        $products_channel_id_array = ee()->cartthrob->store->config('product_channels');
        if ($products_channel_id_array == false)    // Check whether the product channel exists
            return false;

        mc_log('after_channel_entry_save (product new or update) => ' . $values['entry_id']);

        if (in_array($values['channel_id'], $products_channel_id_array)) {    // It is the entry that being updated in the product channel

            //
            // Get the field_id_x
            //
            $fields = $this->get_product_channel_fields($values['channel_id']);

            $product_description_id = 'field_id_'.(isset($fields['description']) ? $fields['description'] : '');
            $product_thumbnail_id = 'field_id_'.(isset($fields['image_url']) ? $fields['image_url'] : '');
            $product_detail_image_id = 'field_id_'.(isset($fields['detail_image_url']) ? $fields['detail_image_url'] : '');
            $product_price_id = 'field_id_'.(isset($fields['price']) ? $fields['price'] : '');
            $product_inventory_id = 'field_id_'.(isset($fields['inventory']) ? $fields['inventory'] : '');
            $product_sku_id = 'field_id_'.(isset($fields['sku']) ? $fields['sku'] : '');


            //
            // Get the values of field_id_x
            //
            $product_id = $values['entry_id'];
            $product_title = $values['title'];
            $product_url = $values['url_title'];
            $product_entry_date = $values['entry_date'];
            $product_status = $values['status'];
            $product_description = isset($values[$product_description_id]) ? $values[$product_description_id] : '';
            $product_thumbnail = isset($values[$product_thumbnail_id]) ? $values[$product_thumbnail_id] : '';
            $product_detail_image = isset($values[$product_detail_image_id]) ? $values[$product_detail_image_id] : '';
            $product_price = isset($values[$product_price_id]) ? $values[$product_price_id] : '';
            $product_inventory = isset($values[$product_inventory_id]) ? $values[$product_inventory_id] : '';
            $product_sku = isset($values[$product_sku_id]) ? $values[$product_sku_id] : '';

            $store_id = mailchimp_get_store_id();

            mc_log('mc_after_channel_entry_save => product_id : '. $product_id);    // chen_debug

            if ($this->api_loader->getStoreProduct($store_id, $product_id)) {
                $this->api_loader->deleteStoreProduct($store_id, $product_id);
            }

            if (isset($values['submit'])) {     // Only for `save` or `update` product
                try {
                    $product = new MailChimp_WooCommerce_Product();

                    $product->setId($product_id);
                    $product->setTitle($product_title);
                    $product->setHandle($product_title);
                    $product->setImageUrl($this->parse_file_server_paths($product_thumbnail));
                    $product->setDescription($product_description);
                    $product->setPublishedAtForeign(mailchimp_date_utc($product_entry_date));
                    $product->setUrl($product_url);

                    // Create a new Variant for the product
                    $variant = new MailChimp_WooCommerce_ProductVariation();
                    $variant->setId($product_id);
                    $variant->setTitle($product_title);
                    $variant->setUrl($product_url);
                    $variant->setPrice($product_price);
                    $variant->setImageUrl($this->parse_file_server_paths($product_thumbnail));
                    if (!empty($product_inventory)) $variant->setInventoryQuantity($product_inventory);
                    if (!empty($product_sku)) $variant->setSku($product_sku);
                    if ($product_status == 'open') $variant->setVisibility('visible');

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


    //
    // Called during checkout if you are attempting to create a member during checkout.
    //
    public function mc_cartthrob_create_member($data, &$obj) {
        $this->initialize();

        if ($this->check_store_sync() == false) return false;

        mc_log('cartthrob_create_member');

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

        mc_log('member_login => '.$userdata->email. ' ( '. $userdata->username .' )');

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

        return $this->handle_cart_updated();
    }

    
    //
    // Additional processing when update cart action ends. (refer to `woocommerce_cart_updated`)
    //
    public function mc_cartthrob_update_cart_end() {

        $this->initialize();

        if ($this->check_store_sync() == false) return false;

        mc_log('cartthrob_update_cart_end');

        return $this->handle_cart_updated();

        /*$customer_info = ee()->cartthrob->cart->customer_info();
        $ret = ee()->session->cache;*/

        /*$item = ee()->cartthrob->cart->item(0);
        mc_log($item->price());*/

        /*if (!empty($customer_info['email_address']))
            mc_log($customer_info);*/
    }

    //
    // Additional processing when a delete from cart action is about to end. (refer to `woocommerce_cart_item_removed`)
    //
    public function mc_cartthrob_delete_from_cart_end() {

        $this->initialize();

        if ($this->check_store_sync() == false) return false;

        mc_log('cartthrob_delete_from_cart_end');

        return $this->handle_cart_updated();
    }


    //
    // Process mailchimp customer subscribe options
    //
    private function mc_submit_user($email, $old_email, $subscribe, $merge_vars) {

        $subscribe = ($subscribe == 'y') ? true : false;

        $list_id = $this->get_general_setting('mc_list_id');

        try {

            // see if we have a member.
            $ret = $this->api_loader->member($list_id, $old_email);

            // if we're updating a member and the email is different, we need to delete the old person
            if ($old_email != $email)
            {
                $this->api_loader->deleteMember($list_id, $old_email);
                $this->api_loader->subscribe($list_id, $email, $subscribe, $merge_vars);

                mailchimp_log('member.sync', 'Subscriber Swap '.$old_email.' to '.$email, $merge_vars);

                return false;
            }

            // ok let's update this member
            $this->api_loader->update($list_id, $email, $subscribe, $merge_vars);
            mailchimp_log('member.sync', 'Updated Member '.$email, $merge_vars);

        } catch (\Exception $e) {

            if ($e->getCode() == 404) {     // member not found
                try {
                    $this->api_loader->subscribe($list_id, $email, $subscribe, $merge_vars);

                    mailchimp_log('member.sync', 'Subscribed Member '.$email, $merge_vars);
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
    // When checkout form is submitted, but before the data is sent to the payment gateway file.
    //
    public function mc_cartthrob_pre_process($options) {

        $this->initialize();

        if ($this->check_store_sync() == false) return false;

        mc_log('mc_cartthrob_pre_process');

        $subscribe = ee()->input->post('mc_subscriber_check') ? 'y' : 'n';

        $customer_info = ee()->cartthrob->cart->customer_info();
        $email = $customer_info['email_address'];
        $merge_vars = array(
            'FNAME' => $customer_info['first_name'],
            'LNAME' => $customer_info['last_name'],
        );

        if ($member_id = ee()->session->userdata('member_id'))      // user logged in already
        {
            ee()->settings_model->subscribe($member_id, $subscribe);    // save subscribe option

            $old_email = ee()->session->userdata('email');

            return $this->mc_submit_user($email, $old_email, $subscribe, $merge_vars);
        }
        else if (!empty($options['create_user']) && is_array($options['create_user'])) {    // create user when checkout

            $old_email = $options['create_user']['email'];      // get email from `create_user` options

            return $this->mc_submit_user($email, $old_email, $subscribe, $merge_vars);
        }

        return true;
    }

    //
    // Additional processing when the payment has been authorized. (refer to `woocommerce_thankyou`)
    //
    public function mc_cartthrob_on_authorize() {
        
        $this->initialize();

        if ($this->check_store_sync() == false) return false;

        mc_log('cartthrob_on_authorize');

        $order_id = ee()->cartthrob->cart->order('order_id');

        // for the debugging and logging
        // $entry_id = ee()->cartthrob->cart->order('entry_id');
        // $order_item = ee()->cartthrob->cart->order();
        // $order_info = ee()->order_model->get_order($entry_id);
        // $customer_info = ee()->cartthrob->cart->customer_info();


        if (!array_key_exists($order_id, $this->pushed_orders)) {

            // register this order is already in process..
            $this->pushed_orders[$order_id] = true;

            // see if we have a session id and a campaign id, also only do this when this user is not the admin.
            $campaign_id = $this->getCampaignTrackingID();

            // queue up the single order to be processed.
            // $handler = new MailChimp_WooCommerce_Single_Order($order_id, null, $campaign_id);
            // wp_queue($handler);

            $store_id = mailchimp_get_store_id();

            // only if we have the right parameters to do the work
            if (!empty($store_id)) {

                $call = $this->api_loader->getStoreOrder($store_id, $order_id) ? 'updateStoreOrder' : 'addStoreOrder';

                // if we already pushed this order into the system, we need to unset it now just in case there
                // was another campaign that had been sent and this was only an order update.
                if ($call === 'updateStoreOrder') {
                    $campaign_id = null;
                }

                // will either add or update the order
                try {

                    // transform the order
                    $order = $this->transformOrder($order_id, $campaign_id);

                    // will be the same as the customer id. an md5'd hash of a lowercased email.
                    $cart_session_id = $order->getCustomer()->getId();

                    $log = "$call :: #{$order->getId()} :: email: {$order->getCustomer()->getEmailAddress()}";

                    if (!empty($campaign_id) && $call === 'addStoreOrder') {
                        $log .= ' :: campaign id '.$campaign_id;
                        $order->setCampaignId($campaign_id);
                    }

                    mailchimp_log('order_submit.submitting', $log);

                    // update or create
                    $api_response = $this->api_loader->$call($store_id, $order, false);

                    if (empty($api_response)) {
                        return $api_response;
                    }

                    mailchimp_log('order_submit.success', $log);

                    // if we're adding a new order and the session id is here, we need to delete the AC cart record.
                    if (!empty($cart_session_id)) {
                        $this->api_loader->deleteCartByID($store_id, $cart_session_id);
                    }

                    return $api_response;

                } catch (\Exception $e) {

                    mailchimp_log('order_submit.tracing_error', $message = strtolower($e->getMessage()));

                    if (!isset($order)) {
                        // transform the order
                        $order = $this->transformOrder($order_id, $campaign_id);
                        $cart_session_id = $order->getCustomer()->getId();
                    }

                    // this can happen when a customer changes their email.
                    if (isset($order) && strpos($message, 'not be changed')) {

                        try {

                            mailchimp_log('order_submit.deleting_customer', "#{$order->getId()} :: email: {$order->getCustomer()->getEmailAddress()}");

                            // delete the customer before adding it again.
                            $this->api_loader->deleteCustomer($store_id, $order->getCustomer()->getId());

                            // update or create
                            $api_response = $this->api_loader->$call($store_id, $order, false);

                            $log = "Deleted Customer :: $call :: #{$order->getId()} :: email: {$order->getCustomer()->getEmailAddress()}";

                            if (!empty($campaign_id)) {
                                $log .= ' :: campaign id '.$campaign_id;
                            }

                            mailchimp_log('order_submit.success', $log);

                            // if we're adding a new order and the session id is here, we need to delete the AC cart record.
                            if (!empty($cart_session_id)) {
                                $this->api_loader->deleteCartByID($store_id, $cart_session_id);
                            }

                            return $api_response;

                        } catch (\Exception $e) {
                            mailchimp_log('order_submit.error', 'deleting-customer-re-add :: #'.$order_id.' :: '.$e->getMessage());
                        }
                    }
                }
            }

            return false;
        }

        return true;
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
        $line->setId('LINE'.$hash);
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

    private function handle_cart_updated() {

        if ($this->is_admin || $this->cart_was_submitted) return false;

        if (empty($this->cart)) {
            $this->cart = $this->getCartItems();
        }

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
                        // $checkout_url = ee()->functions->create_url(ee()->uri->uri_string());   // check `checkout_url` again
                        $site_url = ee()->config->site_url();
                        $checkout_url = rtrim($site_url, '/').'/store/view_cart';    // also check `checkout_url`

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
                            foreach ($products as $item) {
                                // @var MailChimp_WooCommerce_LineItem $item
                                $this->add_product_by_entry_id($item->getProductId());

                                // $transformer = new MailChimp_WooCommerce_Single_Product($item->getProductId());
                                // if (!$transformer->api()->getStoreProduct($store_id, $item->getProductId())) {
                                //     $transformer->handle();
                                // }
                            }

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

    private function transformOrder($order_id, $campaign_id) 
    {
        $order = new MailChimp_WooCommerce_Order();

        $order->setId($order_id);

        // if we have a campaign id let's set it now.
        if (!empty($campaign_id)) {
            $order->setCampaignId($campaign_id);
        }

        ee()->load->model('order_model');
        $entry_id = ee()->cartthrob->cart->order('entry_id');       // check entry_id if same as order_id
        $order_item = ee()->cartthrob->cart->order();
        $order_info = ee()->order_model->get_order($entry_id);

        // get the status of order
		$order_status = ee()->order_model->get_order_status($order_id);     // processing, authorized, decliend, failed ...
        $order_date = mailchimp_date_utc($order_info['entry_date']);

        $order->setFulfillmentStatus($order_status);
        $order->setProcessedAt($order_date);

        if ($order_status === 'declined') {
            $order->setCancelledAt($order_date);
        }

        $order->setCurrencyCode($order_item['currency_code']);
        $order->setFinancialStatus($order_status == 'authorized' ? 'paid' : 'pending');

        $order->setOrderTotal($order_item['total']);

        // if we have any tax
        $order->setTaxTotal($order_item['tax']);

        // if we have shipping.
        $order->setShippingTotal($order_item['shipping']);

        // set the customer
        $order->setCustomer($this->build_customer_from_order($order_item));

        // apply the addresses to the order

        $addresses = $this->get_order_addresses($order_item);
        $order->setShippingAddress($addresses->shipping);
        $order->setBillingAddress($addresses->billing);

        // loop through all the order items
        foreach ($order_item['items'] as $key => $order_detail) {

            // add it into the order item container.
            $item = $this->build_line_item($key, $order_detail);

            // if we don't have a product post with this id, we need to add a deleted product to the MC side (deleted products check later)
            /* if (!($product_post = get_post($item->getProductId()))) {

                // check if it exists, otherwise create a new one.
                if (($deleted_product = MailChimp_WooCommerce_Transform_Products::deleted($item->getProductId()))) {

                    $deleted_product_id = "deleted_{$item->getProductId()}";

                    // swap out the old item id and product variant id with the deleted version.
                    $item->setProductId($deleted_product_id);
                    $item->setProductVariantId($deleted_product_id);

                    // add the item and continue on the loop.
                    $order->addItem($item);
                    continue;
                }

                mailchimp_log('order.items.error', "Order #{$woo->id} :: Product {$item->getProductId()} does not exist!");
                continue;
            } */

            $order->addItem($item);
        }

        return $order;
    }


    /**
     * @param $key
     * @param $order_detail
     * @return MailChimp_WooCommerce_LineItem
     */
    private function build_line_item($key, $order_detail)
    {
        // fire up a new MC line item
        $item = new MailChimp_WooCommerce_LineItem();
        $item->setId('LINE'.$key);
        $item->setProductId($order_detail['product_id']);

        // Not consider `variation_id`
        if (isset($order_detail['variation_id']) && $order_detail['variation_id'] > 0) {
            $item->setProductVariantId($order_detail['variation_id']);
        } else {
            $item->setProductVariantId($order_detail['product_id']);
        }

        $item->setQuantity($order_detail['quantity']);
        $item->setPrice($order_detail['price']);

        return $item;
    }

    private function get_order_addresses($order_item) {

        // use the info from the order to compile an address.
        $billing = new MailChimp_WooCommerce_Address();
        $billing->setAddress1($order_item['billing_address']);
        $billing->setAddress2($order_item['billing_address2']);
        $billing->setCity($order_item['billing_city']);
        $billing->setProvince($order_item['billing_state']);
        $billing->setPostalCode($order_item['billing_zip']);
        $billing->setCountry($order_item['billing_country_code']);
        $billing->setPhone($order_item['customer_phone']);
        $billing->setName('billing');

        $shipping = new MailChimp_WooCommerce_Address();
        $shipping->setAddress1($order_item['shipping_address']);
        $shipping->setAddress2($order_item['shipping_address2']);
        $shipping->setCity($order_item['shipping_city']);
        $shipping->setProvince($order_item['shipping_state']);
        $shipping->setPostalCode($order_item['shipping_zip']);
        $shipping->setCountry($order_item['shipping_country_code']);
        $shipping->setPhone($order_item['customer_phone']);
        $shipping->setName('shipping');

        return (object) array('billing' => $billing, 'shipping' => $shipping);
    }

    private function build_customer_from_order($order_item) {
        $customer = new MailChimp_WooCommerce_Customer();

        $customer->setId(md5(trim(strtolower($order_item['email_address']))));
        $customer->setCompany($order_item['billing_company']);
        $customer->setEmailAddress(trim($order_item['customer_email']));
        $customer->setFirstName($order_item['billing_first_name']);
        $customer->setLastName($order_item['billing_last_name']);
        $customer->setOrdersCount(1);
        $customer->setTotalSpent($order_item['total']);

        // we are saving the post meta for subscribers on each order... so if they have subscribed on checkout
        $subscriber_meta = $order_item['subscription'];
        $subscribed_on_order = $subscriber_meta === '' ? false : (bool) $subscriber_meta;

        $customer->setOptInStatus($subscribed_on_order);

        // use the info from the order to compile an address.
        $address = new MailChimp_WooCommerce_Address();
        $address->setAddress1($order_item['billing_address']);
        $address->setAddress2($order_item['billing_address2']);
        $address->setCity($order_item['billing_city']);
        $address->setProvince($order_item['billing_state']);
        $address->setPostalCode($order_item['billing_zip']);
        $address->setCountry($order_item['billing_country_code']);
        $address->setPhone($order_item['customer_phone']);
        $address->setName('billing');

        $customer->setAddress($address);

        // check after
        /*$customer_info = ee()->cartthrob->cart->customer_info();
        $customer->setOrdersCount($customer_info['orders_count']);
        $customer->setTotalSpent($customer_info['total_spent']);*/

        return $customer;
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


    /**
     * @return mixed|null
     */
    private function getCampaignTrackingID()
    {
        $cookie = $this->cookie('mc_cart_campaign_id', false);
        if (empty($cookie)) {
            $cookie = $this->getWooSession('mc_cart_tracking_id', false);
        }

        return $cookie;
    }

    /**
     * @param $id
     * @param $cookie_duration
     * @return $this
     */
    private function setCampaignTrackingID($id, $cookie_duration) {

        $cid = trim($id);

        @setcookie('mc_cart_campaign_id', $cid, $cookie_duration, '/');
        $this->setWooSession('mc_cart_campaign_id', $cid);

        return $this;
    }

    /**
     * @return bool
     */
    private function getEmailFromSession() {
        return $this->cookie('mc_cart_user_email', false);
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
        $email = $this->cookie('mc_cart_user_previous_email', false);
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
        // initialize and check the mailchimp sync status
        $this->initialize();

        if ($this->check_store_sync() == false) return false;


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
        // initialize and check the mailchimp sync status
        $this->initialize();

        if ($this->check_store_sync() == false) return false;

        mc_log('set_user_by_email');

        if ($this->is_admin) {
            $this->respondJSON(array('success' => false));
        }

        if (ee()->input->is_ajax_request() && isset($_GET['email'])) {

            $cookie_duration = $this->getCookieDuration();

            $this->user_email = trim(str_replace(' ','+', $_GET['email']));

            if (($current_email = $this->getEmailFromSession()) && $current_email !== $this->user_email) {
                $this->previous_email = $current_email;
                $this->force_cart_post = true;
                @setcookie('mc_cart_user_previous_email',$this->user_email, $cookie_duration, '/' );
            }

            @setcookie('mc_cart_user_email', $this->user_email, $cookie_duration, '/' );

            $this->getCartItems();

            $this->handle_cart_updated();

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

        ee()->carts_model->save_cart($uid, $email, $user_id, maybe_serialize($this->cart));

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