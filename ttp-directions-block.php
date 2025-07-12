<?php
/**
 * Plugin Name: TTP Directions Block
 * Description: A custom block for displaying directions with address autocomplete and GPS coordinates
 * Version: 1.0.0
 * Author: Your Name
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class TTPDirectionsBlock {
    
    public function __construct() {
        add_action('init', array($this, 'register_block'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'settings_init'));
        add_action('wp_ajax_test_google_api', array($this, 'test_google_api'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_scripts'));
    }
    
    public function register_block() {
        wp_register_script(
            'ttp-directions-block-editor',
            plugin_dir_url(__FILE__) . 'build/index.js',
            array('wp-blocks', 'wp-i18n', 'wp-element', 'wp-editor', 'wp-components'),
            filemtime(plugin_dir_path(__FILE__) . 'build/index.js')
        );
        
        wp_register_style(
            'ttp-directions-block-editor',
            plugin_dir_url(__FILE__) . 'build/editor.css',
            array('wp-edit-blocks'),
            filemtime(plugin_dir_path(__FILE__) . 'build/editor.css')
        );
        
        wp_register_style(
            'ttp-directions-block-style',
            plugin_dir_url(__FILE__) . 'build/style.css',
            array(),
            filemtime(plugin_dir_path(__FILE__) . 'build/style.css')
        );
        
        register_block_type('ttp/directions', array(
            'editor_script' => 'ttp-directions-block-editor',
            'editor_style' => 'ttp-directions-block-editor',
            'style' => 'ttp-directions-block-style',
            'render_callback' => array($this, 'render_block'),
            'attributes' => array(
                'address' => array(
                    'type' => 'string',
                    'default' => ''
                ),
                'gpsCoordinates' => array(
                    'type' => 'string',
                    'default' => ''
                ),
                'latitude' => array(
                    'type' => 'number',
                    'default' => 0
                ),
                'longitude' => array(
                    'type' => 'number',
                    'default' => 0
                )
            )
        ));
    }
    
    public function render_block($attributes) {
        $address = isset($attributes['address']) ? $attributes['address'] : '';
        $gps = isset($attributes['gpsCoordinates']) ? $attributes['gpsCoordinates'] : '';
        
        ob_start();
        ?>
        <div class="ttp-directions-block">
            <div class="ttp-directions-content">
                <div class="ttp-directions-row">
                    <div class="ttp-address-field">
                        <label>Address:</label>
                        <div class="address-display"><?php echo esc_html($address); ?></div>
                    </div>
                    <div class="ttp-gps-field">
                        <label>GPS:</label>
                        <div class="gps-display"><?php echo esc_html($gps); ?></div>
                    </div>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    public function enqueue_frontend_scripts() {
        $api_key = get_option('ttp_google_api_key', '');
        if (!empty($api_key)) {
            wp_enqueue_script(
                'google-maps-api',
                'https://maps.googleapis.com/maps/api/js?key=' . $api_key . '&libraries=places',
                array(),
                null,
                true
            );
        }
    }
    
    public function add_admin_menu() {
        add_options_page(
            'TTP Directions Settings',
            'TTP Directions',
            'manage_options',
            'ttp-directions',
            array($this, 'options_page')
        );
    }
    
    public function settings_init() {
        register_setting('ttp_directions', 'ttp_google_api_key');
        
        add_settings_section(
            'ttp_directions_section',
            'Google Maps API Configuration',
            array($this, 'settings_section_callback'),
            'ttp_directions'
        );
        
        add_settings_field(
            'ttp_google_api_key',
            'Google API Key',
            array($this, 'api_key_render'),
            'ttp_directions',
            'ttp_directions_section'
        );
    }
    
    public function api_key_render() {
        $api_key = get_option('ttp_google_api_key', '');
        ?>
        <input type="text" name="ttp_google_api_key" value="<?php echo esc_attr($api_key); ?>" style="width: 400px;" />
        <button type="button" id="test-api-key" class="button">Test API Key</button>
        <div id="api-test-result" style="margin-top: 10px;"></div>
        <script>
        jQuery(document).ready(function($) {
            $('#test-api-key').click(function() {
                var apiKey = $('input[name="ttp_google_api_key"]').val();
                $('#api-test-result').html('Testing...');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'test_google_api',
                        api_key: apiKey,
                        nonce: '<?php echo wp_create_nonce('test_api_nonce'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            $('#api-test-result').html('<span style="color: green;">✓ API Key is working!</span>');
                        } else {
                            $('#api-test-result').html('<span style="color: red;">✗ API Key test failed: ' + response.data + '</span>');
                        }
                    }
                });
            });
        });
        </script>
        <?php
    }
    
    public function settings_section_callback() {
        echo 'Enter your Google Maps API key below. Make sure it has the Places API and Geocoding API enabled.';
    }
    
    public function test_google_api() {
        check_ajax_referer('test_api_nonce', 'nonce');
        
        $api_key = sanitize_text_field($_POST['api_key']);
        
        if (empty($api_key)) {
            wp_send_json_error('API key is required');
        }
        
        $test_url = 'https://maps.googleapis.com/maps/api/geocode/json?address=1600+Amphitheatre+Parkway,+Mountain+View,+CA&key=' . $api_key;
        
        $response = wp_remote_get($test_url);
        
        if (is_wp_error($response)) {
            wp_send_json_error('Failed to connect to Google API');
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if ($data['status'] === 'OK') {
            wp_send_json_success('API key is working correctly');
        } else {
            wp_send_json_error('API returned status: ' . $data['status']);
        }
    }
    
    public function options_page() {
        ?>
        <form action='options.php' method='post'>
            <h2>TTP Directions Settings</h2>
            <?php
            settings_fields('ttp_directions');
            do_settings_sections('ttp_directions');
            submit_button();
            ?>
        </form>
        <?php
    }
}

new TTPDirectionsBlock();
