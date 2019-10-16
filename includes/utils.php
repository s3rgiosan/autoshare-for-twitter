<?php
/**
 * A place for everything, everything in its place doesn't apply here.
 * This file is for utility and helper functions.
 *
 * @package TenUp\AutoTweet\Utils
 */

namespace TenUp\AutoTweet\Utils;

use const TenUp\AutoTweet\Core\POST_TYPE_SUPPORT_FEATURE;
use const TenUp\AutoTweet\Core\Post_Meta\ENABLE_AUTOTWEET_KEY;
use const TenUp\AutoTweet\Core\Post_Meta\META_PREFIX;
use const TenUp\AutoTweet\Core\Post_Meta\TWEET_BODY_KEY;
use const TenUp\AutoTweet\Core\Post_Meta\TWITTER_STATUS_KEY;

/**
 * Helper/Wrapper function for returning the meta entries for autotweeting.
 *
 * @param int    $id  The post ID.
 * @param string $key The meta key to retrieve.
 *
 * @return mixed
 */
function get_autotweet_meta( $id, $key ) {
	$data = get_post_meta( $id, sprintf( '%s_%s', META_PREFIX, $key ), true );

	/**
	 * Filters autotweet metadata.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed  Retrieved metadata.
	 * @param int    Post ID.
	 * @param string The meta key.
	 */
	return apply_filters( 'autotweet_meta', $data, $id, $key );
}

/**
 * Updates autotweet-related post metadata by prefixing the passed key.
 *
 * @param int    $id    Post ID.
 * @param string $key   Autotweet meta key.
 * @param mixed  $value The meta value to save.
 * @return mixed The meta_id if the meta doesn't exist, otherwise returns true on success and false on failure.
 */
function update_autotweet_meta( $id, $key, $value ) {
	return update_post_meta( $id, sprintf( '%s_%s', META_PREFIX, $key ), $value );
}

/**
 * Deletes autotweet-related metadata.
 *
 * @param int    $id  The post ID.
 * @param string $key The key of the meta value to delete.
 * @return boolean False for failure. True for success.
 */
function delete_autotweet_meta( $id, $key ) {
	return delete_post_meta( $id, sprintf( '%s_%s', META_PREFIX, $key ) );
}

/**
 * Helper for determining if a post should autotweet.
 *
 * @param int $post_id The post ID.
 *
 * @return bool
 */
function maybe_autotweet( $post_id ) {
	return ( 1 === intval( get_autotweet_meta( $post_id, ENABLE_AUTOTWEET_KEY ) ) ) ? true : false;
}

/**
 * Helper for returning the Auto Tweet site settings.
 *
 * @param string $key The option key.
 *
 * @return mixed
 */
function get_autotweet_settings( $key = '' ) {

	$settings = get_option( \TenUp\AutoTweet\Core\Admin\AT_SETTINGS );

	return ( ! empty( $key ) ) ? $settings[ $key ] : $settings;
}

/**
 * Composes the tweet based off Title and URL.
 *
 * @param \WP_Post $post The post object.
 *
 * @return string
 */
function compose_tweet_body( \WP_Post $post ) {

	/**
	 * Allow filtering of tweet body
	 */
	$tweet_body = apply_filters( 'tenup_autotweet_body', get_tweet_body( $post->ID ), $post );

	/**
	 * Allow filtering of post permalink.
	 *
	 * @param $permalink
	 */
	$url = apply_filters( 'tenup_autotweet_post_url', get_the_permalink( $post->ID ), $post );

	$url               = esc_url( $url );
	$body_max_length   = 275 - strlen( $url ); // 275 instead of 280 because of the space between body and URL and the ellipsis.
	$tweet_body        = sanitize_text_field( $tweet_body );
	$tweet_body_length = strlen( $tweet_body );
	$ellipsis          = ''; // Initialize as empty. Will be set if the tweet body is too long.

	while ( $body_max_length < $tweet_body_length ) {
		// We can't use `&hellip;` here or it will display encoded when tweeting.
		$ellipsis = ' ...';

		// If there are no spaces in the tweet for whatever reason, truncate regardless of where spaces fall.
		if ( false === strpos( $tweet_body, ' ' ) ) {
			$tweet_body = substr( $tweet_body, 0, $body_max_length );
			break;
		}

		// Otherwise, cut off the last word in the text until the tweet is short enough.
		$tweet_words = explode( ' ', $tweet_body );
		array_pop( $tweet_words );
		$tweet_body        = implode( ' ', $tweet_words );
		$tweet_body_length = strlen( $tweet_body );
	}

	return sprintf( '%s%s %s', $tweet_body, $ellipsis, $url );
}

/**
 * Return the Twitter created_at timestamp into local format.
 *
 * @param string $created_at The date the post was created.
 *
 * @return string
 */
function date_from_twitter( $created_at ) {

	$tz   = get_option( 'timezone_string' );
	$tz   = ( ! empty( $tz ) ) ? $tz : 'UTC';
	$date = new \DateTime( $created_at, new \DateTimeZone( 'UTC' ) );
	$date->setTimezone( new \DateTimeZone( $tz ) );

	return $date->format( 'Y-m-d @ g:iA' );
}

/**
 * Format a URL based on the Twitter ID.
 *
 * @param int $post_id The post id.
 *
 * @return string
 */
function link_from_twitter( $post_id ) {

	$handle = get_autotweet_settings( 'twitter_handle' );

	return esc_url( 'https://twitter.com/' . $handle . '/status/' . $post_id );
}

/**
 * Determine if a post has already been published based on the meta entry alone.
 *
 * @internal does NOT query the Twitter API.
 *
 * @param int $post_id The post id.
 *
 * @return bool
 */
function already_published( $post_id ) {

	$twitter_status = get_autotweet_meta( $post_id, TWITTER_STATUS_KEY );

	if ( ! empty( $twitter_status ) ) {
		return ( 'published' === $twitter_status['status'] ) ? true : false;
	}

}

/**
 * Helper for returning the appropriate tweet text body.
 *
 * @param int $post_id The post id.
 *
 * @return string
 */
function get_tweet_body( $post_id ) {

	$body = sanitize_text_field( get_the_title( $post_id ) );

	// Only if.
	$text_override = get_autotweet_meta( $post_id, TWEET_BODY_KEY );
	if ( ! empty( $text_override ) ) {
		$body = $text_override;
	}

	return $body;
}

/**
 * Wrapper for post_type_supports.
 *
 * @param int $post_id The post id to check.
 *
 * @return bool true if the current post type supports autotweet.
 */
function opted_into_autotweet( $post_id ) {
	return post_type_supports( get_post_type( (int) $post_id ), POST_TYPE_SUPPORT_FEATURE );
}
