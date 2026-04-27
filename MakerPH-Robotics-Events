<?php
/**
 * Plugin Name: Robotics Competition Manager
 * Description: Manage upcoming and recent robotics events with social sharing.
 * Version: 1.0
 * Author: Gemini
 */

if (!defined('ABSPATH')) exit;

// 1. Register Custom Post Type
add_action('init', 'rb_register_event_cpt');
function rb_register_event_cpt() {
    register_post_type('robotics_event', [
        'labels' => ['name' => 'Robotics Events', 'singular_name' => 'Event'],
        'public' => true,
        'has_archive' => true,
        'menu_icon' => 'dashicons-performance',
        'supports' => ['title', 'editor', 'thumbnail'],
        'show_in_rest' => true,
    ]);
}

// 2. Add Meta Boxes for Admin Panel
add_action('add_meta_boxes', function() {
    add_meta_box('event_details', 'Event Details', 'rb_event_meta_html', 'robotics_event', 'normal', 'high');
});

function rb_event_meta_html($post) {
    $venue = get_post_meta($post->ID, '_event_venue', true);
    $event_date = get_post_meta($post->ID, '_event_date', true);
    $reg_date = get_post_meta($post->ID, '_reg_date', true);
    $categories = get_post_meta($post->ID, '_event_categories', true);
    ?>
    <p>Venue: <input type="text" name="event_venue" value="<?php echo esc_attr($venue); ?>" class="widefat"></p>
    <p>Event Date: <input type="date" name="event_date" value="<?php echo esc_attr($event_date); ?>"></p>
    <p>Registration Deadline: <input type="date" name="reg_date" value="<?php echo esc_attr($reg_date); ?>"></p>
    <p>Categories: <input type="text" name="event_categories" value="<?php echo esc_attr($categories); ?>" placeholder="e.g. Sumo, Line Follower" class="widefat"></p>
    <?php
}

add_action('save_post', function($post_id) {
    if (isset($_POST['event_venue'])) update_post_meta($post_id, '_event_venue', $_POST['event_venue']);
    if (isset($_POST['event_date'])) update_post_meta($post_id, '_event_date', $_POST['event_date']);
    if (isset($_POST['reg_date'])) update_post_meta($post_id, '_reg_date', $_POST['reg_date']);
    if (isset($_POST['event_categories'])) update_post_meta($post_id, '_event_categories', $_POST['event_categories']);
});

// 3. Frontend Shortcode [robotics_events]
add_shortcode('robotics_events', 'rb_display_events');
function rb_display_events() {
    $today = date('Y-m-d');
    
    $output = '<h2>Upcoming Robotics Competitions</h2>';
    $output .= rb_render_event_table('upcoming', $today);
    
    $output .= '<h2 style="margin-top:40px;">Recent Events</h2>';
    $output .= rb_render_event_table('recent', $today);
    
    return $output;
}

function rb_render_event_table($type, $today) {
    $compare = ($type == 'upcoming') ? '>=' : '<';
    $args = [
        'post_type' => 'robotics_event',
        'posts_per_page' => -1,
        'meta_key' => '_event_date',
        'orderby' => 'meta_value',
        'order' => ($type == 'upcoming') ? 'ASC' : 'DESC',
        'meta_query' => [
            [
                'key' => '_event_date',
                'value' => $today,
                'compare' => $compare,
                'type' => 'DATE'
            ]
        ]
    ];

    $query = new WP_Query($args);
    if (!$query->have_posts()) return '<p>No events found.</p>';

    $html = '<table border="1" style="width:100%; border-collapse: collapse; text-align: left;">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Event</th>
                        <th>Poster</th>
                        <th>Categories</th>
                        <th>Venue</th>
                        ' . ($type == 'upcoming' ? '<th>Registration</th>' : '') . '
                        <th>Share</th>
                    </tr>
                </thead>
                <tbody>';

    while ($query->have_posts()) {
        $query->the_post();
        $id = get_the_ID();
        $img = get_the_post_thumbnail_url($id, 'thumbnail') ?: 'No Image';
        
        $html .= '<tr>';
        $html .= '<td>' . esc_html(get_post_meta($id, '_event_date', true)) . '</td>';
        $html .= '<td><strong>' . get_the_title() . '</strong></td>';
        $html .= '<td><img src="'.$img.'" width="50"></td>';
        $html .= '<td>' . esc_html(get_post_meta($id, '_event_categories', true)) . '</td>';
        $html .= '<td>' . esc_html(get_post_meta($id, '_event_venue', true)) . '</td>';
        
        if ($type == 'upcoming') {
            $html .= '<td>' . esc_html(get_post_meta($id, '_reg_date', true)) . '</td>';
        }
        
        // Jetpack Social Integration
        $share = '';
        if ( function_exists( 'sharing_display' ) ) {
            $share = sharing_display( '', false );
        }
        
        $html .= '<td>' . $share . '</td>';
        $html .= '</tr>';
    }
    $html .= '</tbody></table>';
    wp_reset_postdata();
    return $html;
}
