<?php

/*
Plugin Name: SGC YouTube
Description: YouTube functions for SGC bellingham Theme
Author: Michael Savchuk
*/

// --- Settings --- //

function sgc_youtube_settings_section_cb() {
	echo '<p>Settings for the SGC YouTube plugin.</p>';
}

function sgc_youtube_settings_key_cb() {
	echo '<input name="sgc_youtube_key" id="sgc_youtube_key" type="password" value="' . get_option('sgc_youtube_key') . '" />';
}

function sgc_youtube_settings_channel_id_cb() {
	echo '<input name="sgc_youtube_channel_id" id="sgc_youtube_channel_id" type="text" value="' . get_option('sgc_youtube_channel_id') . '" />';
}

function sgc_youtube_init() {
	register_setting('media', 'sgc_youtube_key', array( 'sanitize_callback' => 'urlencode' ));
	register_setting('media', 'sgc_youtube_channel_id', array( 'sanitize_callback' => 'urlencode' ));

	add_settings_section(
		'sgc_youtube_settings_section',
		'SGC YouTube Settings',
		'sgc_youtube_settings_section_cb',
		'media'
	);

	add_settings_field(
		'sgc_youtube_key',
		'YouTube Client Key',
		'sgc_youtube_settings_key_cb',
		'media',
		'sgc_youtube_settings_section'
	);

	add_settings_field(
		'sgc_youtube_channel_id',
		'YouTube Channel ID',
		'sgc_youtube_settings_channel_id_cb',
		'media',
		'sgc_youtube_settings_section'
	);
}


// --- Errors --- //

function sample_admin_notice__success() {	
	if (get_option('sgc_youtube_key') == ''):
		?>
			<div class="notice notice-error is-dismissible">
				<p>SGC YouTube Plugin: No client key specified. Go to Settings > Media to enter one.</p>
			</div>
		<?php
	endif;

	if (get_option('sgc_youtube_channel_id') == ''):
		?>
			<div class="notice notice-error is-dismissible">
				<p>SGC YouTube Plugin: No channel ID specified. Go to Settings > Media to enter one.</p>
			</div>
		<?php
	endif;
}
add_action( 'admin_notices', 'sample_admin_notice__success' );


// --- Getters --- //
function sgc_youtube_build_request_url($path, $params) {
	$api_key = get_option('sgc_youtube_key');
	$param_keys = array_keys($params);

	return array_reduce($param_keys, function($result, $item) use($params) {
		return $result . '&'
			. urlencode((string)$item)
			. '='
			. urlencode((string)$params[$item]);
	}, 'https://www.googleapis.com/youtube/v3/' . $path . '/?key=' . $api_key);
}

function sgc_youtube_get_search_items($params, $cache_key) {
	$data = get_transient($cache_key);
	
	if ($data === false) {
		$channel_id = get_option('sgc_youtube_channel_id');
		
		$defaults = array(
			'channelId' => $channel_id,
			'part' => 'snippet',
			'type' => 'video',
			'order' => 'date',
		);

		$params = wp_parse_args($params, $defaults);
		
		$url = sgc_youtube_build_request_url('search', $params);
		
		$response = wp_remote_get($url);
		$body = wp_remote_retrieve_body($response);

		if ($body != '') {
			$data = json_decode($body);

			if (property_exists($data, 'error')) return false;
		} else return false;

		set_transient($cache_key, $data, 10);
	}

	return $data->items;
}

function sgc_youtube_get_latest_videos($results = 5) {
	$items = sgc_youtube_get_search_items(array('maxResults' => $results), "sgc-youtube-latest-{$results}");

	return $items;
}

function sgc_youtube_is_streaming() {
	$items = sgc_youtube_get_search_items(array(
		'maxResults' => 1,
		'eventType' => 'live'
	), 'sgc-youtube-live');

	if (count($items) > 0) {
		return 'https://www.youtube.com/watch?v=' . $items[0]->id->videoId;
	} else return false;
}

add_action('admin_init', 'sgc_youtube_init');