<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');?>

<?php foreach ($cartthrob_settings['product_channels'] as $product_channel) : ?>
    <?php
    $product_fields = array();
    $product_fields[''] = '';

    $product_channel = (int) $product_channel;
    $channel_dd = array();
    if (!empty($fields[$product_channel]))
    {
        foreach ($fields[$product_channel] as $field)
        {
            $product_fields[$field['field_id']] = $field['field_label'];
        }
    }

    ?>
    <table class="mainTable padTable ct_product_channels" border="0" cellspacing="0" cellpadding="0">
        <caption><?php echo lang('mc_product_channels_header'); ?></caption>
        <thead class="">
        <tr>
            <th colspan="2">
                <strong class="red"><?php echo lang('mc_product_channel_form_description'); ?></strong>
            </th>

        </tr>
        </thead>
        <tbody>
        <tr class="<?php echo alternator('even', 'odd');?>">
            <td>
                <strong class="red"><?php echo lang('mc_product_channel');?>:</strong>
                <br /><?php echo lang('mc_product_channel_description');?>
            </td>
            <td><strong>
                <?php

                //$attrs = "class='ct_product_column product_channel' id='section_products'";

                //echo form_dropdown('product_channels[]', $channel_dd, $selected_channel, $attrs);

                echo $channel_titles[$product_channel];
                ?>
                </strong>
            </td>
        </tr>
        <tr class="<?php echo alternator('even', 'odd');?>">
            <td>
                <strong><?php echo lang('mc_product_channel_price_field'); ?></strong><br />
                <?php echo lang('mc_product_channel_price_field_description'); ?>
            </td>
            <td>
                <?php
                $attrs = "class='ct_product_column product_price'";

                $curr = "";
                if ( isset($channel_fields[$product_channel]['price']) && $channel_fields[$product_channel]['price'] != null)
                {
                    $curr= $channel_fields[$product_channel]['price'];
                }
                echo form_dropdown('product_channel_fields['.$product_channel.'][price]', $product_fields, $curr, $attrs);

                ?>
            </td>
        </tr>

        <!-- description -->
        <tr class="<?php echo alternator('even', 'odd');?>">
            <td>
                <strong><?php echo lang('mc_product_channel_desc_field'); ?></strong><br />
                <?php echo lang('mc_product_channel_desc_field_description'); ?>
            </td>
            <td>
                <?php
                $attrs = "class='ct_product_column product_price'";

                $curr = "";
                if ( isset($channel_fields[$product_channel]['description']) && $channel_fields[$product_channel]['description'] != null)
                {
                    $curr= $channel_fields[$product_channel]['description'];
                }
                echo form_dropdown('product_channel_fields['.$product_channel.'][description]', $product_fields, $curr, $attrs);

                ?>
            </td>
        </tr>

        <!-- image_url -->
        <tr class="<?php echo alternator('even', 'odd');?>">
            <td>
                <strong><?php echo lang('mc_product_channel_image_url_field'); ?></strong><br />
                <?php echo lang('mc_product_channel_image_url_field_description'); ?>
            </td>
            <td>
                <?php
                $attrs = "class='ct_product_column product_image_url'";

                $curr = "";
                if ( isset($channel_fields[$product_channel]['image_url']) && $channel_fields[$product_channel]['image_url'] != null)
                {
                    $curr= $channel_fields[$product_channel]['image_url'];
                }
                echo form_dropdown('product_channel_fields['.$product_channel.'][image_url]', $product_fields, $curr, $attrs);

                ?>
            </td>
        </tr>
        <tr class="<?php echo alternator('even', 'odd');?>">
            <td>
                <strong><?php echo lang('mc_product_channel_shipping_field'); ?></strong><br />
                <?php echo lang('mc_product_channel_shipping_field_description'); ?>
            </td>
            <td>
                <?php

                $attrs = "class='ct_product_column product_shipping product_channel_fields'";

                $curr = "";
                if ( isset($channel_fields[$product_channel]['shipping']) && $channel_fields[$product_channel]['shipping'] != null)
                {
                    $curr= $channel_fields[$product_channel]['shipping'];
                }
                echo form_dropdown('product_channel_fields['.$product_channel.'][shipping]', $product_fields, $curr, $attrs);

                ?>
            </td>
        </tr>
        <tr class="<?php echo alternator('even', 'odd');?>">
            <td>
                <strong><?php echo lang('mc_product_channel_weight_field'); ?></strong><br />
                <?php echo lang('mc_product_channel_weight_field_description'); ?>
            </td>
            <td>
                <?php

                $attrs = "class='ct_product_column product_weight product_channel_fields'";

                $curr = "";
                if ( isset($channel_fields[$product_channel]['weight']) && $channel_fields[$product_channel]['weight'] != null)
                {
                    $curr= $channel_fields[$product_channel]['weight'];
                }
                echo form_dropdown('product_channel_fields['.$product_channel.'][weight]', $product_fields, $curr, $attrs);

                ?>
            </td>
        </tr>
        <tr class="<?php echo alternator('even', 'odd');?>">
            <td>
                <strong><?php echo lang('mc_product_channel_inventory_field'); ?></strong><br />
                <?php echo lang('mc_product_channel_inventory_field_description'); ?>
            </td>
            <td>
                <?php

                $attrs = "class='ct_product_column product_inventory product_channel_fields'";

                $curr = "";
                if ( isset($channel_fields[$product_channel]['inventory']) && $channel_fields[$product_channel]['inventory'] != null)
                {
                    $curr= $channel_fields[$product_channel]['inventory'];
                }

                echo form_dropdown('product_channel_fields['.$product_channel.'][inventory]', $product_fields, $curr, $attrs);

                ?>

            </td>
        </tr>
        </tbody>
    </table>
<?php endforeach; ?>