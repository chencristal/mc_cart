<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

require_once PATH_THIRD.'mc_cart/config.php';

/**
 * Addon Module Update
 */
class Mc_cart_upd {

    private $version = MC_CART_VER;

    private $tables = array(
        MC_CART_DB_SETTINGS => array(
            'site_id' => array(
                'type' => 'int',
                'constraint' => 5,
                'default' => 1,
            ),
            '`key`' => array(
                'type' => 'varchar',
                'constraint' => 255,
            ),
            'value' => array(
                'type' => 'text',
                'null' => TRUE,
            ),
            'serialized' => array(
                'type' => 'int',
                'constraint' => 1,
                'null' => TRUE,
                'default' => 0,
            )
        ),
        'mc_carts' => array(
            'id' => array(
                'type' => 'varchar',
                'constraint' => 255,
            ),
            'email' => array(
                'type' => 'varchar',
                'constraint' => 100,
            ),
            'user_id' => array(
                'type' => 'int',
                'constraint' => 11,
                'null' => TRUE,
            ),
            'cart' => array(
                'type' => 'text',                
            ),
            'created_at' => array(
                'type' => 'datetime',
            ),
        ),
        'mc_subscribers' => array(
            'member_id' => array(
                'type' => 'int',
                'constraint' => 11,
            ),
            'subscribe' => array(
                'type' => 'varchar',
                'constraint' => 100,
                'null' => TRUE,
            ),
            'created_at' => array(
                'type' => 'datetime',
            ),
        )
    );

    /**
     * Installs the module, creating the necessary tables
     */
    public function install() {
        // Insert module information into Modules table
        $data = array(
            "module_name"        => MC_CART_MACHINE,
            "module_version"     => $this->version,
            "has_cp_backend"     => "y",
            "has_publish_fields" => "n",
        );
        ee()->db->insert('modules', $data);

        ee()->load->model('table_model');
        ee()->table_model->update_tables($this->tables);

        $this->_register_hooks(array(
            // member actions
            'cartthrob_create_member' => 'mc_cartthrob_create_member',

            // product actions
            'after_channel_entry_save' => 'mc_after_channel_entry_save',
            
            // cart actions
            'cartthrob_add_to_cart_end' => 'mc_cartthrob_add_to_cart_end',
            'cartthrob_update_cart_end' => 'mc_cartthrob_update_cart_end',
            'cartthrob_delete_from_cart_end' => 'mc_cartthrob_delete_from_cart_end',

            // checkout actions
            'cartthrob_pre_process' => 'mc_cartthrob_pre_process',
            'cartthrob_on_authorize' => 'mc_cartthrob_on_authorize',

            // login actions
            'member_member_login_multi' => 'mc_member_login',
            'member_member_login_single' => 'mc_member_login',
            'cp_member_login' => 'mc_member_login',

            // core actions
            'core_template_route' => 'mc_core_template_route',
        ));

        return TRUE;
    }

    /**
     * Uninstalls the module, removing the database tables
     */
    public function uninstall()
    {
        ee()->load->dbforge();

        ee()->db->where('module_name', MC_CART_MACHINE)
            ->delete('modules');

        ee()->db->where('class', MC_CART_MACHINE)
            ->delete('actions');

        ee()->db->where('class', MC_CART_MACHINE.'_ext')
            ->delete('extensions');

        //should we do this?
        /*foreach (array_keys($this->tables) as $table)
        {
            ee()->dbforge->drop_table($table);
        }*/

        return TRUE;
    }

    /**
     * Update the module's database tables if necessary
     * @param String $current The installed module's current version
     */
    public function update($current = '')
    {
        if (version_compare($current, $this->version, '=='))
        {
            return FALSE;
        }

        return TRUE;
    }

    /**
     * Register a hook and it's method, ensuring it doesn't already exist
     *
     * @param  array  $hooks Associative array containing the method name as the
     *                       key and the hook as the value
     * @return void
     */
    private function _register_hooks(array $hooks)
    {
        foreach ($hooks as $hook => $method)
        {
            // Check to make sure the hook doesn't already exist
            $count = ee()->db->where('class', MC_CART_MACHINE.'_ext')
                ->where(compact('hook', 'method'))
                ->count_all_results('exp_extensions');

            if ($count == 0)
            {
                ee()->db->insert(
                    'exp_extensions',
                    array(
                        'class'    => MC_CART_MACHINE.'_ext',
                        'method'   => $method,
                        'hook'     => $hook,
                        'settings' => '',
                        'priority' => 10,
                        'version'  => $this->version,
                        'enabled'  => 'y'
                    )
                );
            }

        }
    }
}