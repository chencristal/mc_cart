<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

require_once PATH_THIRD.'mc_cart/config.php';
/**
 * Addon Control Panel
 */
class Mc_cart_mcp
{
    private $initialized = FALSE;

    private $module_name = MC_CART_MACHINE;
    private $api_loader = null;
    private $locale_list, $currency_list, $timezone_list;

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

    private $nav = array(
        'mc_store_settings' => array(
            'mc_list_id_settings' => 'nav_mc_list_id_settings',
        ),
        'mc_product_settings' => array(
            'mc_product_channels' => 'nav_mc_product_channels',
        ),
    );

    public $general_settings = array();     // mc_cart_settings {key: value}
    private $cartthrob_settings = array();

    function __construct()
    {
        // Setup the base url to the module
        $this->form_base = 'C=addons_modules'.AMP.'M=show_module_cp'.AMP.'module=mc_cart';
        $this->base = BASE.AMP.$this->form_base;

        $this->base = ee('CP/URL', 'addons/settings/mc_cart');
        $this->subscriber_edit = ee('CP/URL', 'addons/settings/mc_cart/view');

        ee()->load->helper(array(/*'provider', */'data'));
        ee()->load->library('mailchimp_api');
        ee()->load->model(array('settings_model', 'channel_model', 'field_model'));

        ee()->load->add_package_path(PATH_THIRD.'cartthrob/');
        ee()->load->model('cartthrob_settings_model');
        ee()->load->library('cartthrob_loader');
        ee()->load->library('get_settings');
        ee()->load->library('number');

        if (! $this->cartthrob_enabled() )
        {
            ee()->session->set_flashdata($this->module_name.'_system_error', sprintf('%s', lang($this->module_name.'_cartthrob_must_be_installed')));
            ee()->functions->redirect(ee('CP/URL')->make(''));
        }

        if (! ee()->cartthrob->store->config('save_orders') && ! ee()->cartthrob->store->config('orders_channel'))
        {
            ee()->session->set_flashdata($this->module_name.'_system_error', sprintf('%s', lang($this->module_name.'_orders_channel_must_be_configured')));
            ee()->functions->redirect(ee('CP/URL')->make('addons/settings/cartthrob/order_settings'));
        }

        $this->cartthrob_settings = ee()->cartthrob_settings_model->get_settings();
    }

    // ------------------------------------------------------------------------

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

        //
        // Get the generic setting values
        //
        $this->locale_list = MailChimp_Api_Locales::simple();
        $this->currency_list = MailChimp_WooCommerce_CurrencyCodes::lists();
        $this->timezone_list = get_timezone_list();

        //
        // Check store sync
        //
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
        $this->api_loader = ee()->mailchimp_api->create_loader($this->get_general_setting('mc_api_key'));

        $this->initialized = TRUE;

        return;
    }


    //
    // Get product fields from MC
    //
    private function get_product_channel_fields() {

        $ret = array();

        if ($this->get_general_setting('mc_product_fields') == '') {
            $mc_channel_fields = array();
        }
        else {
            $mc_channel_fields = unserialize($this->get_general_setting('mc_product_fields'));
        }

        foreach ($this->cartthrob_settings['product_channel_fields'] as $id => $fields) {
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

        return $ret;
    }


    public function index()
    {
        $this->initialize();

        $vars = array(
            'base_url'              => ee('CP/URL', 'addons/settings/mc_cart'),
            'cp_page_title'         => lang('mc_cart_settings_form'),
            'save_btn_text'         => 'btn_save_settings',
            'save_btn_text_working' => 'btn_saving'
        );

        // Validate the form
        if (!empty($_POST)) {
            $validator = ee('Validation')->make();

            $rules = array(
                'mc_api_key' => 'required',
                'mc_debugging' => 'required',
            );

            $validator->setRules($rules);
            $result = $validator->validate($_POST);

            if ($result->isValid()) {
                $this->save_settings();
            }
            else {
                $vars['errors'] = $result;
                ee('CP/Alert')->makeInline('shared-form')
                    ->asIssue()
                    ->withTitle(lang('settings_not_saved'))
                    ->now();
            }
        }

        // Setup standard fields
        $vars['sections'] = array(
            array(
                array( // section
                    'title' => 'mc_api_key',
                    'desc' => 'mc_api_key_desc',
                    'fields' => array(
                        'mc_api_key' => array(
                            'type' => 'text',
                            'value' => $this->get_general_setting('mc_api_key'),
                            'required' => TRUE
                        )
                    )
                ),
                array( // section
                    'title' => 'mc_debugging',
                    'desc' => 'mc_debugging_desc',
                    'fields' => array(
                        'mc_debugging' => array(
                            'type' => 'inline_radio',
                            'choices' => array(
                                'y' => 'Yes',
                                'n' => 'No'
                            ),
                            'value' => $this->get_general_setting('mc_debugging'),
                            'required' => TRUE
                        )
                    )
                ),
            ),
        );

        // ee()->cp->add_js_script(array('file' => array('cp/form_group')));

        return array(
            'body' => ee('View')->make('ee:_shared/form_with_box')->render($vars),
            'heading' => lang('mc_cart_settings_heading')
        );
    }

    public function quick_save($set_success_message = TRUE) {

        $this->initialize();

        $return = ee()->input->get('return');
        $message = sprintf('%s %s', lang('mc_cart_module_name'), lang('settings_saved'));

        if ($return == 'mc_product_settings') {     // PRODUCT CHANNEL SETTINGS

            ee()->settings_model->save_setting('mc_product_fields',
                serialize(ee()->input->post('product_channel_fields')));
        }
        else if ($return == 'mc_store_settings') {  // MAILCHIMP STORE SETTINGS
            // Validate the form
            if (!empty($_POST)) {
                $validator = ee('Validation')->make();

                $rules = array(
                    'mc_store_name' => 'required',
                    'mc_store_email' => 'required',
                    'mc_store_address' => 'required',
                    'mc_store_city' => 'required',
                    'mc_store_state' => 'required',
                    'mc_store_postal_code' => 'required',
                    'mc_store_country' => 'required',
                    'mc_store_phone' => 'required',
                );

                $validator->setRules($rules);
                $result = $validator->validate($_POST);

                if ($result->isValid()) {
                    $this->save_store_settings();
                }
                else {
                    $message = sprintf('%s : %s', lang('mc_cart_module_name'), lang('mc_fields_required'));
                    $vars['errors'] = $result;
                    ee('CP/Alert')->makeInline('shared-form')
                        ->asIssue()
                        ->withTitle(lang('settings_not_saved'))
                        ->now();
                }
            }
        }

        if ($set_success_message) {
            ee()->session->set_flashdata('message_success', $message);
        }

        ee()->functions->redirect(ee('CP/URL')->make('addons/settings/mc_cart/'.$return));
    }


    public function mc_store_settings() {
        $this->initialize();

        return $this->load_view(__FUNCTION__);
    }

    public function mc_product_settings() {
        $this->initialize();

        return $this->load_view(__FUNCTION__);
    }


    private function load_view($current_nav, $more = array(), $structure = array()) {

        //
        // If mailchimp API key is not valid, then redirect to base url
        //
        if ($this->get_general_setting('mc_api_key_valid') !== 'y') {
            ee()->functions->redirect($this->base);
        }

        $view_paths = array();
        $sections = array();

        $nav = $this->nav;

        foreach ($nav as $top_nav => $_nav) {
            if ($top_nav != $current_nav)
                continue;

            foreach ($_nav as $url_title => $section) {
                if ( ! preg_match('/^http/', $url_title)) $sections[] = $url_title;
            }
        }


        //
        // Get channels information
        //
        $channels = ee()->channel_model->get_channels(NULL, array(),
            array(array('channel_id' => $this->cartthrob_settings['product_channels'])))->result_array();
        $fields = array();
        $channel_titles = array();
        $statuses = array();

        foreach ($channels as $channel) {
            $channel_id = $channel['channel_id'];

            $channel_titles[$channel_id] = $channel['channel_title'];

            // only want to capture a subset of data, because we're using this for JSON and we were getting too much data previously
            $channel_fields = ee()->field_model->get_fields($channel['field_group'])->result_array();

            foreach ($channel_fields as $key => &$channel_field) {
                /*
				This is 5.2 only... sigh this will eventually replace the 3 lines below.
				$fields[$channel['channel_id']][$key] = array_intersect_key($data, array_fill_keys(array('field_id',
                        'site_id', 'group_id', 'field_name', 'field_type', 'field_label'), TRUE));
				*/
                $array_fill_keys = array('field_id', 'site_id', 'group_id', 'field_name', 'field_type', 'field_label');
                $combined = array_combine($array_fill_keys, array_fill(0, count($array_fill_keys), TRUE));
                $fields[$channel_id][$key] = array_intersect_key($channel_field, $combined);
            }

            $statuses[$channel_id] = ee()->channel_model->get_channel_statuses($channel['status_group'])->result_array();
        }

        $status_titles = array();
        foreach ($statuses as $status) {
            foreach ($status as $item) {
                $status_titles[$item['status']] = $item['status'];
            }
        }
        if ( ! empty($this->cartthrob_settings['product_channels'])) {
            foreach ($this->cartthrob_settings['product_channels'] as $i => $channel_id) {
                if ( ! isset($channel_titles[$channel_id])) {
                    unset($this->cartthrob_settings['product_channels'][$i]);
                }
            }
        }
        if ( ! empty($this->cartthrob_settings['product_channel_fields'])) {
            foreach ($this->cartthrob_settings['product_channel_fields'] as $channel_id => $values)
            {
                if ( ! isset($channel_titles[$channel_id])) {
                    unset($this->cartthrob_settings['product_channel_fields'][$channel_id]);
                }
            }
        }

        $data = array(
            'structure' => $structure,
            'nav' => $nav,
            'current_nav' => $current_nav,
            'sections' => $sections,
            'load' => ee()->load,
            'session' => ee()->session,
            'fields' => $fields,
            'channel_fields' => $this->get_product_channel_fields(),
            'channels' => $channels,
            'channel_titles' => $channel_titles,
            'statuses' => $statuses,
            'status_titles' => $status_titles,
            'cartthrob_settings' => $this->cartthrob_settings,
            'settings' => $this->general_settings,
            'locale_list' => $this->locale_list,
            'currency_list' => $this->currency_list,
            'timezone_list' => $this->timezone_list,
            'mc_mcp' => $this,
            'more' => $more,
            'form_open' => form_open(ee('CP/URL')->make('addons/settings/mc_cart/quick_save', array('return' => ee()->uri->segment(5)))),
        );

        ee()->cp->cp_page_title =  ee()->lang->line('mc_cart_module_name').' - '.ee()->lang->line('nav_'.$current_nav);

        ee()->cp->add_js_script('ui', 'accordion');

        if (version_compare(APP_VER, '2.2', '<'))
        {
            ee()->cp->add_to_head('<link href="'.URL_THIRD_THEMES.'cartthrob/css/cartthrob.css" rel="stylesheet" type="text/css" />');
            // ee()->cp->add_to_foot(ee()->load->view('mc_settings_form_head', $data, TRUE));

            $output = ee()->load->view('settings_form', $data, TRUE);
        }
        else
        {

            ee()->cp->add_to_head('<link href="'.URL_THIRD_THEMES.'cartthrob/css/cartthrob.css" rel="stylesheet" type="text/css" />');
            // ee()->cp->add_to_foot(ee()->load->view('mc_settings_form_head', $data, TRUE));

            $output = ee()->load->view('mc_settings_form', $data, TRUE);

            foreach ($view_paths as $path)
            {
                ee()->load->remove_package_path($path);
            }
        }

        return $output;
    }

    public function get_mailchimp_list_status() {
        //
        // First of all, Get mailchimp list array, and Check the store synced
        //
        $list_id = $this->get_general_setting('mc_list_id');
        $str_lists = $this->get_general_setting('mc_lists');
        $is_synced = $this->get_general_setting('mc_store_sync');
        if ($is_synced == 'y' && $list_id != '') {
            $lists = json_decode($str_lists, true);

            foreach ($lists['lists'] as $list) {
                $list_id_array[$list['id']] = $list['name'];
            }
        }
        else {
            $is_synced = 'n';
            $list_id_array = array();
            $lists = $this->api_loader->getLists();
            if ($lists != null) {
                ee()->settings_model->save_setting('mc_lists', json_encode($lists));

                foreach ($lists['lists'] as $list) {
                    $list_id_array[$list['id']] = $list['name'];
                }
            }
        }

        return array(
            'is_synced' => $is_synced,
            'list_id' => $list_id,
            'list_id_array' => $list_id_array,
        );
    }

    // ------------------------------------------------------------------------

    private function save_settings()
    {
        $this->initialize();

        $data = array(
            'mc_api_key' => ee()->input->post('mc_api_key'),
            'mc_debugging' => ee()->input->post('mc_debugging'),
            'mc_profile' => '',
            'mc_api_key_valid' => 'n',
        );

        $this->api_loader->setApiKey($data['mc_api_key']);

        ee()->session->set_flashdata(array(
            'message_success' => lang('preferences_updated')
        ));

        if (($profile = $this->api_loader->ping(true)) != null) {        // ApiKey validation success and return profile

            $data['mc_profile'] = json_encode($profile);
            $data['mc_api_key_valid'] = 'y';
            ee()->settings_model->save_settings($data);

            ee()->functions->redirect(ee('CP/URL')->make(
                'addons/settings/mc_cart/mc_store_settings'
            ));
        }
        else {            // ApiKey validation failed
            ee()->settings_model->save_settings($data);

            ee('CP/Alert')->makeInline('shared-form')
                ->asIssue()
                ->withTitle(lang('mc_api_key_not_valid'))
                ->now();
        }
    }

    private function save_store_settings()
    {
        $this->initialize();

        $data = array(
            'mc_list_id' => ee()->input->post('mc_list_id')?:$this->get_general_setting('mc_list_id'),
            'mc_store_name' => ee()->input->post('mc_store_name'),
            'mc_store_email' => ee()->input->post('mc_store_email'),
            'mc_store_address' => ee()->input->post('mc_store_address'),
            'mc_store_city' => ee()->input->post('mc_store_city'),
            'mc_store_state' => ee()->input->post('mc_store_state'),
            'mc_store_postal_code' => ee()->input->post('mc_store_postal_code'),
            'mc_store_country' => ee()->input->post('mc_store_country'),
            'mc_store_phone' => ee()->input->post('mc_store_phone'),
            'mc_locale' => ee()->input->post('mc_locale'),
            'mc_currency' => ee()->input->post('mc_currency'),
            'mc_timezone' => ee()->input->post('mc_timezone'),
        );

        ee()->session->set_flashdata(array(
            'message_success' => lang('preferences_updated')
        ));

        //
        // Process to sync the mailchimp store
        //
        if ($this->sync_store($data) === true) {

            $data['mc_store_sync'] = 'y';

            ee()->settings_model->save_settings($data);
            ee('CP/Alert')->makeInline('shared-form')
                ->asSuccess()
                ->withTitle(lang('mc_store_synced'))
                ->defer();

            ee()->functions->redirect(ee('CP/URL')->make(
                'addons/settings/mc_cart/mc_store_settings'
            ));
        }
        else {
            $data['mc_store_sync'] = 'n';
            ee()->settings_model->save_settings($data);

            ee('CP/Alert')->makeInline('shared-form')
                ->asIssue()
                ->withTitle(lang('mc_store_not_synced'))
                ->now();
        }
    }

    // Get general setting from $general_settings.
    // $key must be exist in the $default_settings array.
    private function get_general_setting($key) {
        if (isset($this->general_settings[$key]))
            return $this->general_settings[$key];

        return $this->default_settings[$key];
    }

    // -----------------------------------------------------------------------------

    public function resync() {
        $this->initialize();

        $data = array(
            'mc_store_sync' => 'n'
        );

        ee()->settings_model->save_settings($data);
        ee()->functions->redirect(ee('CP/URL')->make(
            'addons/settings/mc_cart/mc_store_settings'
        ));
    }

    // -----------------------------------------------------------------------------

    public function delete()
    {
        foreach (ee()->input->post('forms') as $form_id)
        {
            ee('Model')->get('subscriber:SubscriberForm')
                ->filter('id', $form_id)
                ->first()
                ->delete();
        }

        ee()->functions->redirect($this->base);
    }


    /**
     * cartthrob_enabled
     *
     * determines if cartthrob is enabled
     *
     * @return boolean
     * @author Chris Newton
     */
    private function cartthrob_enabled()
    {
        $this->initialize();

        $query = ee()->db->select('module_name')
            ->where_in('module_name', 'Cartthrob')
            ->get('modules');

        if ($query->result())
        {
            $query->free_result();

            return TRUE;
        }
        return false;
    }

    private function sync_store($data) {

        $this->initialize();

        $site_url = mailchimp_get_store_id();
        $new = false;

        if (!($store = $this->api_loader->getStore($site_url))) {
            $new = true;
            $store = new MailChimp_WooCommerce_Store();
        }

        $list_id = $this->get_general_setting('mc_list_id');
        $call = $new ? 'addStore' : 'updateStore';
        $time_key = $new ? 'store_created_at' : 'store_updated_at';

        $store->setId($site_url);
        $store->setPlatform('cartthrob');

        // set the locale data
        $store->setPrimaryLocale($data['mc_locale']);
        $store->setTimezone($data['mc_timezone']);
        $store->setCurrencyCode($data['mc_currency']);

        // set the basics
        $store->setName($data['mc_store_name']);
        $store->setDomain(ee()->config->item('site_url'));
        $store->setEmailAddress($data['mc_store_email']);
        $store->setAddress($this->address($data));
        $store->setPhone($data['mc_store_phone']);
        $store->setListId($list_id);

        try {
            // let's create a new store for this user through the API
            $this->api_loader->$call($store);

            // apply extra meta for store created at
            ee()->config->set_item('errors.store_info', false);
            ee()->config->set_item($time_key, time());

            return true;

        } catch (\Exception $e) {
            ee()->config->set_item('errors.store_info', $e->getMessage());
        }

        return false;
    }

    /**
     * @param array $data
     * @return MailChimp_WooCommerce_Address
     */
    private function address(array $data)
    {
        $address = new MailChimp_WooCommerce_Address();

        if (isset($data['mc_store_address']) && $data['mc_store_address']) {
            $address->setAddress1($data['mc_store_address']);
        }

        if (isset($data['mc_store_city']) && $data['mc_store_city']) {
            $address->setCity($data['mc_store_city']);
        }

        if (isset($data['mc_store_state']) && $data['mc_store_state']) {
            $address->setProvince($data['mc_store_state']);
        }

        if (isset($data['mc_store_country']) && $data['mc_store_country']) {
            $address->setCountry($data['mc_store_country']);
        }

        if (isset($data['mc_store_postal_code']) && $data['mc_store_postal_code']) {
            $address->setPostalCode($data['mc_store_postal_code']);
        }

        if (isset($data['mc_store_name']) && $data['mc_store_name']) {
            $address->setCompany($data['mc_store_name']);
        }

        if (isset($data['mc_store_phone']) && $data['mc_store_phone']) {
            $address->setPhone($data['mc_store_phone']);
        }

        if (isset($data['mc_currency']) && $data['mc_currency']) {
            $address->setCountryCode($data['mc_currency']);
        }


        return $address;
    }
}