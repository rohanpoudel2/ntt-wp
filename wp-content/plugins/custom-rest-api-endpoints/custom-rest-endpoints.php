<?php
/*
Plugin Name: Custom REST API Endpoints
Description: Adds custom REST API endpoints.
Version: 1.0
Author: Rohan Poudel
*/

function custom_rest_trip_route()
{
  register_rest_route(
    'ntt/v1',
    'trips',
    array(
      'methods' => 'GET',
      'callback' => 'get_filtered_trips',
    )
  );
}
add_action('rest_api_init', 'custom_rest_trip_route');

function get_filtered_trips($request)
{
  $custom_data = $request->get_header('X-Custom-Data');
  $data = json_decode($custom_data, true);
  $country_param = $data['country'];
  $activity_param = $data['activity'];
  $list_param = $data['list'];
  $destination_param = $request->get_param('destination');

  if (empty($country_param) || empty($activity_param)) {
    return array();
  }

  $args = array(
    'post_type' => 'trip',
    'post_status' => 'publish',
    'posts_per_page' => -1,
    'tax_query' => array(
      'relation' => 'AND',
      array(
        'taxonomy' => 'country',
        'field' => 'name',
        'terms' => $country_param,
      ),
      array(
        'taxonomy' => 'activities',
        'field' => 'name',
        'terms' => $activity_param,
      ),
    ),
  );

  if (!empty($destination_param)) {
    $args['tax_query'][] = array(
      'taxonomy' => 'destination',
      'field' => 'slug',
      'terms' => $destination_param,
    );
  }

  $posts = get_posts($args);
  $result = array();

  foreach ($posts as $post) {
    $acf_fields = get_fields($post->ID);
    $country_terms = get_the_terms($post->ID, 'country');
    $activity_terms = get_the_terms($post->ID, 'activities');
    $destination_terms = get_the_terms($post->ID, 'destination');
    $difficulty_terms = get_the_terms($post->ID, 'difficulty');
    $featured_media_id = get_post_thumbnail_id($post->ID);

    $country_name = wp_list_pluck(array_filter($country_terms, function ($term) {
      return $term->parent === 0;
    }), 'name');

    $difficulty_name = wp_list_pluck($difficulty_terms, 'name');

    $destination_names = wp_list_pluck(array_filter($destination_terms, function ($term) {
      return $term->parent !== 0;
    }), 'name');

    $activity_names = wp_list_pluck(array_filter($activity_terms, function ($term) {
      return $term->parent !== 0;
    }), 'name');

    $formatted_activities = [implode(', ', $activity_names)];

    $image_sizes = get_intermediate_image_sizes();

    $featured_media_sizes = array();
    foreach ($image_sizes as $size) {
      $image_data = wp_get_attachment_image_src($featured_media_id, $size);
      if ($image_data) {
        $featured_media_sizes[$size] = $image_data[0];
      }
    }

    $item = array(
      'id' => $post->ID,
      'date' => $post->post_date,
      'slug' => $post->post_name,
      'name' => $post->post_title,
      'country' => $country_name,
      'area' => $destination_names,
      'activities' => $formatted_activities,
      'difficulty' => $difficulty_name,
      'duration' => get_field('duration', $post->ID),
      'image' => $featured_media_sizes,
    );

    if (isset($list_param) && filter_var($list_param, FILTER_VALIDATE_BOOLEAN)) {
      $result[] = $item;
    } else {
      $item['date_gmt'] = $post->post_date_gmt;
      $item['guid'] = array(
        'rendered' => get_permalink($post->ID),
      );
      $item['modified'] = $post->post_modified;
      $item['modified_gmt'] = $post->post_modified_gmt;
      $item['status'] = $post->post_status;
      $item['type'] = $post->post_type;
      $item['content'] = array(
        'rendered' => apply_filters('the_content', $post->post_content),
      );
      $item['featured_media'] = $featured_media_sizes;
      $item['activities'] = $formatted_activities;
      $item['destination'] = $destination_names;
      $item['difficulty'] = $difficulty_name;
      $item['acf'] = $acf_fields;

      $result[] = $item;
    }
  }

  return $result;
}
