<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * @return object
 */
function mailchimp_environment_variables() {

    return (object) array(
        'repo' => 'master',
        'environment' => 'production',
        'version' => '0.0.1',
    );
}

/**
 * Determine if a given string contains a given substring.
 *
 * @param  string  $haystack
 * @param  string|array  $needles
 * @return bool
 */
function mailchimp_string_contains($haystack, $needles)
{
    foreach ((array) $needles as $needle) {
        if ($needle != '' && mb_strpos($haystack, $needle) !== false) {
            return true;
        }
    }

    return false;
}

/**
 * @param $action
 * @param $message
 * @param array $data
 * @return array|WP_Error
 */
function mailchimp_log($action, $message, $data = array())
{
    // refer to woocommerce plugin      // chen_check
    if ($message === null)
        file_put_contents('2.txt', $action." ==>> NULL  \r\n", FILE_APPEND|LOCK_EX);
    else
        file_put_contents('2.txt', $action." ==>> ".print_r($message, true)."  \r\n", FILE_APPEND|LOCK_EX);

    if (count($data) > 0)
        file_put_contents('2.txt', $action."(data) ==>> ".print_r($data, true)."  \r\n", FILE_APPEND|LOCK_EX);

    return TRUE;
}

/**
 * @return string
 */
function mailchimp_get_store_id() {
    return md5(ee()->config->item('site_url'));
}

/**
 * @param $date
 * @return DateTime
 */
function mailchimp_date_utc($date) {
    ee()->load->model('settings_model');
    $timezone = ee()->settings_model->get_setting('mc_timezone');
    if ($timezone == false) $timezone = 'America/New_York';


    if (is_numeric($date)) {
        $stamp = $date;
        $date = new \DateTime('now', new DateTimeZone($timezone));
        $date->setTimestamp($stamp);
    } else {
        $date = new \DateTime($date, new DateTimeZone($timezone));
    }

    $date->setTimezone(new DateTimeZone('UTC'));
    return $date;
}

/**
 * @param array $data
 * @return mixed
 */
function mailchimp_array_remove_empty($data) {
    if (empty($data) || !is_array($data)) {
        return array();
    }
    foreach ($data as $key => $value) {
        if ($value === null || $value === '') {
            unset($data[$key]);
        }
    }
    return $data;
}