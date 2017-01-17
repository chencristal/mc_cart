<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

require_once PATH_THIRD.'mc_cart/config.php';
/**
 * Carts Model
 */
class Carts_model extends CI_Model
{
    private $table = 'mc_carts';

    public function __construct()
    {
        parent::__construct();
    }

    public function get_saved_cart($id) {
        $this->db->select("*");
        $this->db->where('id', $id);
        $ret = $this->db->get($this->table)->result_array();

        if (count($ret) > 0)
            return $ret[0];
        return false;
    }

    public function delete_cart($id) {
        $this->db->where('id', $id);
        $this->db->delete($this->table);

        return true;
    }

    public function save_cart($id, $email, $user_id, $cart) {
        $this->db->select("*");
        $this->db->where('id', $id);
        $ret = $this->db->get($this->table)->result_array();

        if (count($ret) > 0) {
            $this->db->where('id', $id);
            $this->db->update($this->table, array(
                'email' => $email,
                'user_id' => (int) $user_id,
                'cart' => $cart,
                'created_at' => gmdate('Y-m-d H:i:s', time()),
            ));
        } else {
            $this->db->insert($this->table, array(
                'id' => $id,
                'email' => $email,
                'user_id' => (int) $user_id,
                'cart' => $cart,
                'created_at' => gmdate('Y-m-d H:i:s', time()),
            ));
        }

        return true;
    }
}