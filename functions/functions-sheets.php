<?php
/**
 * FUNCTIONS-SHEETS.PHP
 * Events = Rows | Profiles nested under Events = Column G data
 * Walks the full menu tree. Any menu item linked to post_type=event becomes a row.
 */

add_action( 'rest_api_init', function () {
    register_rest_route( 'showrunner/v1', '/export', array(
        'methods'  => 'GET',
        'callback' => 'showrunner_export_callback',
        'permission_callback' => '__return_true',
    ));
});

function showrunner_export_callback( $request ) {
    $menu_id = $request->get_param( 'menu_id' );
    if ( ! $menu_id ) {
        return new WP_Error( 'no_menu', 'Menu ID required', array( 'status' => 400 ) );
    }

    $menu_items = wp_get_nav_menu_items( $menu_id );
    if ( ! $menu_items ) {
        return new WP_Error( 'empty_menu', 'No items found in menu', array( 'status' => 404 ) );
    }

    // Pre-index: parent_id => [child items]
    $children_map = array();
    // Pre-index: item ID => item (for parent lookups)
    $item_map = array();

    foreach ( $menu_items as $item ) {
        $item_map[ $item->ID ] = $item;
        if ( $item->menu_item_parent != 0 ) {
            $children_map[ $item->menu_item_parent ][] = $item;
        }
    }

    $export   = array();
    $sequence = 1;

    foreach ( $menu_items as $item ) {
        // Only create rows for event post types, at any depth
        if ( $item->object !== 'event' ) {
            continue;
        }

        $post = get_post( $item->object_id );
        if ( ! $post ) {
            continue;
        }

        // Resolve parent menu item (null if top-level)
        $parent_item = isset( $item_map[ $item->menu_item_parent ] ) 
            ? $item_map[ $item->menu_item_parent ] 
            : null;

        // Gather guests from child profiles
        $guest_info = array();
        if ( isset( $children_map[ $item->ID ] ) ) {
            foreach ( $children_map[ $item->ID ] as $child ) {
                if ( $child->object !== 'profile' ) {
                    continue;
                }

                $child_post_id = $child->object_id;

                // _guest_type is on the profile post meta
                $guest_type = get_post_meta( $child_post_id, '_guest_type', true );
                if ( $guest_type === 'nominee' ) {
                    continue;
                }

                $name            = $child->title;
                $appearance_type = get_post_meta( $child_post_id, 'appearance_type', true );
                $status          = get_post_meta( $child_post_id, 'confirmation_status', true );
                $poc             = get_post_meta( $child_post_id, 'point_of_contact', true );

                $guest_info[] = array(
                    'name'            => $name,
                    'appearance_type' => $appearance_type,
                    'status'          => $status,
                    'poc'             => $poc,
                );
            }
        }

        // Build flat guest string for Column G
        $guest_strings = array();
        foreach ( $guest_info as $g ) {
            $guest_strings[] = "{$g['name']} ({$g['appearance_type']} | {$g['status']} | POC: {$g['poc']})";
        }

        $letter   = 'C'; // Adjust per event_type if needed
        $combined = $letter . $sequence;

        $export[] = array(
            'A_letter'      => $letter,
            'B_sequence'    => $sequence,
            'C_combined'    => $combined,
            'D_time'        => null,                // Placeholder - Google Sheets chain formula
            'E_duration'    => (int) get_post_meta( $item->ID, '_event_length_seconds', true ),
            'F_title'       => $post->post_title,
            'G_guests'      => implode( ' | ', $guest_strings ),
            'H_menu_id'     => $item->ID,
            'I_post_id'     => $item->object_id,
            // Debug/context fields (strip before sending to Sheets if needed)
            'parent_menu_id' => $item->menu_item_parent,
            'parent_title'   => $parent_item ? $parent_item->title : null,
            'event_type'     => get_post_meta( $item->object_id, '_event_type', true ),
            'guest_count'    => count( $guest_info ),
        );

        $sequence++;
    }

    return rest_ensure_response( $export );
}