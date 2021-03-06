<?php
/**
 * Plugin Name: WP Job Manager - Public API
 * Plugin URI: http://www.academe.co.uk/
 * Description: WP Plugin to expose non-sensitive WP Job Manager job details through the WP REST API.
 * Version: 1.1.1
 * Author: Academe Computing
 * Author URI: http://www.academe.co.uk/
 * Text Domain: wp-job-manager-public-api
 * Domain Path: /languages
 * License: GPLv2 or later
 */

// Exit if accessed directly
if (! defined('ABSPATH')) {
    exit;
}

/**
 * API Access to job titles and some other limited data.
 */
add_action('rest_api_init', function () {
    $api_route = 'wpjm_public/v1';

    // Include expired jobs, because they *were* most likely public once.
    $post_statuses = ['publish', 'expired'];
    $post_type = 'job_listing';

    // Construct the data structure to be returned for a post.
    $format_post = function($post) {
        return [
            'post_id' => $post->ID,
            'post_title' => $post->post_title,
            'post_name' => $post->post_name,
            'post_type' => $post->post_type,
            'permalink' => get_permalink($post),
            'post_date_gmt' => $post->post_date_gmt,
            'post_status' => $post->post_status,
            'guid' => $post->guid,
            'application_deadline_date' => get_post_meta($post->ID, '_application_deadline', true),
            'listing_expiry_date' => get_post_meta($post->ID, '_job_expires', true),
        ];
    };

    register_rest_route($api_route, '/job/(?P<id>\d+)',[
        'methods' => 'GET',
        'callback' => function(WP_REST_Request $request) use ($post_statuses, $post_type, $format_post) {
            // The ID of the job we want.
            $post_id = $request->get_param('id');

            // Get the job.
            $post = get_post($post_id);

            // Return the post details if the post type and status is acceptable.
            if ($post->post_type == $post_type && in_array($post->post_status, $post_statuses)) {
                return $format_post($post);
            }

            // No post or the wrong type of post.
            return new WP_Error('invalid_job', 'Invalid job', ['status' => 404]);
        },
        'args' => [
            'id',
        ],
    ]);

    /**
     * The callback function, used over several similar routes.
     */
    $callback = function(WP_REST_Request $request) use ($post_statuses, $post_type, $format_post) {
        // Just an assumption this will be okay for now.
        $posts_per_page = 1000;

        // Dates are inclusive and can use words, but replace spaces with underscores,
        // for example "this_month" (thought they don't seem to work).
        $after = str_replace('_', ' ', $request->get_param('date_from', ''));
        $before = str_replace('_', ' ', $request->get_param('date_to', ''));

        $args = [
            'post_status' => $post_statuses,
            'post_type' => $post_type,
            'posts_per_page' => $posts_per_page,
        ];

        if ($before || $after) {
            // Make sure the dates are inclusive, so /2017/2017 will give all
            // jobs for 2017, which is more intuitive.
            $args['date_query'] = ['inclusive' => true];

            if ($before) {
                $args['date_query']['before'] = $before;
            }

            if ($after) {
                $args['date_query']['after'] = $after;
            }
        }

        $posts = get_posts($args);

        $data = [];

        foreach($posts as $post) {
            $data[$post->ID] = $format_post($post);
        }

        return $data;
    };

    /**
     * No from or to dates. Defaults to "latest".
     */
    register_rest_route($api_route, '/jobs',[
        'methods' => 'GET',
        'callback' => $callback,
    ]);

    /**
     * From date only.
     */
    register_rest_route($api_route, '/jobs/(?P<date_from>[-+_a-zA-Z0-9]+)',[
        'methods' => 'GET',
        'callback' => $callback,
        'args' => ['date_from'],
    ]);

    /**
     * From and oto dates (date range).
     */
    register_rest_route($api_route, '/jobs/(?P<date_from>[-+_a-zA-Z0-9]+)/(?P<date_to>[-a-zA-Z0-9]+)',[
        'methods' => 'GET',
        'callback' => $callback,
        'args' => ['date_from', 'date_to'],
    ]);
});
