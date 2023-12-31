<?php
/*
Plugin Name: Custom REST API Endpoints
Description: Adds custom REST API endpoints.
Version: 1.0
Author: Rohan Poudel
*/

function custom_rest_trip_list_route()
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
add_action('rest_api_init', 'custom_rest_trip_list_route');

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

    $destination_taxonomy_details = array_filter($destination_terms, function ($term) {
      return $term->parent !== 0;
    });

    $destination_taxonomy_description = $destination_taxonomy_details[0]->description;
    $destination_taxonomy_acf = get_field('hero', 'destination_' . $destination_taxonomy_details[0]->term_id);
    $destination_taxonomy_media_id = $destination_taxonomy_acf['image']['id'];

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

    $destination_taxonomy_image_sizes = array();
    foreach ($image_sizes as $size) {
      $image_data = wp_get_attachment_image_src($destination_taxonomy_media_id, $size);
      if ($image_data) {
        $destination_taxonomy_image_sizes[$size] = $image_data[0];
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
      $item['prices'] = $acf_fields['prices'];
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
      $item['activities'] = $formatted_activities;
      $item['destination'] = $destination_names;
      $item['difficulty'] = $difficulty_name;
      $item['acf'] = $acf_fields;

      $result[] = $item;
    }
  }

  $taxonomy_array = array(
    'description' => $destination_taxonomy_description,
    'image' => $destination_taxonomy_image_sizes,
    'tours' => $result
  );

  return $taxonomy_array;
}


function custom_rest_trip_route()
{
  register_rest_route(
    'ntt/v1',
    'trip',
    array(
      'methods' => 'GET',
      'callback' => 'get_trip',
    )
  );
}
add_action('rest_api_init', 'custom_rest_trip_route');

function get_trip($request)
{
  $trip_data = $request->get_header('X-Trip-data');
  $data = json_decode($trip_data, true);
  $trip_slug = $data['trip'];
  $country_param = $data['country'];
  $activity_param = $data['activity'];
  $region_param = $data['region'];

  if (empty($trip_data)) {
    return array();
  }

  $args = array(
    'name' => $trip_slug,
    'post_type' => 'trip',
    'post_status' => 'publish',
    'posts_per_page' => 1,
  );

  $posts = get_posts($args);

  if (empty($posts)) {
    return array();
  }

  $post = $posts[0];
  $post->post_content = apply_filters('the_content', $post->post_content);
  $acf_fields = get_fields($post->ID);

  $recommendation = basic_trip_fetch($acf_fields['recommendation']->ID);

  $acf_fields['recommendation'] = $recommendation;

  if (isset($acf_fields['gallery']) && is_array($acf_fields['gallery'])) {
    $modified_gallery = array();

    foreach ($acf_fields['gallery'] as $image) {
      $thumbnail_url = wp_get_attachment_image_src($image['ID'], 'thumbnail');
      $medium_url = wp_get_attachment_image_src($image['ID'], 'medium');
      $large_url = wp_get_attachment_image_src($image['ID'], 'large');
      $image_sizes = array(
        'thumbnail' => array('url' => $thumbnail_url[0]),
        'medium' => array('url' => $medium_url[0]),
        'large' => array('url' => $large_url[0]),
      );
      $alt_text = get_post_meta($image['ID'], '_wp_attachment_image_alt', true);

      $image_data = array(
        'sizes' => $image_sizes,
        'alt' => $alt_text,
      );

      $modified_gallery[] = $image_data;
    }

    $acf_fields['gallery'] = $modified_gallery;
  }

  $featured_media_id = get_post_thumbnail_id($post->ID);
  $featured_media_alt = get_post_meta($featured_media_id, '_wp_attachment_image_alt', true);

  $featured_media = array(
    'alt' => $featured_media_alt,
    'sizes' => array(
      'thumbnail' => array('url' => wp_get_attachment_image_url($featured_media_id, 'thumbnail')),
      'medium' => array('url' => wp_get_attachment_image_url($featured_media_id, 'medium')),
      'large' => array('url' => wp_get_attachment_image_url($featured_media_id, 'large')),
      'full' => array('url' => wp_get_attachment_image_url($featured_media_id, 'full')),
    ),
  );

  $taxonomy_terms = wp_get_post_terms($post->ID, array('country', 'activities', 'destination', 'difficulty'));

  $country_terms = array();
  $activity_terms = array();
  $destination_terms = array();
  $difficulty_terms = array();

  foreach ($taxonomy_terms as $term) {
    if ($term->taxonomy === 'country') {
      $country_terms[] = $term->name;
    } elseif ($term->taxonomy === 'difficulty') {
      $difficulty_terms[] = $term->name;
    } elseif ($term->parent !== 0) {
      switch ($term->taxonomy) {
        case 'activities':
          $activity_terms[] = $term->name;
          break;
        case 'destination':
          $destination_terms[] = $term->name;
          break;
      }
    }
  }

  $trip_data = array(
    'post' => $post,
    'acf' => $acf_fields,
    'country' => $country_terms,
    'activities' => $activity_terms,
    'destination' => $destination_terms,
    'difficulty' => $difficulty_terms,
    'featured_media' => $featured_media
  );

  $country_param = strtolower($country_param);
  $activity_param = strtolower($activity_param);
  $region_param = strtolower($region_param);
  $destination_terms = array_map('strtolower', $destination_terms);
  $country_terms = array_map('strtolower', $country_terms);
  $activity_terms = array_map('strtolower', $activity_terms);

  if (in_array($country_param, $country_terms) && in_array($activity_param, $activity_terms) && in_array($region_param, $destination_terms)) {
    return $trip_data;
  } else {
    return array();
  }
}

function custom_rest_activity_region_route()
{
  register_rest_route(
    'ntt/v1',
    'activity_region',
    array(
      'methods' => 'GET',
      'callback' => 'get_activity_region',
    )
  );
}
add_action('rest_api_init', 'custom_rest_activity_region_route');

function get_activity_region($request)
{
  $trip_data = $request->get_header('X-region-data');
  $data = json_decode($trip_data, true);
  $country_param = $data['country'];
  $activity_param = $data['activity'];

  if (empty($trip_data)) {
    return array();
  }

  $args = array(
    'post_type' => 'trip',
    'tax_query' => array(
      'relation' => 'AND',
      array(
        'taxonomy' => 'country',
        'field' => 'slug',
        'terms' => $country_param,
      ),
      array(
        'taxonomy' => 'activities',
        'field' => 'slug',
        'terms' => $activity_param,
      ),
    ),
  );

  $trips = get_posts($args);

  if (empty($trips)) {
    return array();
  }

  $trip_destinations = array();
  $filtered_destinations = array();
  $filtered_activities = array();
  foreach ($trips as $trip) {
    $destination_terms = wp_get_post_terms($trip->ID, 'destination');
    $activities_terms = wp_get_post_terms($trip->ID, 'activities');
    foreach ($destination_terms as $term) {
      $fields = get_fields($term);
      $term->acf = $fields;
      if ($term->parent !== 0) {
        $filtered_destinations[] = $term;
      }
    }
    foreach ($activities_terms as $term) {
      $fields = get_fields($term);
      $term->acf = $fields;
      if ($term->parent !== 0) {
        $filtered_activities = $term;
        break;
      }
    }
    $trip_destinations = $filtered_destinations;
    array_push($trip_destinations, $filtered_activities);
  }


  return $trip_destinations;
}

function country_information()
{
  register_rest_route(
    'ntt/v1',
    'country',
    array(
      'methods' => 'GET',
      'callback' => 'get_country_information',
    )
  );
}
add_action('rest_api_init', 'country_information');

function get_country_information($request)
{
  $country_data = $request->get_header('X-country-data');
  $data = json_decode($country_data, true);
  $country_param = $data['country'];
  if (empty($country_data)) {
    return array();
  }
  $args = array(
    'post_type' => 'country-information',
    'name' => $country_param,
  );
  $country_information = get_posts($args);
  if (empty($country_information)) {
    return array();
  }
  $country_acf = get_fields($country_information[0]->ID);
  $top_rated_array = array();
  $processed_posts = array();

  $top_rated = $country_acf['top_rated_treks'];
  $best_activity = $country_acf['best_activity_region'];

  foreach ($top_rated as $post) {
    if (in_array($post->ID, $processed_posts)) {
      continue;
    }

    $processed_posts[] = $post->ID;
    $country_terms = get_terms(array('taxonomy' => 'country', 'object_ids' => $post->ID, 'fields' => 'slugs', 'exclude' => array(0)));
    $activities_terms = get_terms(array('taxonomy' => 'activities', 'object_ids' => $post->ID, 'fields' => 'slugs', 'exclude' => array(0)));
    $destination_terms = get_terms(array('taxonomy' => 'destination', 'object_ids' => $post->ID, 'fields' => 'slugs', 'exclude' => array(0)));

    $country_terms_filtered = array();
    $activity_terms_filtered = array();
    $destination_terms_filtered = array();

    foreach ($country_terms as $term_slug) {
      $term = get_term_by('slug', $term_slug, 'country');
      if ($term->parent === 0) {
        $country_terms_filtered[] = $term_slug;
      }
    }

    foreach ($activities_terms as $term_slug) {
      $term = get_term_by('slug', $term_slug, 'activities');
      if ($term->parent !== 0) {
        $activity_terms_filtered[] = $term_slug;
      }
    }

    foreach ($destination_terms as $term_slug) {
      $term = get_term_by('slug', $term_slug, 'destination');
      if ($term->parent !== 0) {
        $destination_terms_filtered[] = $term_slug;
      }
    }

    $post_data = array(
      'id' => $post->ID,
      'date' => $post->post_date,
      'date_gmt' => $post->post_date_gmt,
      'guid' => $post->guid,
      'modified' => $post->post_modified,
      'modified_gmt' => $post->post_modified_gmt,
      'slug' => $post->post_name,
      'status' => $post->post_status,
      'title' => array(
        'rendered' => $post->post_title,
      ),
      'featured_image' => get_the_post_thumbnail_url($post->ID),
      'country' => $country_terms_filtered,
      'activities' => $activity_terms_filtered,
      'destination' => $destination_terms_filtered,
    );

    $top_rated_array[] = $post_data;
  }
  $new = array();
  foreach ($best_activity as $term) {
    $region_posts = get_posts(
      array(
        'post_type' => 'trip',
        'tax_query' => array(
          array(
            'taxonomy' => 'destination',
            'field' => 'slug',
            'terms' => $term,
          ),
        ),
      )
    );
    foreach ($region_posts as $post) {
      $activities_terms = get_terms(array('taxonomy' => 'activities', 'object_ids' => $post->ID, 'fields' => 'slugs', 'exclude' => array(0)));
      $activity_terms_filtered = array();
      foreach ($activities_terms as $term_slug) {
        $term = get_term_by('slug', $term_slug, 'activities');
        if ($term && $term->parent !== 0 && $term->parent !== null) {
          $activity_terms_filtered[] = $term_slug;
        }
      }
      $region_activities = $activity_terms_filtered;
    }
    $term->activities = $region_activities;
    array_push($new, [$region_activities, get_fields($term)]);
  }

  $country_information['acf'] = $country_acf;
  $country_information['acf']['top_rated_treks'] = $top_rated_array;
  $country_information['acf']['best_activity_region'] = $best_activity;
  $country_information['acf']['map_activities'] = $new;

  return $country_information;
}

function hearted_trip()
{
  register_rest_route(
    'ntt/v1',
    'hearted',
    array(
      'methods' => 'GET',
      'callback' => 'get_hearted_trips',
    )
  );
}
add_action('rest_api_init', 'hearted_trip');

function get_hearted_trips($request)
{
  $country_data = $request->get_header('X-heart-data');
  $data = json_decode($country_data, true);
  $ids = $data['ids'];

  $responses = array();

  foreach ($ids as $id) {
    $response = basic_trip_fetch($id);
    $responses[] = $response;
  }

  return $responses;
}

function basic_trip_fetch($id)
{
  $post = get_post($id);

  if (empty($post)) {
    return array();
  }

  $featured_image_url = get_the_post_thumbnail_url($post->ID, 'medium_large');
  $post_content = apply_filters('the_content', $post->post_content);

  $country_terms = wp_get_post_terms($post->ID, 'country', array('fields' => 'slugs'));
  $activities_terms = wp_get_post_terms($post->ID, 'activities', array('fields' => 'slugs'));
  $destination_terms = wp_get_post_terms($post->ID, 'destination', array('fields' => 'slugs'));
  $difficulty_term = wp_get_post_terms($post->ID, 'difficulty', array('fields' => 'slugs'));

  $country_terms_filtered = '';
  $activity_terms_filtered = '';
  $destination_terms_filtered = '';

  foreach ($country_terms as $term_slug) {
    $term = get_term_by('slug', $term_slug, 'country');
    if ($term->parent === 0) {
      $country_terms_filtered = $term_slug;
    }
  }

  foreach ($activities_terms as $term_slug) {
    $term = get_term_by('slug', $term_slug, 'activities');
    if ($term->parent !== 0) {
      $activity_terms_filtered = $term_slug;
    }
  }

  foreach ($destination_terms as $term_slug) {
    $term = get_term_by('slug', $term_slug, 'destination');
    if ($term->parent !== 0) {
      $destination_terms_filtered = $term_slug;
    }
  }

  $acf = get_fields($post->ID);
  $price = $acf['prices']['price'];
  $sale_price = $acf['prices']['sale_price'];
  $rating = $acf['avg_rating'];
  $duration = $acf['duration'];

  $responses = array(
    'ID' => $post->ID,
    'post_title' => $post->post_title,
    'post_status' => $post->post_status,
    'post_name' => $post->post_name,
    'post_type' => $post->post_type,
    'featured_image_url' => $featured_image_url,
    'post_content' => $post_content,
    'country' => $country_terms_filtered,
    'activities' => $activity_terms_filtered,
    'destination' => $destination_terms_filtered,
    'difficulty' => $difficulty_term[0],
    'duration' => $duration,
    'price' => $price,
    'sale_price' => $sale_price,
    'rating' => $rating,
  );
  return $responses;
}

function home_page()
{
  register_rest_route(
    'ntt/v1',
    'home',
    array(
      'methods' => 'GET',
      'callback' => 'get_home_page',
    )
  );
}
add_action('rest_api_init', 'home_page');

function get_home_page($request)
{
  $home_page = get_page_by_path('home');

  if (!$home_page) {
    return new WP_Error('not_found', 'Home page not found', array('status' => 404));
  }

  $acf = get_fields($home_page->ID);
  foreach ($acf["home_hero"] as $key => $image) {
    $acf["home_hero"][$key]["hero_image"] = wp_get_attachment_image_src($image["hero_image"]["ID"], 'full')[0];
  }
  foreach ($acf["countries"] as $countryKey => $country) {
    $trips = $country["country_items"]["activity"]["activity"];
    foreach ($trips as $tripKey => $trip) {
      $temp = get_taxonomy_details($trip, 'activities');
      if ($temp['parent'] !== 0) {
        $trips[$tripKey] = get_taxonomy_details($trip, 'activities');
      } else {
        unset($trips[$tripKey]);
      }
    }
    $acf["countries"][$countryKey]["country_items"]["activity"]["activity"] = $trips;
  }
  foreach ($acf["countries"] as $key => $country) {
    $trips = $country["country_items"]["group_tour"]["group_tour"];
    foreach ($trips as $tripKey => $trip) {
      $trips[$tripKey] = basic_trip_fetch($trip);
    }
    $acf["countries"][$key]["country_items"]["group_tour"]["group_tour"] = $trips;
  }

  foreach ($acf["latest_updates"]["latest_updates"] as $key => $update) {
    $formatted_content = apply_filters('the_content', $update["latest_update"]->post_content);
    $acf["latest_updates"]["latest_updates"][$key]["latest_update"]->post_content = $formatted_content;
  }

  $response = array(
    "data" => $acf,
  );

  return $response;
}

function get_gallery()
{
  register_rest_route(
    'ntt/v1',
    'gallery',
    array(
      'methods' => 'GET',
      'callback' => 'get_trip_gallery',
    )
  );
}
add_action('rest_api_init', 'get_gallery');

function get_trip_gallery($request)
{
  $trip_id = $request->get_param('id');
  if (empty($trip_id)) {
    return array();
  }
  $trip = get_post($trip_id);
  if (empty($trip)) {
    return array();
  }
  $gallery = get_fields($trip)['gallery'];
  $response = array(
    "title" => $trip->post_title,
    "data" => $gallery,
  );
  return $response;
}

function get_taxonomy_details($id, $taxonomy)
{
  $term = get_term($id, $taxonomy);
  $acf = get_fields($term);
  $response = array(
    'term_id' => $term->term_id,
    'parent' => $term->parent,
    'term_name' => $acf['hero']['title'],
    'term_slug' => $term->slug,
    'term_description' => $term->description,
    'image' => $acf['hero']['image']['sizes']['medium_large'],
    'count' => $term->count,
  );

  return $response;
}

function cross_country()
{
  register_rest_route(
    'ntt/v1',
    'cross-country',
    array(
      'methods' => 'GET',
      'callback' => 'get_cross_country',
    )
  );
}
add_action('rest_api_init', 'cross_country');

function get_cross_country($request)
{
  $cross = get_page_by_path('cross-country');

  if (!$cross) {
    return new WP_Error('not_found', 'Cross country page not found', array('status' => 404));
  }

  $acf = get_fields($cross->ID);
  $featured_image_url = get_the_post_thumbnail_url($cross->ID, 'full');

  $args = array(
    'post_type' => 'trip',
    'tax_query' => array(
      array(
        'taxonomy' => 'activities',
        'field' => 'slug',
        'terms' => 'cross-country',
      ),
    ),
  );

  $trips = get_posts($args);

  if ($trips) {
    $formatted_trips = array();
    foreach ($trips as $trip) {
      $trip_data = basic_trip_fetch($trip->ID);
      $formatted_trips[] = $trip_data;
    }
  }

  return array(
    'image' => $featured_image_url,
    'data' => $cross,
    'acf' => $acf,
    'trips' => $formatted_trips,
  );
}

function cross_country_trip()
{
  register_rest_route(
    'ntt/v1',
    'cross-country-trip',
    array(
      'methods' => 'GET',
      'callback' => 'get_cross_country_trip',
    )
  );
}
add_action('rest_api_init', 'cross_country_trip');

function get_cross_country_trip($request)
{
  $slug = $request->get_param('slug');
  $args = array(
    'name' => $slug,
    'post_type' => 'trip',
    'post_status' => 'publish',
    'posts_per_page' => 1,
  );
  $trip = get_posts($args);

  if (!$trip) {
    return array();
  }

  $trip_id = $trip[0]->ID;

  $destinations = wp_get_post_terms($trip_id, 'destination', array('fields' => 'names'));
  $activities = wp_get_post_terms($trip_id, 'activities', array('fields' => 'names'));
  $countries = wp_get_post_terms($trip_id, 'country', array('fields' => 'names'));
  $difficulty = wp_get_post_terms($trip_id, 'difficulty', array('fields' => 'names'));
  $featured_image_url = get_the_post_thumbnail_url($trip_id, 'full');

  $acf = get_fields($trip_id);
  $acf['recommendation'] = basic_trip_fetch($acf['recommendation']->ID);

  return array(
    'trip' => $trip,
    'destinations' => $destinations,
    'activities' => $activities,
    'countries' => $countries,
    'difficulty' => $difficulty,
    'acf' => $acf,
    'featured_media' => $featured_image_url,
  );
}
