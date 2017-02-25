<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed'); ?>

<!-- begin right column -->
<div class="ct_top_nav">
	<div class="ct_nav" >
			<?php foreach (array_keys($nav) as $method) : ?>
				<span class="button"><a class="nav_button<?php if ( ! ee()->uri->segment(5) || ee()->uri->segment(5) == $method) : ?> current<?php endif; ?>" href="<?=ee('CP/URL')->make('addons/settings/mc_cart/'.$method)?>"><?=lang('nav_'.$method)?></a></span>
			<?php endforeach; ?>
		<div class="clear_both"></div>
	</div>
</div>

<div class="clear_left shun"></div>

<?=$form_open?>

<div id="cartthrob_settings_content">
    <?php if (version_compare(APP_VER, '2.2', '<')) $orig_view_path = $load->_ci_view_path; ?>

    <?php foreach ($sections as $section) : ?>

        <?php if (version_compare(APP_VER, '2.2', '<')) $load->_ci_view_path = (isset($view_paths[$section])) ? $view_paths[$section] : $orig_view_path; ?>
        <?=$load->view($section, null, TRUE)?>

    <?php endforeach; ?>
</div>

<p><input type="submit" name="submit" value="Submit" class="submit" /></p>
</form>