<?php

namespace WIPC;

use WC_Product_Variation;

class Import_Handler {

    private $data;
    private $results = [
        'updated' => 0,
        'errors' => [],
    ];

    public function __construct( $parsed_data ) {
        $this->data = $parsed_data;
    }

    public function run() {
        foreach ( $this->data as $row ) {
            $sku = trim( $row['nr. sku'] ?? '' );
            $price_per_kg = floatval( str_replace(',', '.', $row['cena_zakupu_za_1kg'] ?? 0 ) );

            if ( ! $sku || $price_per_kg <= 0 ) {
                $this->log_error( $sku, 'Invalid SKU or price ≤ 0' );
                continue;
            }

            // Get product variations starting with {sku}-
            $variations = $this->get_variations_by_sku_prefix( $sku );

            if ( empty( $variations ) ) {
                $this->log_error( $sku, 'No product variations found' );
                continue;
            }

            foreach ( $variations as $variation ) {
                $variation_sku = $variation->get_sku();
                $suffix = strtoupper( str_replace( $sku . '-', '', $variation_sku ) );

                $weight_in_kg = parse_weight_from_sku_suffix( $suffix );

                if ( $weight_in_kg === false ) {
                    $this->log_error( $variation_sku, 'Cannot parse weight' );
                    continue;
                }
            
                $purchase_price = round( $price_per_kg * $weight_in_kg, 2 );
            
                // Update WooCommerce variation price
                $variation_id = $variation->get_id();
                update_post_meta( $variation_id, '_regular_price', $purchase_price );
                update_post_meta( $variation_id, '_price', $purchase_price );
            
                $this->results['updated']++;
            }
        }

        return $this->results;
    }

    // Fetch variations by SKU prefix using REGEXP meta query
    private function get_variations_by_sku_prefix( $sku_prefix ) {
        $args = [
            'post_type'   => 'product_variation',
            'post_status' => 'publish',
            'meta_query'  => [
                [
                    'key'     => '_sku',
                    'value'   => '^' . $sku_prefix . '-',
                    'compare' => 'REGEXP',
                ]
            ],
            'posts_per_page' => -1,
        ];

        $query = new \WP_Query( $args );
        $variations = [];

        foreach ( $query->posts as $post ) {
            $variation = new WC_Product_Variation( $post->ID );
            if ( $variation->get_sku() ) {
                $variations[] = $variation;
            }
        }

        return $variations;
    }

    // Log import errors and save to results
    private function log_error( $sku, $message ) {
        $this->results['errors'][] = [
            'sku' => $sku,
            'message' => $message,
        ];

        log_import_error( "SKU {$sku}: {$message}" );
    }
}