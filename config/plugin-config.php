<?php
/**
 * Plugin Configuration
 * 
 * This file contains the main configuration settings for the plugin.
 * All plugin-specific settings should be defined here.
 */

return [
    // Plugin Information
    'plugin' => [
        'name'        => 'Your Plugin Name',
        'version'     => '1.0.0',
        'description' => 'A modern WordPress plugin for [your purpose]',
        'author'      => 'Your Name',
        'author_uri'  => 'https://yourwebsite.com',
        'plugin_uri'  => 'https://yourwebsite.com/plugin',
        'text_domain' => 'your-plugin-textdomain',
        'min_php'     => '7.4',
        'min_wp'      => '5.6',
    ],

    // Database Tables
    'tables' => [
        'prefix' => 'your_prefix_',
        'tables' => [
            'profiles' => [
                'name' => 'profiles',
                'columns' => [
                    'id' => 'BIGINT UNSIGNED NOT NULL AUTO_INCREMENT',
                    'user_id' => 'BIGINT UNSIGNED',
                    'email' => 'VARCHAR(255)',
                    'token' => 'VARCHAR(64)',
                    'token_expires' => 'DATETIME',
                    'last_login' => 'DATETIME',
                    'created_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP',
                    'updated_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
                ],
                'primary_key' => 'id',
                'indexes' => [
                    'user_id' => ['user_id'],
                    'email' => ['email'],
                    'token' => ['token'],
                ],
            ],
        ],
    ],

    // Post Types
    'post_types' => [
        'profile' => [
            'labels' => [
                'name'               => 'Profiles',
                'singular_name'      => 'Profile',
                'menu_name'          => 'Profiles',
                'add_new'            => 'Add New',
                'add_new_item'       => 'Add New Profile',
                'edit_item'          => 'Edit Profile',
                'new_item'           => 'New Profile',
                'view_item'          => 'View Profile',
                'search_items'       => 'Search Profiles',
                'not_found'          => 'No profiles found',
                'not_found_in_trash' => 'No profiles found in Trash',
            ],
            'public'              => true,
            'show_ui'             => true,
            'show_in_menu'        => true,
            'show_in_nav_menus'   => true,
            'show_in_admin_bar'   => true,
            'menu_position'       => 5,
            'menu_icon'           => 'dashicons-groups',
            'hierarchical'        => false,
            'supports'            => ['title', 'editor', 'thumbnail', 'custom-fields'],
            'has_archive'         => true,
            'rewrite'             => ['slug' => 'profiles'],
            'show_in_rest'        => true,
        ],
    ],

    // Taxonomies
    'taxonomies' => [
        'profile_category' => [
            'post_type' => 'profile',
            'labels' => [
                'name'              => 'Profile Categories',
                'singular_name'     => 'Profile Category',
                'search_items'      => 'Search Profile Categories',
                'all_items'         => 'All Profile Categories',
                'parent_item'       => 'Parent Profile Category',
                'parent_item_colon' => 'Parent Profile Category:',
                'edit_item'         => 'Edit Profile Category',
                'update_item'       => 'Update Profile Category',
                'add_new_item'      => 'Add New Profile Category',
                'new_item_name'     => 'New Profile Category Name',
                'menu_name'         => 'Categories',
            ],
            'hierarchical'      => true,
            'show_ui'           => true,
            'show_admin_column' => true,
            'query_var'         => true,
            'rewrite'           => ['slug' => 'profile-category'],
            'show_in_rest'      => true,
        ],
    ],

    // Meta Boxes
    'meta_boxes' => [
        'profile_info' => [
            'title'      => 'Profile Information',
            'post_types' => ['profile'],
            'context'    => 'normal',
            'priority'   => 'high',
            'fields'     => [
                'profile_email' => [
                    'name'  => 'Email',
                    'type'  => 'email',
                    'required' => true,
                ],
                'profile_phone' => [
                    'name'  => 'Phone',
                    'type'  => 'tel',
                ],
                'profile_company' => [
                    'name'  => 'Company',
                    'type'  => 'text',
                ],
                'profile_website' => [
                    'name'  => 'Website',
                    'type'  => 'url',
                ],
            ],
        ],
    ],

    // API Endpoints
    'api' => [
        'namespace' => 'your-plugin/v1',
        'endpoints' => [
            'profiles' => [
                'route'    => '/profiles',
                'methods'  => ['GET', 'POST'],
                'callback' => 'handle_profiles_endpoint',
            ],
            'profile' => [
                'route'    => '/profiles/(?P<id>\d+)',
                'methods'  => ['GET', 'PUT', 'DELETE'],
                'callback' => 'handle_profile_endpoint',
            ],
        ],
    ],

    // Email Templates
    'emails' => [
        'magic_link' => [
            'subject' => 'Your Profile Access Link',
            'template' => 'emails/magic-link.php',
        ],
        'welcome' => [
            'subject' => 'Welcome to Your Profile',
            'template' => 'emails/welcome.php',
        ],
    ],

    // Security
    'security' => [
        'token_expiry' => 24, // hours
        'max_login_attempts' => 5,
        'lockout_duration' => 30, // minutes
        'allowed_roles' => ['administrator', 'editor'],
    ],

    // Assets
    'assets' => [
        'css' => [
            'admin' => [
                'src' => 'assets/css/admin.css',
                'deps' => ['wp-components'],
                'version' => '1.0.0',
            ],
            'public' => [
                'src' => 'assets/css/public.css',
                'deps' => [],
                'version' => '1.0.0',
            ],
        ],
        'js' => [
            'admin' => [
                'src' => 'assets/js/admin.js',
                'deps' => ['wp-components', 'wp-element'],
                'version' => '1.0.0',
            ],
            'public' => [
                'src' => 'assets/js/public.js',
                'deps' => [],
                'version' => '1.0.0',
            ],
        ],
    ],

    // Settings
    'settings' => [
        'page_title' => 'Plugin Settings',
        'menu_title' => 'Plugin Settings',
        'capability' => 'manage_options',
        'menu_slug'  => 'plugin-settings',
        'sections'   => [
            'general' => [
                'title' => 'General Settings',
                'fields' => [
                    'enable_feature' => [
                        'type' => 'checkbox',
                        'label' => 'Enable Feature',
                        'default' => true,
                    ],
                    'api_key' => [
                        'type' => 'text',
                        'label' => 'API Key',
                        'default' => '',
                    ],
                ],
            ],
        ],
    ],
]; 