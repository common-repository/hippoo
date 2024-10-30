<?php

///
///Invoice 
///

define( 'HIPPOO_INVOICE_PLUGIN_LANG_DIR', HIPPOO_INVOICE_PLUGIN_PATH . 'languages'. DIRECTORY_SEPARATOR );
define( 'HIPPOO_INVOICE_PLUGIN_TEMPLATE_PATH', HIPPOO_INVOICE_PLUGIN_PATH . 'templates' . DIRECTORY_SEPARATOR . 'simple' . DIRECTORY_SEPARATOR );

add_action( 'plugins_loaded', 'hippoo_load_textdomain' );
function hippoo_load_textdomain() {
    load_plugin_textdomain( 'hippoo-invoice', FALSE, HIPPOO_INVOICE_PLUGIN_LANG_DIR );
}
add_action( 'admin_enqueue_scripts', 'hippoo_enqueue_scripts' );
function hippoo_enqueue_scripts() {
    wp_enqueue_style(  'hippoo-styles', HIPPOO_INVOICE_PLUGIN_URL . 'assets/css/admin-style.css' );
    wp_enqueue_script( 'hippoo-scripts', HIPPOO_INVOICE_PLUGIN_URL . 'assets/js/admin-script.js', [ 'jquery', 'jquery-ui-core', 'jquery-ui-tooltip' ] );
}
add_action( 'woocommerce_init', 'hippoo_invoice_load' );

function hippoo_invoice_load() {
    
    require_once HIPPOO_INVOICE_PLUGIN_PATH . 'libs/barcode/vendor/autoload.php';

    require_once HIPPOO_INVOICE_PLUGIN_PATH . 'helper.php';
    require_once HIPPOO_INVOICE_PLUGIN_PATH . 'api.php';
    require_once HIPPOO_INVOICE_PLUGIN_PATH . 'settings.php';

    require_once HIPPOO_INVOICE_PLUGIN_PATH . 'woocommerce/order.php';
    require_once HIPPOO_INVOICE_PLUGIN_PATH . 'woocommerce/product.php';
    require_once HIPPOO_INVOICE_PLUGIN_PATH . 'woocommerce/my-account.php';
}

add_filter( 'query_vars', 'hippoo_query_vars' );
function hippoo_query_vars( $vars ) {
    $vars[] = 'post_id';
    $vars[] = 'download_type';

    return $vars;
}

add_filter( 'init', 'hippoo_handle_html_display' );
function hippoo_handle_html_display() {
    if ( isset( $_GET['download_type'] ) && isset( $_GET['post_id'] ) ) {
        $post_id = sanitize_text_field( $_GET['post_id'] );
        $download_type = sanitize_text_field( $_GET['download_type'] );

        if ( user_has_order_access( $post_id ) || current_user_can( 'administrator' ) ) {
            $html_doc = generate_html( $post_id, $download_type );

            echo  $html_doc;
        } else {
            echo "You do not have access to view this order.";
        }
        exit;
    }
}

?>