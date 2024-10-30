<?php
add_filter("woocommerce_rest_prepare_shop_order_object", "hippoo_enrich_product_order_object", 10, 3);
add_action('woocommerce_no_stock_notification', 'hippoo_woocommerce_no_stock_notification', 10, 1);

// Hook the function to an appropriate action, like 'init' or 'woocommerce_init'.
add_action('init', 'hippoo_add_order_status_filters');


function hippoo_woocommerce_no_stock_notification($product){
    update_post_meta($product->get_id(), 'out_stock_time', date('Y-m-d H:i:s'));
    hippoo_out_of_stock_send_notification_by_prodcut($product);
}


add_action('rest_api_init', function () {
    require_once __DIR__ . '/web_api_auth.php';
    $controller = new HippooControllerWithAuth();
    $controller->register_routes();
});

add_action( 'rest_api_init', function () {
    register_rest_route( 'hippoo/v1', 'wc/token/get', array(
        'methods'  => 'GET',
        'callback' => 'hippoo_get_token_from_wc',
        'permission_callback' => '__return_true'
    ));
    register_rest_route( 'hippoo/v1', 'wc/token/save_callback/(?P<token_id>\w+)', array(
        'methods'  => 'POST',
        'callback' => 'hippoo_save_token_callback',
        'permission_callback' => '__return_true'
    ));
    register_rest_route('hippoo/v1', 'wc/token/return/(?P<token_id>\w+)', array(
        'methods'  => 'GET',
        'callback' => 'hippoo_returned',
        'permission_callback' => '__return_true'
    ));
    register_rest_route( 'hippoo/v1', 'wc/token/show/(?P<token_id>\w+)', array(
        'methods'  => 'GET',
        'callback' => 'hippoo_show_token',
        'permission_callback' => '__return_true'
    ));
    register_rest_route( 'hippoo/v1', 'config', array(
        'methods'  => 'GET',
        'callback' => 'hippoo_config',
        'permission_callback' => '__return_true'
    ));
    
    # TODO Delete This Soon as Amid is updating the App
    register_rest_route( 'woohouse/v1', 'config', array(
        'methods'  => 'GET',
        'callback' => 'hippoo_config',
        'permission_callback' => '__return_true'
    ));
} );

function hippoo_config($data){
    
    if (function_exists('hippoo_config')) {
        $plugin_data = get_plugin_data( hippoo_main_file_path );
        $plugin_version = $plugin_data['Version'];
        $response = array(
            "hippoo" => "true",
            "hippoo_plugin_version" => $plugin_version,
            "lang" => get_bloginfo("language"),
        );
        return new WP_REST_Response($response);
    }
}


function hippoo_enrich_product_order_object($response, $order, $request)
{
    if (empty($response->data)) {
        return $response;
    }

    // Calculate total weight
    $total_weight = 0;
    foreach ($order->get_items() as $item) {
        $product = $item->get_product();
        if ($product && $product->get_weight()) {
            $total_weight += $product->get_weight() * $item->get_quantity();
        }
    }
    $total_weight = round($total_weight, 2);

    // Retrieve weight unit from WooCommerce settings
    $weight_unit = get_option('woocommerce_weight_unit');

    // Add total weight and weight unit to response data
    $response->data['total_weight'] = $total_weight;
    $response->data['weight_unit'] = $weight_unit;

    // Check if line_items is not null before iterating
    if (!empty($response->data['line_items'])) {
        foreach ($response->data['line_items'] as $item_id => $item_values) {
            $img = get_post_meta($item_values['product_id'], '_thumbnail_id', true);
            $response->data['line_items'][$item_id]['image'] = empty($img) ? '' : wp_get_attachment_image_url($img, 'thumbnail');
        }
    }

    return $response;
}



# Send notifications to mobile
function hippoo_send_notification_by_order($order_id)
{
    $order = wc_get_order($order_id);

    $home_url = home_url();
    $parsed_url = parse_url($home_url);
    $cs_hostname = $parsed_url['host'];
    
    $order_status = $order->get_status();
    $order_id = $order->get_id();
    $order_count = $order->get_item_count();
    $order_total_price = $order->get_total();
    $order_currency = $order->get_currency();

    $title = "Order {$order_id} {$order_status}!";
    $content  = $order_count . " Items";
    $content .= " | " . $order_total_price . $order_currency;
    $content .= " | " . "Status: " . $order_status;
    
    $args = array(
        'body' => array(
            'cs_hostname'=> $cs_hostname,
            'notif_data' => array(
                'title' => $title,
                'content' => $content
                )
            )
    );
    
    $response = wp_remote_post(hippoo_proxy_notifiction_url, $args);

    if (!is_wp_error($response))
        $body = wp_remote_retrieve_body($response);
}


# Send out of stock notifications to mobile
function hippoo_out_of_stock_send_notification_by_prodcut($product)
{

    $home_url = home_url();
    $parsed_url = parse_url($home_url);
    $cs_hostname = $parsed_url['host'];
    
    $product_name = $product->get_name();
    $product_image_url = get_the_post_thumbnail_url($product->get_id(), 'thumbnail');
    $url = "hippoo://app/outofstock/?product_id=" . $product->get_id();

    $title = "Product is out of stock!";
    $content = "Product " . $product_name;
    
    $args = array(
        'body' => array(
            'cs_hostname'=> $cs_hostname,
            'notif_data' => array(
                'title' => $title,
                'content' => $content,
                'image' => $product_image_url,
                'largeIcon' => $product_image_url,
                'url' => $url
                )
            )
    );
    
    $response = wp_remote_post(hippoo_proxy_notifiction_url, $args);

    if (!is_wp_error($response))
        $body = wp_remote_retrieve_body($response);
}

function hippoo_get_token_from_wc()
{
    $key          = md5(microtime().rand());
    $store_url    = get_option('siteurl');
    $store_url    = str_replace("http://", "https://", $store_url);
    $return_url   = $store_url . "/wp-json/hippoo/v1/wc/token/return/" . $key;
    $callback_url = $store_url . "/wp-json/hippoo/v1/wc/token/save_callback/" . $key;
    $endpoint     = '/wc-auth/v1/authorize';
    
    $params = [
        'app_name'     => __( 'Hippoo', 'hippoo' ),
        'scope'        => 'read_write',
        'user_id'      => $key,
        'return_url'   => $return_url,
        'callback_url' => $callback_url
    ];
    
    $query_string = http_build_query($params);
    $url          = $store_url . $endpoint . '?' . $query_string;
    
    wp_redirect($url);
    exit;
}

function hippoo_save_token_callback($data)
{
    $response = array();
    $msg = "";
    if (isset($data['token_id'])) {
        $token_id = $data['token_id'];
        $file     = hippoo_get_temp_dir() . 'hippoo_' . $token_id . '.json';
        $token    = file_get_contents('php://input');
        file_put_contents($file, $token);

        $msg = 'Token Saved';

    } else {
        $msg = 'No Token';
    }
    $response['Message'] = $msg;
    return new WP_REST_Response ($response, 200);
}

function hippoo_show_token($data)
{
    $response = array();
    $msg = "";
    # Remove all old files
    $tokens = glob(hippoo_get_temp_dir() . 'hippoo_*.json');
    foreach ($tokens as $t)
    {
        if (time() - filemtime($t) > 20000) {
            unlink($t);
        }
    }
    
    # Get the token
    if (isset($data['token_id'])){
        $token_id = $data['token_id'];
        $file     = hippoo_get_temp_dir() . 'hippoo_' . $token_id . '.json';

        # Check if the token is not too old
        if (file_exists($file)) {
            $token = file_get_contents($file);
            unlink($file);
            $token_json = json_decode($token);
            return new WP_REST_Response ($token_json, 200);
        } else {
            $msg = 'Unauthenticated, No Token Reauthenticate';

            $response['Message'] = $msg;
            return new WP_REST_Response ($response, 401);
        }
        
    } else {
        $msg = 'Unauthenticated, No Token Data';
        $response['Message'] = $msg;
        return new WP_REST_Response ($response, 401);
    }

    $response['Message'] = $msg;
    return new WP_REST_Response ($response, 401);
   
}

function hippoo_returned($data)
{
    $returned_html_template = "<!DOCTYPE html>
        <html>
        <head>
        <title>Auto Redirect</title>
        <script type=\"text/javascript\">
            window.onload = function() {
            window.location.href = \"LINK\";
            };
        </script>
        </head>
        <body>
        <h1>Auto Redirect</h1>
        <h3>MSG</h3>
        <p>This page will automatically redirect in a few seconds...</p>
        </body>
        </html>";


    if (isset($data['token_id'])) {
        $token_id   = $data['token_id'];

        $msg        = __( 'You can get the data from here', 'hippoo' );
        $token_link = "hippoo://app/login/?token=" . $token_id;

        $returned_html_template = str_replace("LINK", $token_link, $returned_html_template);
        $returned_html_template = str_replace("MSG", $msg, $returned_html_template);
    } else {
        $msg                    = __( 'Unauthenticated, No Token Data', 'hippoo' );
        $returned_html_template = str_replace("MSG", $msg, $returned_html_template);
    }

    header('Content-Type: text/html;charset=utf-8;');
    echo wp_kses( $returned_html_template, array(
        'script' => array(
            'type' => true,
            'src' => true,
        ),
        ) );

}

/*
* Set product images from API Functions
*/
function hippoo_pif_get_url_attachment($data){
    $fin       = wp_upload_bits($data['name'],null,base64_decode($data['content']));
    $file_name = basename( $fin['file'] );
    $file_type = wp_check_filetype( $file_name, null );

    $post_info = array(
        'guid'           => $fin['url'],
        'post_mime_type' => $file_type['type'],
        'post_title'     => sanitize_file_name($file_name),
        'post_content'   => '',
        'post_status'    => 'inherit',
    );
    $attach_id   = wp_insert_attachment( $post_info, $fin['file'] );
    $attach_data = wp_generate_attachment_metadata($attach_id,$fin['file']);
    wp_update_attachment_metadata($attach_id,$attach_data);
    return $attach_id;
}


function hippoo_pif_product_images_del($data){
    if($data['cnt']==0)
        delete_post_thumbnail($data['id']);
    else{
        $imgs = explode(",", get_post_meta($data['id'],'_product_image_gallery',true));
        
        $cnt  = $data['cnt'] -1;
        if(isset($imgs[$cnt])){
            unset($imgs[$cnt]);
            update_post_meta($data['id'],'_product_image_gallery',implode(',',$imgs));
            return new WP_REST_Response( __( 'Image deleted successfully.', 'hippoo' ), 200);
        }else
            return new WP_REST_Response( __( 'Image not found!', 'hippoo' ), 404);
    }
}

/*
* Set product images from API Routes
*/
add_action( 'rest_api_init', function () {

    register_rest_route( 'wc/v3', 'productsimg/(?P<id>\d+)/imgs', array(
        'methods'             => 'POST',
        'callback'            => 'hippoo_pif_product_images',
        'permission_callback' => 'hippoo_pif_api_permissions_check',
    ));

    register_rest_route( 'wc/v3', 'productsimg/(?P<id>\d+)/dlmg/(?P<cnt>\d+)', array(
        'methods'             => 'GET',
        'callback'            => 'hippoo_pif_product_images_del',
        'permission_callback' => 'hippoo_pif_api_permissions_check',
    ));

} );

function hippoo_pif_api_permissions_check(){
    return current_user_can( 'edit_others_posts' );
}


function hippoo_pif_product_images($data){

    $arr = $data->get_json_params();

    if(empty($arr['imgs']))
        return ['There is not any images!'];

    $gallery = [];
    foreach($arr['imgs'] as $i=>$img){
        $img_id = hippoo_pif_get_url_attachment($img);
        if( $i == 0 )
            set_post_thumbnail($data['id'], $img_id);
        else
            $gallery[] = $img_id;
    }
    if(!empty($gallery))
        update_post_meta($data['id'], '_product_image_gallery', implode(',',$gallery));

    return new WP_REST_Response( __( 'Image saved successfully.', 'hippoo' ), 200);
}


/**
 * Conditionally add filters for order status notifications.
 */
function hippoo_add_order_status_filters() {
    $settings = get_option('hippoo_settings');
    if (empty($settings)) {
        return;
    }
    foreach ($settings as $key => $value) {
        if (strpos($key, 'send_notification_wc-') === 0 && $value === true) {
            $status_key = str_replace('send_notification_wc-', '', $key);
            add_filter("woocommerce_order_status_{$status_key}", "hippoo_send_notification_by_order");
        }
    }
}


