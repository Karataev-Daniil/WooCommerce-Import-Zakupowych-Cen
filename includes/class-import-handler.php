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

    // Main import method
    public function run() {
        foreach ( $this->data as $row ) {
            $sku = trim( $row['nr. sku'] ?? '' );
            $price_per_kg = floatval( str_replace(',', '.', $row['cena_zakupu_za_1kg'] ?? 0 ) );

            if ( ! $sku || $price_per_kg <= 0 ) {
                $this->log_error( $sku, 'Invalid SKU or price ≤ 0' );
                continue;
            }

            // Get parent product ID by SKU
            $parent_product_id = wc_get_product_id_by_sku( $sku );
            if ( ! $parent_product_id ) {
                $this->log_error( $sku, 'Parent product not found' );
                continue;
            }

            // Ensure parent product has variable attribute setup
            $this->set_product_variable_attribute_weights( $parent_product_id );

            // Create missing variations with SKUs and attributes
            $this->ensure_variations_with_skus( $sku, $parent_product_id );

            // Fetch all variations for this parent SKU
            $variations = $this->get_variations_by_sku_prefix( $sku );

            if ( empty( $variations ) ) {
                $this->log_error( $sku, 'No product variations found' );
                continue;
            }

            // Update variation prices based on weight
            foreach ( $variations as $variation ) {
                $variation_sku = $variation->get_sku();
                $suffix = strtoupper( str_replace( $sku . '-', '', $variation_sku ) );

                $weight_in_kg = \WIPC\parse_weight_from_sku_suffix( $suffix );

                if ( $weight_in_kg === false ) {
                    $this->log_error( $variation_sku, 'Cannot parse weight' );
                    continue;
                }

                $purchase_price = round( $price_per_kg * $weight_in_kg, 2 );

                update_post_meta( $variation->get_id(), '_regular_price', $purchase_price );
                update_post_meta( $variation->get_id(), '_price', $purchase_price );

                $this->results['updated']++;
            }
        }

        return $this->results;
    }

    // Setup variable attribute 'pa_weight' on parent product (to avoid "Any value" in variations)
    private function set_product_variable_attribute_weights( int $product_id ) {
        $product = wc_get_product( $product_id );
        if ( ! $product || $product->get_type() !== 'variable' ) {
            return;
        }

        $taxonomy = 'pa_weight';

        $attribute = new \WC_Product_Attribute();
        $attribute->set_id( wc_attribute_taxonomy_id_by_name( $taxonomy ) );
        $attribute->set_name( $taxonomy );
        $attribute->set_options( ['1kg', '500g', '250g'] ); // term slugs
        $attribute->set_position( 0 );
        $attribute->set_visible( true );
        $attribute->set_variation( true );

        $product->set_attributes( [ $attribute ] );
        $product->save();
    }

    // Create missing variations and assign SKU and attribute
    private function ensure_variations_with_skus( string $parent_sku, int $parent_product_id ) {
        $weights = ['1kg', '500g', '250g'];

        $parent = wc_get_product( $parent_product_id );
        if ( ! $parent || $parent->get_type() !== 'variable' ) {
            return;
        }

        $existing_variations = $parent->get_children();
        $existing_skus = [];
        foreach ( $existing_variations as $variation_id ) {
            $variation = wc_get_product( $variation_id );
            if ( $variation ) {
                $existing_skus[] = $variation->get_sku();
            }
        }

        foreach ( $weights as $weight_slug ) {
            $weight_sku_part = strtoupper( $weight_slug );
            $target_sku = $parent_sku . '-' . $weight_sku_part;

            if ( in_array( $target_sku, $existing_skus, true ) ) {
                continue; // skip if variation already exists
            }

            $variation = new \WC_Product_Variation();
            $variation->set_parent_id( $parent_product_id );
            $variation->set_sku( $target_sku );

            // Assign attribute value (term slug)
            $variation->set_attributes([
                'pa_weight' => $weight_slug,
            ]);

            $variation->save();
        }
    }

    // Get product variations by SKU prefix (using REGEXP meta query)
    private function get_variations_by_sku_prefix( string $sku_prefix ) {
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

    // Log error into result and error_log
    private function log_error( string $sku, string $message ) {
        $this->results['errors'][] = [
            'sku' => $sku,
            'message' => $message,
        ];

        \WIPC\log_import_error( "SKU {$sku}: {$message}" );
    }
}