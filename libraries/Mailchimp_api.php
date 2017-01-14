<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

require_once PATH_THIRD.'mc_cart/config.php';

class Mailchimp_api {

    public $loader = null;

    public function __construct()
    {
        $this->load_dependencies();
    }

    private function load_dependencies()
    {
        require_once PATH_THIRD.'mc_cart/libraries/api/global.php';

        require_once PATH_THIRD.'mc_cart/libraries/api/assets/class-mailchimp-address.php';
        require_once PATH_THIRD.'mc_cart/libraries/api/assets/class-mailchimp-cart.php';
        require_once PATH_THIRD.'mc_cart/libraries/api/assets/class-mailchimp-customer.php';
        require_once PATH_THIRD.'mc_cart/libraries/api/assets/class-mailchimp-line-item.php';
        require_once PATH_THIRD.'mc_cart/libraries/api/assets/class-mailchimp-order.php';
        require_once PATH_THIRD.'mc_cart/libraries/api/assets/class-mailchimp-product.php';
        require_once PATH_THIRD.'mc_cart/libraries/api/assets/class-mailchimp-product-variation.php';
        require_once PATH_THIRD.'mc_cart/libraries/api/assets/class-mailchimp-store.php';

        require_once PATH_THIRD.'mc_cart/libraries/api/errors/class-mailchimp-error.php';
        require_once PATH_THIRD.'mc_cart/libraries/api/errors/class-mailchimp-server-error.php';

        require_once PATH_THIRD.'mc_cart/libraries/api/helpers/class-mailchimp-woocommerce-api-currency-codes.php';
        require_once PATH_THIRD.'mc_cart/libraries/api/helpers/class-mailchimp-woocommerce-api-locales.php';

        require_once PATH_THIRD.'mc_cart/libraries/api/class-mailchimp-woocommerce-create-list-submission.php';
        require_once PATH_THIRD.'mc_cart/libraries/api/class-mailchimp-api.php';
    }

    public function create_loader($api_key) {
        if ($this->loader == null)
            $this->loader = new MailChimp_WooCommerce_MailChimpApi($api_key);
        else
            $this->loader->setApiKey($api_key);

        return $this->loader;
    }
}