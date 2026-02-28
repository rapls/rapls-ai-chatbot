<?php
/**
 * Plugin version — SINGLE SOURCE OF TRUTH.
 *
 * This file is the only place where WPAIC_VERSION is defined.
 * CI tag gate, verify-release.sh, and readme.txt all reference this file.
 * Pro reads the constant at runtime via Free — never defines its own.
 *
 * @package Rapls_AI_Chatbot
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!defined('WPAIC_VERSION')) {
    define('WPAIC_VERSION', '1.5.0');
}
