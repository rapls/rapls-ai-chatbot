<?php
/**
 * Quota Exceeded Exception
 *
 * Thrown when API quota is exceeded or billing issue occurs.
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPAIC_Quota_Exceeded_Exception extends Exception {
    // Custom exception for API quota/billing errors
}
