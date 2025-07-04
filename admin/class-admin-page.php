<?php

namespace WIPC;

class Admin_Page {

    public function __construct() {
        add_action( 'admin_menu', [ $this, 'register_import_page' ] );
    }

    // Add submenu under WooCommerce
    public function register_import_page() {
        add_submenu_page(
            'woocommerce',
            'Import Purchase Prices',
            'Import Purchase Prices',
            'manage_woocommerce',
            'import-purchase-prices',
            [ $this, 'render_page' ]
        );
    }

    // Display admin page with upload form and import results
    public function render_page() {
        ?>
        <div class="wrap">
            <h1>Import Purchase Prices</h1>

            <?php
            if ( isset( $_FILES['purchase_file'] ) ) {
                $this->handle_upload();
            }
            ?>

            <form method="post" enctype="multipart/form-data">
                <input type="file" name="purchase_file" accept=".csv,.xlsx,.xls" required>
                <?php submit_button( 'Upload and Calculate' ); ?>
            </form>

            <div id="wipc-progress-bar">
                <div id="wipc-progress-bar-fill"></div>
            </div>
        </div>
        <?php
    }

    // Handle file upload, parsing and import
    private function handle_upload() {
        $file = $_FILES['purchase_file'];

        // 1. Validate file extension
        $ext = strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) );
        if ( ! in_array( $ext, ['csv', 'xlsx', 'xls'] ) ) {
            echo '<div class="notice notice-error"><p>Unsupported file format. Allowed formats: CSV, XLS, XLSX.</p></div>';
            return;
        }

        if ( $file['error'] !== UPLOAD_ERR_OK ) {
            echo '<div class="notice notice-error"><p>File upload error.</p></div>';
            return;
        }

        $tmp_name = $file['tmp_name'];
        $upload_dir = wp_upload_dir();
        $destination = $upload_dir['basedir'] . '/tmp-imports/' . basename( $file['name'] );

        if ( ! file_exists( dirname( $destination ) ) ) {
            wp_mkdir_p( dirname( $destination ) );
        }

        if ( ! move_uploaded_file( $tmp_name, $destination ) ) {
            echo '<div class="notice notice-error"><p>Failed to save the uploaded file.</p></div>';
            return;
        }

        try {
            // 2. Parse file
            $parser = new File_Parser( $destination );
            $data = $parser->parse();
        
            // 3. Check required columns
            if ( empty( $data ) || ! isset( $data[0]['nr. sku'], $data[0]['cena_zakupu_za_1kg'] ) ) {
                echo '<div class="notice notice-error"><p>The file does not contain required columns: <code>nr. sku</code> and <code>cena_zakupu_za_1kg</code>.</p></div>';
                return;
            }
        
            // 4. Preview data
            $preview = $parser->get_preview();
            echo '<h2>Data Preview:</h2>';
            echo '<table class="widefat striped"><thead><tr><th>SKU</th><th>Price per 1kg</th></tr></thead><tbody>';
            foreach ( $preview as $row ) {
                echo '<tr><td>' . esc_html( $row['nr. sku'] ) . '</td><td>' . esc_html( $row['cena_zakupu_za_1kg'] ) . '</td></tr>';
            }
            echo '</tbody></table>';
        
            // 5. Run import
            $importer = new Import_Handler( $data );
            $result = $importer->run();
        
            // 6. Display result
            echo '<h2>Import Result:</h2>';
            echo '<p><strong>Variations updated:</strong> ' . intval( $result['updated'] ) . '</p>';
        
            if ( ! empty( $result['errors'] ) ) {
                echo '<div class="notice notice-warning"><p>Errors occurred:</p><ul>';
                foreach ( $result['errors'] as $error ) {
                    echo '<li><strong>' . esc_html( $error['sku'] ) . '</strong>: ' . esc_html( $error['message'] ) . '</li>';
                }
                echo '</ul></div>';
            } else {
                echo '<div class="notice notice-success"><p>Import completed successfully!</p></div>';
            }
        
            // 7. Save admin log
            $log_path = $upload_dir['basedir'] . '/tmp-imports/import-log-' . date('Ymd-His') . '.txt';
            $log_data = "Updated: " . $result['updated'] . "\n";
            foreach ( $result['errors'] as $err ) {
                $log_data .= "SKU: {$err['sku']} — {$err['message']}\n";
            }
            file_put_contents( $log_path, $log_data );

        } catch ( \Exception $e ) {
            echo '<div class="notice notice-error"><p>Processing error: ' . esc_html( $e->getMessage() ) . '</p></div>';
        }
    }
}

// Enqueue admin styles and scripts
add_action('admin_enqueue_scripts', function () {
    wp_enqueue_style('wipc-admin-style', WIPC_PLUGIN_URL . 'admin/assets/style.css', [], WIPC_PLUGIN_VERSION);
    wp_enqueue_script('wipc-admin-script', WIPC_PLUGIN_URL . 'admin/assets/script.js', [], WIPC_PLUGIN_VERSION, true);
});