<?php
namespace WP_ArrivalSpace;

/**
 * WP Arrival.Space Admin Interface
 * Version: 0.0.1
 * Author: Ben ERwin
 */

if (!defined('ABSPATH')) {
    exit;
}

class Admin {
    private static $instance = null;
    private $config = [];
    private $post_types = ['post', 'page', 'event', 'profile', 'resource'];

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('wp_ajax_arrival_space_search_posts', [$this, 'ajax_search_posts']);
    }

    public function add_admin_menu() {
        add_submenu_page(
            'options-general.php',
            'WP Arrival.Space',
            'WP Arrival.Space',
            'manage_options',
            'wp-arrival-space',
            [$this, 'render_admin_page']
        );
    }

    public function enqueue_admin_assets($hook) {
        if ('settings_page_wp-arrival-space' !== $hook) {
            return;
        }

        wp_enqueue_media();
        wp_enqueue_style(
            'arrivalspace-admin',
            get_template_directory_uri() . '/admin/css/arrivalspace-admin.css',
            [],
            '0.0.1'
        );

        wp_enqueue_script(
            'arrivalspace-admin',
            get_template_directory_uri() . '/admin/js/arrivalspace-admin.js',
            ['jquery'],
            '0.0.1',
            true
        );

        wp_localize_script('arrivalspace-admin', 'wpArrivalSpace', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wp-arrival-space-nonce'),
            'postTypes' => $this->post_types
        ]);
    }

    public function register_settings() {
        register_setting('wp_arrival_space_settings', 'wp_arrival_space_room_config');
        register_setting('wp_arrival_space_settings', 'wp_arrival_space_gates_config');
    }

    public function ajax_search_posts() {
        check_ajax_referer('wp-arrival-space-nonce', 'nonce');

        $search = sanitize_text_field($_POST['search']);
        $args = [
            'post_type' => $this->post_types,
            'post_status' => 'publish',
            'posts_per_page' => 10,
            's' => $search
        ];

        $query = new \WP_Query($args);
        $results = [];

        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $results[] = [
                    'id' => get_the_ID(),
                    'title' => get_the_title(),
                    'type' => get_post_type(),
                    'url' => get_permalink()
                ];
            }
        }
        wp_reset_postdata();

        wp_send_json_success($results);
    }

    private function render_room_config_section($config) {
        ?>
        <div class="arrival-space-section">
            <h2>Room Configuration</h2>
            
            <div class="config-editor">
                <div class="config-form">
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="roomTitle">Room Title</label>
                            </th>
                            <td>
                                <input type="text" 
                                       id="roomTitle" 
                                       name="wp_arrival_space_room_config[roomTitle]" 
                                       value="<?php echo esc_attr($config['roomTitle'] ?? ''); ?>" 
                                       class="regular-text">
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="roomDescription">Room Description</label>
                            </th>
                            <td>
                                <textarea id="roomDescription" 
                                          name="wp_arrival_space_room_config[roomDescription]" 
                                          class="large-text" 
                                          rows="3"><?php echo esc_textarea($config['roomDescription'] ?? ''); ?></textarea>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Room Settings</th>
                            <td>
                                <label>
                                    <input type="checkbox" 
                                           name="wp_arrival_space_room_config[hideArchitecture]" 
                                           value="1" 
                                           <?php checked($config['hideArchitecture'] ?? false); ?>>
                                    Hide Architecture
                                </label><br>
                                <label>
                                    <input type="checkbox" 
                                           name="wp_arrival_space_room_config[noAssetShadows]" 
                                           value="1" 
                                           <?php checked($config['noAssetShadows'] ?? false); ?>>
                                    No Asset Shadows
                                </label><br>
                                <label>
                                    <input type="checkbox" 
                                           name="wp_arrival_space_room_config[hideRoomTitle]" 
                                           value="1" 
                                           <?php checked($config['hideRoomTitle'] ?? false); ?>>
                                    Hide Room Title
                                </label><br>
                                <label>
                                    <input type="checkbox" 
                                           name="wp_arrival_space_room_config[noCollision]" 
                                           value="1" 
                                           <?php checked($config['noCollision'] ?? false); ?>>
                                    No Collision
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="logoURL">Logo URL</label>
                            </th>
                            <td>
                                <div class="media-upload-container">
                                    <input type="text" 
                                           id="logoURL" 
                                           name="wp_arrival_space_room_config[logoURL]" 
                                           value="<?php echo esc_url($config['logoURL'] ?? ''); ?>" 
                                           class="regular-text">
                                    <button type="button" class="button" id="upload_logo">Select Image</button>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Colors</th>
                            <td>
                                <div class="color-picker-group">
                                    <label>
                                        Wall Color:
                                        <input type="color" 
                                               name="wp_arrival_space_room_config[wallColor]" 
                                               value="<?php echo esc_attr($config['wallColor'] ?? '#000000'); ?>">
                                    </label><br>
                                    <label>
                                        Floor Color:
                                        <input type="color" 
                                               name="wp_arrival_space_room_config[floorColor]" 
                                               value="<?php echo esc_attr($config['floorColor'] ?? '#000000'); ?>">
                                    </label><br>
                                    <label>
                                        Glass Color:
                                        <input type="color" 
                                               name="wp_arrival_space_room_config[glassColor]" 
                                               value="<?php echo esc_attr($config['glassColor'] ?? '#930612'); ?>">
                                    </label><br>
                                    <label>
                                        Ceiling Color:
                                        <input type="color" 
                                               name="wp_arrival_space_room_config[ceilingColor]" 
                                               value="<?php echo esc_attr($config['ceilingColor'] ?? '#ea0b0b'); ?>">
                                    </label><br>
                                    <label>
                                        Gate Title Color:
                                        <input type="color" 
                                               name="wp_arrival_space_room_config[gateTitleColor]" 
                                               value="<?php echo esc_attr($config['gateTitleColor'] ?? '#ffffff'); ?>">
                                    </label>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Textures</th>
                            <td>
                                <div class="media-upload-container">
                                    <label>Wall Texture:</label>
                                    <input type="text" 
                                           name="wp_arrival_space_room_config[wallTexture]" 
                                           value="<?php echo esc_url($config['wallTexture'] ?? ''); ?>" 
                                           class="regular-text">
                                    <button type="button" class="button upload-texture" data-target="wallTexture">Select Image</button>
                                </div>
                                <div class="media-upload-container">
                                    <label>Floor Texture:</label>
                                    <input type="text" 
                                           name="wp_arrival_space_room_config[floorTexture]" 
                                           value="<?php echo esc_url($config['floorTexture'] ?? ''); ?>" 
                                           class="regular-text">
                                    <button type="button" class="button upload-texture" data-target="floorTexture">Select Image</button>
                                </div>
                                <div class="media-upload-container">
                                    <label>Skybox Image:</label>
                                    <input type="text" 
                                           name="wp_arrival_space_room_config[skyboxImage]" 
                                           value="<?php echo esc_url($config['skyboxImage'] ?? ''); ?>" 
                                           class="regular-text">
                                    <button type="button" class="button upload-texture" data-target="skyboxImage">Select Image</button>
                                </div>
                            </td>
                        </tr>
                    </table>
                </div>
                <div class="config-json">
                    <h3>JSON Configuration</h3>
                    <textarea id="room_json" 
                              name="wp_arrival_space_room_config[json]" 
                              class="large-text code" 
                              rows="20"><?php echo esc_textarea($config['json'] ?? ''); ?></textarea>
                </div>
            </div>
        </div>
        <?php
    }

    private function render_gates_section($config) {
        ?>
        <div class="arrival-space-section">
            <h2>Gate Configuration</h2>
            
            <div class="gate-search">
                <input type="text" 
                       id="gate_search" 
                       placeholder="Search for posts..." 
                       class="regular-text">
                <div id="search_results" class="search-results"></div>
            </div>

            <div class="gates-list">
                <?php
                $gates = $config['gates'] ?? [];
                foreach ($gates as $index => $gate) {
                    $this->render_gate_item($index, $gate);
                }
                ?>
            </div>

            <div class="config-editor">
                <div class="config-form">
                    <h3>Selected Gate Configuration</h3>
                    <div id="gate_config_form">
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="gate_title">Title</label>
                                </th>
                                <td>
                                    <input type="text" 
                                           id="gate_title" 
                                           name="wp_arrival_space_gates_config[title]" 
                                           class="regular-text">
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="gate_description">Description</label>
                                </th>
                                <td>
                                    <textarea id="gate_description" 
                                              name="wp_arrival_space_gates_config[description]" 
                                              class="large-text" 
                                              rows="3"></textarea>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
                <div class="config-json">
                    <h3>JSON Configuration</h3>
                    <textarea id="gates_json" 
                              name="wp_arrival_space_gates_config[json]" 
                              class="large-text code" 
                              rows="20"><?php echo esc_textarea($config['json'] ?? ''); ?></textarea>
                </div>
            </div>
        </div>
        <?php
    }

    private function render_gate_item($index, $gate) {
        ?>
        <div class="gate-item" data-index="<?php echo esc_attr($index); ?>">
            <div class="gate-header">
                <div class="gate-columns">
                    <div class="gate-column gate-number"><?php echo esc_html($index + 1); ?></div>
                    <div class="gate-column gate-post-title"><?php echo esc_html($gate['post_title'] ?? 'No post title'); ?></div>
                    <div class="gate-column gate-menu-title"><?php echo esc_html($gate['title'] ?? 'No menu title'); ?></div>
                    <div class="gate-column gate-actions">
                        <button type="button" class="button edit-gate">Edit</button>
                        <button type="button" class="button remove-gate">Remove</button>
                    </div>
                </div>
            </div>
            <div class="gate-content">
                <p class="gate-description"><?php echo esc_html($gate['description'] ?? ''); ?></p>
                <p class="gate-post">
                    Type: <?php echo esc_html($gate['post_type'] ?? ''); ?>
                    <?php if (!empty($gate['url'])): ?>
                        <br>URL: <a href="<?php echo esc_url($gate['url']); ?>" target="_blank"><?php echo esc_url($gate['url']); ?></a>
                    <?php endif; ?>
                </p>
            </div>
        </div>
        <?php
    }

    public function render_admin_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        $room_config = get_option('wp_arrival_space_room_config', []);
        $gates_config = get_option('wp_arrival_space_gates_config', []);
        ?>
        <div class="wrap wp-arrival-space-admin">
            <h1>WP Arrival.Space Configuration</h1>
            
            <div class="wp-arrival-space-container">
                <div class="wp-arrival-space-sidebar">
                    <div class="wp-arrival-space-actions">
                        <button type="button" class="button button-primary" id="generate-config">
                            Generate Config
                        </button>
                        <button type="button" class="button" id="copy-config">
                            Copy to Clipboard
                        </button>
                    </div>
                    
                    <div class="wp-arrival-space-preview">
                        <h3>Config Preview</h3>
                        <pre id="config-preview"><?php 
                            echo esc_html(json_encode([
                                'room' => $room_config,
                                'gates' => $gates_config
                            ], JSON_PRETTY_PRINT)); 
                        ?></pre>
                    </div>
                </div>

                <div class="wp-arrival-space-main">
                    <form method="post" action="options.php">
                        <?php 
                        settings_fields('wp_arrival_space_settings');
                        $this->render_room_config_section($room_config);
                        $this->render_gates_section($gates_config);
                        submit_button('Save Configuration');
                        ?>
                    </form>
                </div>
            </div>
        </div>
        <?php
    }
}

// Initialize the admin interface
function init_admin() {
    Admin::get_instance();
}
add_action('init', __NAMESPACE__ . '\\init_admin'); 