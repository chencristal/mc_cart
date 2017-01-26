<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

require_once PATH_THIRD.'subscriber/config.php';

/**
 * Addon Core Module File
 */
class Mc_cart
{
    public $return_data = '';

    public function checkout_subscriber() {

        // if (ee()->session->userdata('member_id')) {

            ee()->load->helper('form');
            ee()->lang->loadfile('mc_cart');

            $data = array(
                'name'      => 'mc_subscriber_check',
                'id'        => 'mc_subscriber_check',
                'value'     => 'accept',
                'checked'   => TRUE,
            );

            $ret = form_fieldset(lang('mc_subscriber_section'),
                    array(
                        'class' => 'mc_subscriber_section', 
                        'id' => 'mc_subscriber_section'
                    ));

            $ret.= '<div class="control-group">';
            $ret.= form_label(lang('mc_subscriber_label'), 'mc_subscriber_check', 
                    array('class' => 'control-label'));

            $ret.= '<div class="controls">';
            $ret.= form_checkbox($data);
            $ret.= '</div></div>';


            $ret.= form_fieldset_close();
            $this->return_data = $ret;
        // }
        
        return $this->return_data;
    }
}