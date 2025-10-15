<?php
/**
 * Polys Meta Key Constants (new)
 * Add-only: central place to avoid string drift across services/controllers.
 */

// Nav menu item meta
if (!defined('META_MENU_GUEST_TYPE'))      define('META_MENU_GUEST_TYPE', '_guest_type');
if (!defined('META_MENU_EVENT_TYPE'))      define('META_MENU_EVENT_TYPE', '_event_type');
if (!defined('META_MENU_CLASSES'))         define('META_MENU_CLASSES', '_menu_item_classes');
if (!defined('META_MENU_COORDS'))          define('META_MENU_COORDS', '_coords');
if (!defined('META_MENU_OFFSET'))          define('META_MENU_OFFSET', '_offset');
if (!defined('META_MENU_APPEARANCE_TYPE')) define('META_MENU_APPEARANCE_TYPE', '_appearance_type');

// Profile meta
if (!defined('META_PROFILE_IS_COMPANY'))   define('META_PROFILE_IS_COMPANY', 'is_company');
if (!defined('META_PROFILE_TITLE'))        define('META_PROFILE_TITLE', 'profile_title');
if (!defined('META_PROFILE_COMPANY'))      define('META_PROFILE_COMPANY', 'profile_company');
if (!defined('META_PROFILE_SORT_NAME'))    define('META_PROFILE_SORT_NAME', 'sort_name');
if (!defined('META_PROFILE_EMAIL'))        define('META_PROFILE_EMAIL', 'email');
if (!defined('META_PROFILE_WEBSITE'))      define('META_PROFILE_WEBSITE', 'website');
if (!defined('META_PROFILE_LINKEDIN'))     define('META_PROFILE_LINKEDIN', 'linkedin');
if (!defined('META_PROFILE_TWITTER'))      define('META_PROFILE_TWITTER', 'twitter');
if (!defined('META_PROFILE_BSKY'))         define('META_PROFILE_BSKY', 'bsky');
if (!defined('META_PROFILE_FACEBOOK'))     define('META_PROFILE_FACEBOOK', 'facebook');
if (!defined('META_PROFILE_INSTAGRAM'))    define('META_PROFILE_INSTAGRAM', 'instagram');
if (!defined('META_PROFILE_YOUTUBE'))      define('META_PROFILE_YOUTUBE', 'youtube');
if (!defined('META_PROFILE_LOGO'))         define('META_PROFILE_LOGO', 'logo');
if (!defined('META_PROFILE_LOGO_3D'))      define('META_PROFILE_LOGO_3D', '3Dlogo');
if (!defined('META_PROFILE_SCREENSHOT'))   define('META_PROFILE_SCREENSHOT', 'screenshot');

// Event/Resource meta
if (!defined('META_EVENT_DURATION'))       define('META_EVENT_DURATION', 'duration');
if (!defined('META_EVENT_UTC_START'))      define('META_EVENT_UTC_START', 'utc_start');
if (!defined('META_EVENT_EMBED_URL'))      define('META_EVENT_EMBED_URL', 'embed_video_url');
if (!defined('META_EVENT_VIDEO_URL'))      define('META_EVENT_VIDEO_URL', 'video_url');
if (!defined('META_EVENT_PLAYLIST_URL'))   define('META_EVENT_PLAYLIST_URL', 'playslist_url');
if (!defined('META_EVENT_STYLE_CLASS'))    define('META_EVENT_STYLE_CLASS', 'event_style_class');
if (!defined('META_EVENT_SCRIPT_URL'))     define('META_EVENT_SCRIPT_URL', 'event_script_url');
if (!defined('META_EVENT_REEL_URL'))       define('META_EVENT_REEL_URL', 'event_reel_url');
if (!defined('META_EVENT_SUPPRESS_SPEAKERS')) define('META_EVENT_SUPPRESS_SPEAKERS', 'suppress_speaker_list');
if (!defined('META_EVENT_SESSION_TYPE'))   define('META_EVENT_SESSION_TYPE', 'session_type');

// Section/Page meta
if (!defined('META_SECTION_MENU'))         define('META_SECTION_MENU', 'section_menu');
if (!defined('META_SECTION_CLASS'))        define('META_SECTION_CLASS', 'section_class');
if (!defined('META_SECTION_STRIP_LABEL'))  define('META_SECTION_STRIP_LABEL', 'section_strip_from_label');
if (!defined('META_SECTION_HERO_CLASS'))   define('META_SECTION_HERO_CLASS', 'section_hero_class');
