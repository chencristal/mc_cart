<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');?>
<?php
    $list_status = $mc_mcp->get_mailchimp_list_status();
?>

    <table class="mainTable padTable mc_list_id_panel" border="0" cellspacing="0" cellpadding="0">
        <caption><?php echo lang('mc_list_id_panel'); ?></caption>
        <thead class=""> </thead>
        <tbody>
        <tr class="<?php echo alternator('even', 'odd');?>">
            <td style="width: 50%">
                <strong class="red"><?php echo lang('mc_product_channel');?>:</strong>
                <br /><?php echo lang('mc_product_channel_description');?>
            </td>
            <td>
                <?php
                $attrs = "";
                if ($list_status['is_synced'] == 'y')
                    $attrs = "disabled='disabled'";

                echo form_dropdown('mc_list_id', $list_status['list_id_array'], $list_status['list_id'], $attrs);
                ?>

                <?php
                if ($list_status['is_synced'] == 'y'):
                ?>
                <a class="btn action" href="<?php echo ee('CP/URL')->make('addons/settings/mc_cart/resync');?>">RESYNC</a>
                <?php endif; ?>
            </td>
        </tr>
        </tbody>
    </table>


    <table class="mainTable padTable mc_store_settings_panel" border="0" cellspacing="0" cellpadding="0">
        <caption><?php echo lang('mc_store_settings_panel'); ?></caption>
        <thead class=""> </thead>
        <tbody>
        <tr class="<?php echo alternator('even', 'odd');?>">
            <td style="width: 50%">
                <strong class="red"><?php echo lang('mc_store_name');?>:</strong>
                <br /><?php echo lang('mc_store_name_desc');?>
            </td>
            <td>
                <?php
                $data = array(
                    'name'          => 'mc_store_name',
                    'value'         => $settings['mc_store_name'],
                );

                echo form_input($data);
                ?>
            </td>
        </tr>
        <tr class="<?php echo alternator('even', 'odd');?>">
            <td style="width: 50%">
                <strong class="red"><?php echo lang('mc_store_email');?>:</strong>
                <br /><?php echo lang('mc_store_email_desc');?>
            </td>
            <td>
                <?php
                $data = array(
                    'name'          => 'mc_store_email',
                    'value'         => $settings['mc_store_email'],
                );

                echo form_input($data);
                ?>
            </td>
        </tr>
        <tr class="<?php echo alternator('even', 'odd');?>">
            <td style="width: 50%">
                <strong class="red"><?php echo lang('mc_store_address');?>:</strong>
                <br /><?php echo lang('mc_store_address_desc');?>
            </td>
            <td>
                <?php
                $data = array(
                    'name'          => 'mc_store_address',
                    'value'         => $settings['mc_store_address'],
                );

                echo form_input($data);
                ?>
            </td>
        </tr>
        <tr class="<?php echo alternator('even', 'odd');?>">
            <td style="width: 50%">
                <strong class="red"><?php echo lang('mc_store_city');?>:</strong>
                <br /><?php echo lang('mc_store_city_desc');?>
            </td>
            <td>
                <?php
                $data = array(
                    'name'          => 'mc_store_city',
                    'value'         => $settings['mc_store_city'],
                );

                echo form_input($data);
                ?>
            </td>
        </tr>
        <tr class="<?php echo alternator('even', 'odd');?>">
            <td style="width: 50%">
                <strong class="red"><?php echo lang('mc_store_state');?>:</strong>
                <br /><?php echo lang('mc_store_state_desc');?>
            </td>
            <td>
                <?php
                $data = array(
                    'name'          => 'mc_store_state',
                    'value'         => $settings['mc_store_state'],
                );

                echo form_input($data);
                ?>
            </td>
        </tr>
        <tr class="<?php echo alternator('even', 'odd');?>">
            <td style="width: 50%">
                <strong class="red"><?php echo lang('mc_store_postal_code');?>:</strong>
                <br /><?php echo lang('mc_store_postal_code_desc');?>
            </td>
            <td>
                <?php
                $data = array(
                    'name'          => 'mc_store_postal_code',
                    'value'         => $settings['mc_store_postal_code'],
                );

                echo form_input($data);
                ?>
            </td>
        </tr>
        <tr class="<?php echo alternator('even', 'odd');?>">
            <td style="width: 50%">
                <strong class="red"><?php echo lang('mc_store_country');?>:</strong>
                <br /><?php echo lang('mc_store_country_desc');?>
            </td>
            <td>
                <?php
                $data = array(
                    'name'          => 'mc_store_country',
                    'value'         => $settings['mc_store_country'],
                );

                echo form_input($data);
                ?>
            </td>
        </tr>
        <tr class="<?php echo alternator('even', 'odd');?>">
            <td style="width: 50%">
                <strong class="red"><?php echo lang('mc_store_phone');?>:</strong>
                <br /><?php echo lang('mc_store_phone_desc');?>
            </td>
            <td>
                <?php
                $data = array(
                    'name'          => 'mc_store_phone',
                    'value'         => $settings['mc_store_phone'],
                );

                echo form_input($data);
                ?>
            </td>
        </tr>
        </tbody>
    </table>

    <table class="mainTable padTable mc_locale_settings_panel" border="0" cellspacing="0" cellpadding="0">
        <caption><?php echo lang('locale_settings'); ?></caption>
        <thead class=""> </thead>
        <tbody>
        <tr class="<?php echo alternator('even', 'odd');?>">
            <td style="width: 50%">
                <strong class="red"><?php echo lang('mc_locale');?>:</strong>
                <br /><?php echo lang('mc_locale_desc');?>
            </td>
            <td>
                <?php
                $attrs = "";

                echo form_dropdown('mc_locale', $locale_list, $settings['mc_locale'], $attrs);
                ?>
            </td>
        </tr>
        <tr class="<?php echo alternator('even', 'odd');?>">
            <td style="width: 50%">
                <strong class="red"><?php echo lang('mc_currency');?>:</strong>
                <br /><?php echo lang('mc_currency_desc');?>
            </td>
            <td>
                <?php
                $attrs = "";

                echo form_dropdown('mc_currency', $currency_list, $settings['mc_currency'], $attrs);
                ?>
            </td>
        </tr>
        <tr class="<?php echo alternator('even', 'odd');?>">
            <td style="width: 50%">
                <strong class="red"><?php echo lang('mc_timezone');?>:</strong>
                <br /><?php echo lang('mc_timezone_desc');?>
            </td>
            <td>
                <?php
                $attrs = "";

                echo form_dropdown('mc_timezone', $timezone_list, $settings['mc_timezone'], $attrs);
                ?>
            </td>
        </tr>
        </tbody>
    </table>