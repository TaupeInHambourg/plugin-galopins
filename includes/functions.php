<?php

/**
 * Galopins Tools
 *
 * @package GalopinsTools
 * @author Agence Galopins
 * @link https://agencegalopins.com/
 * @version 1.0.0
**/



// No direct access.
if ( ! defined( 'GLP_PATH' ) ) {
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
	exit();
}

// Manually set the page to 404 if the category slug is not found
add_action('template_redirect', function() {
    global $wp_query;
    if (is_archive() && !have_posts() && isset($wp_query->query['category_name'])) {
        $cat = get_category_by_slug($wp_query->query['category_name']);
        if (!$cat) {
            $wp_query->set_404();
            status_header(404);
        }
    }
}, 1);

// Get relationship between cases-studies and customer-reviews
add_action('elementor/query/get_review', function($query){
	global $post;
	if(!$post) $post=get_post();
	$postId = $post->ID;

	$review = get_post_meta($postId, 'retour_client');

	// Hide the node if no review is found
	if(empty($review[0])) {
        echo '<style>
        .elementor-element-a4e4ff1 {
            display: none !important;
        }
        </style>';
	};

	$query->set('post__in', $review[0]);

});
