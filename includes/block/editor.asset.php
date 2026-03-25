<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
return [
    'dependencies' => [
        'wp-blocks',
        'wp-element',
        'wp-block-editor',
        'wp-components',
        'wp-i18n',
    ],
    'version' => defined('RAPLSAICH_VERSION') ? RAPLSAICH_VERSION : '1.5.0',
];
