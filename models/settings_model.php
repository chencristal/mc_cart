<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

require_once PATH_THIRD.'mc_cart/config.php';
/**
 * Settings Model
 */
class Settings_model extends CI_Model
{
    private $table = MC_CART_DB_SETTINGS;

    public function __construct()
    {
        parent::__construct();
    }

    public function get_all_settings() {
        $ret = array();

        $this->db->select("*");
        $result = $this->db->get($this->table)->result_array();

        foreach($result as $row) {
            $ret[$row['key']] = $row['value'];
        }

        return $ret;
    }

    public function get_setting($key) {
        $this->db->select("*");
        $this->db->where('key', $key);
        $result = $this->db->get($this->table)->result_array();
        if (count($result) > 0) {
            return $result[0]['value'];
        }

        return false;
    }

    public function save_settings($data) {

        $settings = $this->get_all_settings();

        foreach($data as $key => $val) {
            if (isset($settings[$key]))
                $this->db->update(
                    $this->table,
                    array('value' => $val),
                    array('key' => $key)
                );
            else {
                $this->db->insert(
                    $this->table,
                    array(
                        'value' => $val,
                        'key' => $key,
                    )
                );
            }
        }

        return TRUE;
    }

    public function save_setting($key, $val) {
        $settings = $this->get_all_settings();

        if (isset($settings[$key]))
            $this->db->update(
                $this->table,
                array('value' => $val),
                array('key' => $key)
            );
        else {
            $this->db->insert(
                $this->table,
                array(
                    'value' => $val,
                    'key' => $key,
                )
            );
        }

        return TRUE;
    }
}