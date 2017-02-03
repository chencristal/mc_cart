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
        file_put_contents('2.txt', date("Y-m-d H:i:s")." : ".$action." ==>> NULL  \r\n", FILE_APPEND|LOCK_EX);
    else
        file_put_contents('2.txt', date("Y-m-d H:i:s")." : ".$action." ==>> ".print_r($message, true)."  \r\n", FILE_APPEND|LOCK_EX);

    if (count($data) > 0)
        file_put_contents('2.txt', date("Y-m-d H:i:s")." : ".$action."(data) ==>> ".print_r($data, true)."  \r\n", FILE_APPEND|LOCK_EX);

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

/**
 * Unserialize value only if it was serialized.
 *
 * @since 2.0.0
 *
 * @param string $original Maybe unserialized original, if is needed.
 * @return mixed Unserialized data can be any type.
 */
function maybe_unserialize( $original ) {
	if ( is_serialized( $original ) ) // don't attempt to unserialize data that wasn't serialized going in
		return @unserialize( $original );
	return $original;
}

/**
 * Check value to find if it was serialized.
 *
 * If $data is not an string, then returned value will always be false.
 * Serialized data is always a string.
 *
 * @since 2.0.5
 *
 * @param string $data   Value to check to see if was serialized.
 * @param bool   $strict Optional. Whether to be strict about the end of the string. Default true.
 * @return bool False if not serialized and true if it was.
 */
function is_serialized( $data, $strict = true ) {
	// if it isn't a string, it isn't serialized.
	if ( ! is_string( $data ) ) {
		return false;
	}
	$data = trim( $data );
 	if ( 'N;' == $data ) {
		return true;
	}
	if ( strlen( $data ) < 4 ) {
		return false;
	}
	if ( ':' !== $data[1] ) {
		return false;
	}
	if ( $strict ) {
		$lastc = substr( $data, -1 );
		if ( ';' !== $lastc && '}' !== $lastc ) {
			return false;
		}
	} else {
		$semicolon = strpos( $data, ';' );
		$brace     = strpos( $data, '}' );
		// Either ; or } must exist.
		if ( false === $semicolon && false === $brace )
			return false;
		// But neither must be in the first X characters.
		if ( false !== $semicolon && $semicolon < 3 )
			return false;
		if ( false !== $brace && $brace < 4 )
			return false;
	}
	$token = $data[0];
	switch ( $token ) {
		case 's' :
			if ( $strict ) {
				if ( '"' !== substr( $data, -2, 1 ) ) {
					return false;
				}
			} elseif ( false === strpos( $data, '"' ) ) {
				return false;
			}
			// or else fall through
		case 'a' :
		case 'O' :
			return (bool) preg_match( "/^{$token}:[0-9]+:/s", $data );
		case 'b' :
		case 'i' :
		case 'd' :
			$end = $strict ? '$' : '';
			return (bool) preg_match( "/^{$token}:[0-9.E-]+;$end/", $data );
	}
	return false;
}

/**
 * Check whether serialized data is of string type.
 *
 * @since 2.0.5
 *
 * @param string $data Serialized data.
 * @return bool False if not a serialized string, true if it is.
 */
function is_serialized_string( $data ) {
	// if it isn't a string, it isn't a serialized string.
	if ( ! is_string( $data ) ) {
		return false;
	}
	$data = trim( $data );
	if ( strlen( $data ) < 4 ) {
		return false;
	} elseif ( ':' !== $data[1] ) {
		return false;
	} elseif ( ';' !== substr( $data, -1 ) ) {
		return false;
	} elseif ( $data[0] !== 's' ) {
		return false;
	} elseif ( '"' !== substr( $data, -2, 1 ) ) {
		return false;
	} else {
		return true;
	}
}

/**
 * Serialize data, if needed.
 *
 * @since 2.0.5
 *
 * @param string|array|object $data Data that might be serialized.
 * @return mixed A scalar data
 */
function maybe_serialize( $data ) {
	if ( is_array( $data ) || is_object( $data ) )
		return serialize( $data );

	// Double serialization is required for backward compatibility.
	// See https://core.trac.wordpress.org/ticket/12930
	// Also the world will end. See WP 3.6.1.
	if ( is_serialized( $data, false ) )
		return serialize( $data );

	return $data;
}