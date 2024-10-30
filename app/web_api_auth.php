<?php
class HippooControllerWithAuth extends WC_REST_Customers_Controller
{
    function register_routes()
    {
        #
        $args_hippoo_stock_list = array(
            'methods'             => 'GET',
            'callback'            => array($this, 'hippoo_stock_list'),
            'permission_callback' => array($this, 'get_items_permissions_check'),
            'args'                => array(
                'page' => array(
                    'required'          => false
                ),
            )
        );
        register_rest_route('wc-hippoo/v1', '/wc/stock(?:/(?P<id>\d+))?', $args_hippoo_stock_list);

        #
        $args_hippoo_media_upload = array(
            'methods'             => 'POST',
            'callback'            => array($this, 'hippoo_media_upload'),
            'permission_callback' => array($this, 'create_item_permissions_check')
        );
        register_rest_route('wc-hippoo/v1', '/wp/media/item', $args_hippoo_media_upload);

        #
        $args_hippoo_media_delete = array(
            'methods'             => 'DELETE',
            'callback'            => array($this, 'hippoo_media_delete'),
            'permission_callback' => array($this, 'delete_item_permissions_check'),
            'args'                => array(
                'ids' => array(
                    'required'          => true,
                    'sanitize_callback' => 'rest_sanitize_request_arg',
                    'validate_callback' => 'rest_validate_request_arg',
                    'type'              => 'array',
                    'description'       => 'Array of media item IDs to delete.',
                ),
            )
        );
        register_rest_route('wc-hippoo/v1', '/wp/media/item', $args_hippoo_media_delete);

        #
        $args_hippoo_system_info = array(
            'methods'             => 'GET',
            'callback'            => array($this, 'hippoo_system_info'),
            'permission_callback' => array($this, 'get_items_permissions_check')
        );
        register_rest_route('wc-hippoo/v1', '/wp/system/info', $args_hippoo_system_info);
        
        #
        register_rest_route( 'wc-hippoo/v1', '/setting', array(
            array(
                'methods'   => 'GET',
                'callback'  => array( $this, 'get_setting' ),
                'permission_callback' => array( $this, 'get_items_permissions_check' ),
            ),
        ) );
 
        #
        register_rest_route( 'wc-hippoo/v1', '/setting', array(
            array(
                'methods'   => 'PUT',
                'callback'  => array( $this, 'update_setting' ),
                'permission_callback' => array( $this, 'get_items_permissions_check' ),
            ),
        ) );
    }

    function hippoo_stock_list($data)
    {

        if (isset($data['page'])) {
            $page = esc_sql($data['page']);
        } elseif (isset($data['id'])) {
            $page = esc_sql($data['id']);
        } else {
            $page = "1";
        }

        $page = --$page * 25;
        global $wpdb;
        $query = "SELECT p.ID as post_id, p.post_title, pm.meta_value as product_quantity, o.meta_value as out_of_stock_date
                                        FROM $wpdb->posts AS p
                                        JOIN $wpdb->postmeta AS pm ON p.ID = pm.post_id
                                        JOIN $wpdb->postmeta AS o ON p.ID = o.post_id
                                        WHERE p.post_type = 'product'
                                        AND p.post_status = 'publish'
                                        AND o.meta_key = 'out_stock_time'
                                        AND pm.meta_key = '_stock'
                                        AND pm.meta_value <= 0
                                        ORDER BY out_of_stock_date DESC limit $page,25";
        $rows = $wpdb->get_results($wpdb->prepare($query));

        if (empty($rows)) {
            $response = array();
            return new WP_REST_Response($response, 200);
        }
        $response = array();
        foreach ($rows as $row) {
            $img = empty($row->post_parent) ? $row->post_id : $row->post_parent;
            $response[] = [
                'id'                => $row->post_id,
                'img'               => get_the_post_thumbnail_url($img, 'thumbnail'),
                'out_of_stock_date' => $row->out_of_stock_date,
                'title'             => $row->post_title,
                'product_quantity'  => $row->product_quantity,
            ];
        }
        return new WP_REST_Response($response, 200);
    }

    function hippoo_media_upload()
    {
        if (empty($_FILES['file'])) {
            return new WP_Error('invalid_file', 'Invalid file.', ['status' => 400]);
        }
        $file = $_FILES['file'];
        $upload = wp_upload_bits($file['name'], null, file_get_contents($file['tmp_name']));

        if (!$upload['error']) {
            $attachment = array(
                'post_mime_type' => $upload['type'],
                'post_title' => sanitize_file_name($upload['file']),
                'post_content' => '',
                'post_status' => 'inherit'
            );

            $attachment_id = wp_insert_attachment($attachment, $upload['file']);

            if (!is_wp_error($attachment_id)) {
                $attachment_data = wp_generate_attachment_metadata($attachment_id, $upload['file']);
                wp_update_attachment_metadata($attachment_id, $attachment_data);
                $media_url = wp_get_attachment_url($attachment_id);
                $response =  array(
                    'status' => 'success',
                    'media_url' => $media_url,
                    'attachment_id' => $attachment_id,
                    "attachment_data" => $attachment_data
                );
                return new WP_REST_Response($response, 200);
            }
        }

        return new WP_Error('upload_failed', 'Media upload failed.', ['status' => 500]);
    }

    function hippoo_media_delete($request)
    {
        $attachment_ids = $request->get_param('ids');
        $attachment_ids_deleted = array();
        if (!is_null($attachment_ids) && count($attachment_ids) > 0) {
            foreach ($attachment_ids as $attachment_id) {

                $attachment_path = get_attached_file($attachment_id);
                if (!$attachment_path) {
                    return new WP_Error('invalid_attachment', 'Attachment not found.', ['status' => 404]);
                }

                $deleted = wp_delete_attachment($attachment_id, true);

                if ($deleted === false) {
                    return new WP_Error('delete_error', 'Error deleting the attachment.', ['status' => 500]);
                }

                $attachment_ids_deleted[] = $attachment_id;
            }


            $response =  array(
                'status' => 'success',
                'message' => 'Attachment(s) deleted successfully.',
                'attachment_ids_deleted' => $attachment_ids_deleted
            );
            return new WP_REST_Response($response, 200);
        }

        $response =  array(
            'status' => 'problem',
            'message' => 'Nothing to delete'
        );
        return new WP_REST_Response($response, 404);
    }

    function hippoo_get_available_plugins_from_central()
    {
        $response = wp_remote_get('https://hippoo.app/wp-json/hippoo/v1/get_plugins');
        if (is_wp_error($response)) {
            return [];
        }
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        return $data;
    }

    function hippoo_system_info($request)
    {

        $available_plugins = $this->hippoo_get_available_plugins_from_central();
        $plugins = get_plugins();
        $plugins_info = array();

        # Filter hippoo family plugins
        $plugins = array_filter($plugins, function ($plugin) {
            $plugin_family_names = array('hippoo');
            foreach ($plugin_family_names as $plugin_family_name) {
                if (stripos($plugin['TextDomain'], $plugin_family_name) === 0) {
                    return true;
                }
            }
            return false;
        });

        // return new WP_REST_Response($plugins, 200);

        foreach ($plugins as $plugin_file => $plugin) {
            // echo $plugin['TextDomain'];
            $available_plugin_from_central = hippoo_get_product_by_slug($available_plugins, $plugin['TextDomain']);
            // print_r($available_plugin_from_central);
            // echo "11111";
            // print_r('<br>');
            // if (is_null($available_plugin_from_central)) {
            //     print_r("NotNull");
            // }
            // return;
            if (!is_null($available_plugin_from_central)) {
                
                $minimum_support_version = $available_plugin_from_central['attributes']['pa_minimum-support'];
                $latest_version = $available_plugin_from_central['attributes']['pa_latest-version'];
                $current_installed_version = $plugin['Version'];

                if (
                    version_compare($current_installed_version, $minimum_support_version, '>=')
                &&  version_compare($current_installed_version, $latest_version,          '<=')
                ) 
                {

                    # Get plugin installation status
                    $plugin_status = 'installed';
                    if (is_plugin_active($plugin_file)) {
                        $plugin_status = 'active';
                    }

                    $available_plugin_from_central['installation_status'] = $plugin_status;
                    $available_plugin_from_central['attributes']['current_installed_version'] = $plugin['Version'];
                    $plugins_info[] = $available_plugin_from_central;
                }
            }
        }

        return new WP_REST_Response($plugins_info, 200);
    }
    
    function get_setting($request) {
        $settings = get_option('hippoo_settings');
    
        if (empty($settings)) {
            $settings = [];
        }

        $default_settings = [
            'invoice_plugin_enabled' => false,
            'send_notification_wc-processing' => true
        ];

        if (function_exists('wc_get_order_statuses')) {
            $order_statuses = wc_get_order_statuses();
            foreach ($order_statuses as $status_key => $status_label) {
                $key = 'send_notification_' . $status_key;
                if (!array_key_exists($key, $default_settings)) {
                    $default_settings[$key] = false;
                }
            }
        }
    
        $settings = array_merge($default_settings, $settings);
    
        $settings = array_map(function($value) {
            return ($value === '1') ? true : (($value === '0') ? false : $value);
        }, $settings);

        update_option('hippoo_settings', $settings);
        return rest_ensure_response($settings);
    }

        
    public function update_setting( $request ) {
        $settings = get_option('hippoo_settings');
        if (empty($settings)) {
            $settings = [];
        }
        $new_settings = json_decode( $request->get_body(), true );
    
        $settings = array_merge($settings, $new_settings);
    
        // Convert string 'true' or 'false' to boolean true or false
        $settings = array_map(function($value) {
            return ($value === 'true') ? true : (($value === 'false') ? false : $value);
        }, $settings);
    
        update_option('hippoo_settings', $settings);
    
        return rest_ensure_response( $settings );
    }
}
