<?php

namespace WIPC;

// Parse weight from SKU suffix like "500G", "1KG"
function parse_weight_from_sku_suffix( $suffix ) {
    $suffix = strtoupper( $suffix );

    if ( preg_match( '/^([0-9.]+)(KG|G)$/', $suffix, $matches ) ) {
        $value = (float) $matches[1];
        $unit  = $matches[2];

        return convert_to_kg( $value, $unit );
    }

    return false; // invalid format
}

// Convert to kilograms
function convert_to_kg( $value, $unit ) {
    if ( $unit === 'KG' ) {
        return $value;
    }

    if ( $unit === 'G' ) {
        return $value / 1000;
    }

    return false;
}

// Log errors
function log_import_error( $message ) {
    error_log( '[Import Error] ' . $message );
}