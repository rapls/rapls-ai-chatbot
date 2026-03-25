<?php
/**
 * Quota Exceeded Exception
 *
 * Thrown when API quota is exceeded or billing issue occurs.
 */

if (!defined('ABSPATH')) {
    exit;
}

class RAPLSAICH_Quota_Exceeded_Exception extends Exception {

    /**
     * Recommended wait time in seconds (from Retry-After header).
     * 0 means no hint was provided by the upstream API.
     *
     * @var int
     */
    private int $retry_after = 0;

    /**
     * Set Retry-After seconds.
     *
     * @param int $seconds Seconds to wait before retrying.
     * @return $this
     */
    public function set_retry_after(int $seconds): self {
        $this->retry_after = max(0, $seconds);
        return $this;
    }

    /**
     * Get Retry-After seconds.
     *
     * @return int 0 if not set.
     */
    public function get_retry_after(): int {
        return $this->retry_after;
    }
}
