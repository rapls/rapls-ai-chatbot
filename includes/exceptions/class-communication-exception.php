<?php
/**
 * Communication Exception
 *
 * Thrown when wp_remote_post returns a WP_Error (timeout, DNS failure,
 * connection reset, etc.). Used by send_with_fallback() to distinguish
 * network failures (fallback-eligible) from generic code=0 exceptions.
 */

if (!defined('ABSPATH')) {
    exit;
}

class RAPLSAICH_Communication_Exception extends Exception {
    // Custom exception for network/transport failures
}
