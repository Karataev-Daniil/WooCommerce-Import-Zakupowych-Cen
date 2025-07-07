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
            $parser = new File_Parser($destination);

            $ext = strtolower(pathinfo($destination, PATHINFO_EXTENSION));

            if ($ext === 'csv') {
                $preview = [];
                $generator = $parser->parse_csv_generator();
                $count = 0;
                foreach ($generator as $row) {
                    $preview[] = $row;
                    $count++;
                    if ($count >= 5) break;
                }
            
                if (empty($preview) || !isset($preview[0]['nr. sku'], $preview[0]['cena_zakupu_za_1kg'])) {
                    echo '<div class="notice notice-error"><p>The file does not contain required columns: <code>nr. sku</code> and <code>cena_zakupu_za_1kg</code>.</p></div>';
                    return;
                }
            
                echo '<h2>Data Preview:</h2>';
                echo '<table class="widefat striped"><thead><tr><th>SKU</th><th>Price per 1kg</th></tr></thead><tbody>';
                foreach ($preview as $row) {
                    echo '<tr><td>' . esc_html($row['nr. sku']) . '</td><td>' . esc_html($row['cena_zakupu_za_1kg']) . '</td></tr>';
                }
                echo '</tbody></table>';
            
                $batch_size = 200;
                $offset = 0;
                $total_updated = 0;
                $all_errors = [];
            
                $generator = $parser->parse_csv_generator();
            
                $batch = [];
                foreach ($generator as $row) {
                    $batch[] = $row;
                    if (count($batch) >= $batch_size) {
                        $importer = new Import_Handler($batch);
                        $result = $importer->run();
                    
                        $total_updated += $result['updated'];
                        if (!empty($result['errors'])) {
                            $all_errors = array_merge($all_errors, $result['errors']);
                        }
                        $batch = [];
                    }
                }
            
                if (count($batch) > 0) {
                    $importer = new Import_Handler($batch);
                    $result = $importer->run();
                    $total_updated += $result['updated'];
                    if (!empty($result['errors'])) {
                        $all_errors = array_merge($all_errors, $result['errors']);
                    }
                }
            
                echo '<h2>Import Result:</h2>';
                echo '<p><strong>Variations updated:</strong> ' . intval($total_updated) . '</p>';
            
                if (!empty($all_errors)) {
                    echo '<div class="notice notice-warning"><p><strong>Errors occurred:</strong></p>';
                    echo '<ul class="wipc-error-list">';
                
                    $max_visible = 5;
                    foreach ($all_errors as $index => $error) {
                        $hidden_class = $index >= $max_visible ? ' class="wipc-hidden-error"' : '';
                        echo "<li{$hidden_class}><strong>" . esc_html($error['sku']) . "</strong>: " . esc_html($error['message']) . "</li>";
                    }
                
                    echo '</ul>';
                
                    if (count($all_errors) > $max_visible) {
                        echo '<button id="wipc-show-more-errors" class="button-link">Show more errors</button>';
                    }
                
                    echo '</div>';
                }
            
                $upload_dir = wp_upload_dir();
                $log_path = $upload_dir['basedir'] . '/tmp-imports/import-log-' . date('Ymd-His') . '.txt';
                $log_data = "Updated: " . $total_updated . "\n";
                foreach ($all_errors as $err) {
                    $log_data .= "SKU: {$err['sku']} — {$err['message']}\n";
                }
                file_put_contents($log_path, $log_data);
            
            } else {
                $data = $parser->parse();
            
                if (empty($data) || !isset($data[0]['nr. sku'], $data[0]['cena_zakupu_za_1kg'])) {
                    echo '<div class="notice notice-error"><p>The file does not contain required columns: <code>nr. sku</code> and <code>cena_zakupu_za_1kg</code>.</p></div>';
                    return;
                }
            
                $preview = $parser->get_preview();
                echo '<h2>Data Preview:</h2>';
                echo '<table class="widefat striped"><thead><tr><th>SKU</th><th>Price per 1kg</th></tr></thead><tbody>';
                foreach ($preview as $row) {
                    echo '<tr><td>' . esc_html($row['nr. sku']) . '</td><td>' . esc_html($row['cena_zakupu_za_1kg']) . '</td></tr>';
                }
                echo '</tbody></table>';
            
                $importer = new Import_Handler($data);
                $result = $importer->run();
            
                echo '<h2>Import Result:</h2>';
                echo '<p><strong>Variations updated:</strong> ' . intval($result['updated']) . '</p>';
            
                if (!empty($result['errors'])) {
                    echo '<div class="notice notice-warning"><p><strong>Errors occurred:</strong></p>';
                    echo '<ul class="wipc-error-list">';
                
                    $max_visible = 5;
                    foreach ($result['errors'] as $index => $error) {
                        $hidden_class = $index >= $max_visible ? ' class="wipc-hidden-error"' : '';
                        echo "<li{$hidden_class}><strong>" . esc_html($error['sku']) . "</strong>: " . esc_html($error['message']) . "</li>";
                    }
                
                    echo '</ul>';
                
                    if (count($result['errors']) > $max_visible) {
                        echo '<button id="wipc-show-more-errors" class="button-link">Show more errors</button>';
                    }
                
                    echo '</div>';
                }
            
                $upload_dir = wp_upload_dir();
                $log_path = $upload_dir['basedir'] . '/tmp-imports/import-log-' . date('Ymd-His') . '.txt';
                $log_data = "Updated: " . $result['updated'] . "\n";
                foreach ($result['errors'] as $err) {
                    $log_data .= "SKU: {$err['sku']} — {$err['message']}\n";
                }
                file_put_contents($log_path, $log_data);
            }
        } catch (\Exception $e) {
            echo '<div class="notice notice-error"><p>Processing error: ' . esc_html($e->getMessage()) . '</p></div>';
        }
    }
}

// Enqueue admin styles and scripts
add_action('admin_enqueue_scripts', function () {
    wp_enqueue_style('wipc-admin-style', WIPC_PLUGIN_URL . 'admin/assets/style.css', [], WIPC_PLUGIN_VERSION);
    wp_enqueue_script('wipc-admin-script', WIPC_PLUGIN_URL . 'admin/assets/script.js', [], WIPC_PLUGIN_VERSION, true);
});