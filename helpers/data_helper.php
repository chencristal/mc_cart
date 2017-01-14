<?php


/**
 * @return array
 */
function mailchimp_get_timezone_list() {
    $zones_array = array();
    $timestamp = time();
    $current = date_default_timezone_get();

    foreach(timezone_identifiers_list() as $key => $zone) {
        date_default_timezone_set($zone);
        $zones_array[$key]['zone'] = $zone;
        $zones_array[$key]['diff_from_GMT'] = 'UTC/GMT ' . date('P', $timestamp);
    }

    date_default_timezone_set($current);

    return $zones_array;
}

/**
 * @return array
 */
function get_timezone_list() {
    $zones_array = array();
    $timestamp = time();
    $current = date_default_timezone_get();

    foreach(timezone_identifiers_list() as $key => $zone) {
        date_default_timezone_set($zone);
        $zones_array[$zone] = 'UTC/GMT ' . date('P', $timestamp) . ' - ' . $zone;
    }

    date_default_timezone_set($current);

    return $zones_array;
}

//
// Get value from array with its key
//
function getSettingsValue($data_array, $field_name, $default_value = '') {

    if (isset($data_array[$field_name]))
        return $data_array[$field_name];

    return $default_value;
}
/**
 * Returns the value of an input using a data array first, then post value
 * and then a default value
 *
 * @access private
 * @param Model $model The SubscriberForm model
 * @param String $input_name The name of the input field separated by dots
 * 	for nested fields
 * @param String $default_value The value to default to
 * @return String The correct value for the input field
 */
function getValue($model, $input_name, $default_value = '')
{
    if (($value = getArrayValue($model, $input_name)) !== NULL)
    {
        return $value;
    }
    else if (($value = getArrayValue($_POST, $input_name)) !== NULL)
    {
        return $value;
    }
    else
    {
        return $default_value;
    }
}

/**
 * Get a nested array value given a dot-separated path
 *
 * @param  array  $array Array to traverse
 * @param  string $path  Dot-separated path
 * @return mixed         Array value
 */
function getArrayValue($array, $path)
{
    $path = explode('.', $path);

    if (is_array($array))
    {
        for ($i = $array; $key = array_shift($path); $i = $i[$key])
        {
            if ( ! isset($i[$key]))
            {
                return NULL;
            }
        }
    }
    else if (is_object($array))
    {
        $i = $array->{$path[0]};

        if (is_array($i) && count($path) > 1)
        {
            array_shift($path);
            for ($i = $i; $key = array_shift($path); $i = $i[$key])
            {
                if ( ! isset($i[$key]))
                {
                    return NULL;
                }
            }
        }
    }

    return $i;
}


// chen_debug
function mc_log($log_message) {
    if ($log_message === null)
        file_put_contents('2.txt', 'NULL', FILE_APPEND|LOCK_EX);
    else
        file_put_contents('2.txt', print_r($log_message, true), FILE_APPEND|LOCK_EX);
}

// EOF
