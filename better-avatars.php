<?php
/*
Plugin Name: Better Avatars
Plugin URI: https://github.com/pathawks/Better-WP-Avatars
Description: Tries hard to pull in avatars from Facebook and Twitter
Author: Pat Hawks
Version: 1.0
Author URI: http://dirtysuds.com
*/

function dirtysuds_better_avatars_tumblr($url) {
	$url = parse_url($url, PHP_URL_HOST);
	if(preg_match('#[a-z0-9_\-]+\.tumblr\.com#i', $url))
		return 'http://api.tumblr.com/v2/blog/'.$url.'/avatar/';
	return;
}

function dirtysuds_better_avatars_blavatar($url) {
	$url = parse_url($url, PHP_URL_HOST);
	if(preg_match('#[a-z0-9_\-]+\.wordpress\.com#i', $url))
		return 'http://0.gravatar.com/blavatar/'.md5($url);
	return;
}

function dirtysuds_better_avatars_gmail($gmail) {
	$gravatar_url = 'http://www.gravatar.com/avatar/'.md5( strtolower( trim( $gmail.'@gmail.com' ))) .'?r='.get_option('avatar_rating').'&d=404';
	$gravatar = @get_headers($gravatar_url,1);

	if(preg_match("|200|", $gravatar[0])) // Don't bother checking for a Gmail avatar if there is a Gravatar set.
		return;

	$gmail_icon_url = 'https://profiles.google.com/s2/photos/profile/'.$gmail;
	$gmail_icon     = @get_headers($gmail_icon_url,1);
	return $gmail_icon['Location'];
}

function dirtysuds_better_avatars_facebook($id_or_email) {

	if( preg_match('#http://www.facebook.com/people/[^/]+/([a-z0-9_\-]+)#i', $id_or_email, $facebook ) )
		return 'https://graph.facebook.com/'.$facebook[1].'/picture?type=square';

	if( preg_match('#http://www.facebook.com/profile.php\?id\=([0-9]+)#i', $id_or_email, $facebook ) )
		return 'https://graph.facebook.com/'.$facebook[1].'/picture?type=square';

	if( preg_match('#http://www.facebook.com/([a-z0-9_\-\.]+)#i', $id_or_email, $facebook ) )
		return 'https://graph.facebook.com/'.$facebook[1].'/picture?type=square';

/*
 * If we're fetching a profile picture from an email address,
 * we need to ask Facebook for an obsfuscated URL. Otherwise,
 * one could piece the users email address together from the
 * image url, and WordPress explicitly promises commenters
 * that their email address will remain private. No such
 * claims are made for URLs.
*/

	if(preg_match('#([a-z0-9\.\-\_]+)@facebook.com#i', $id_or_email,$facebook)) {
		$facebook_icon_url = 'https://graph.facebook.com/'.$facebook[1].'/picture?type=square';
		$facebook_icon     = @get_headers($gmail_icon_url,1);
		if(!empty($facebook_icon['Location']) && !strstr($facebook_icon['Location'],'/static-ak/')) // Make sure the avatar returned isn't the blank man
			return $facebook_icon['Location'];
	}

	return;
}

function dirtysuds_better_avatars($avatar, $id_or_email, $size, $default, $alt) {

	$email = NULL;
	if ( is_email( $id_or_email ) ) {
		$email = $id_or_email;
	} elseif ( strlen( $id_or_email->comment_author_email ) ) {
		$email = $id_or_email->comment_author_email;
	} elseif ( is_numeric($id_or_email) ) {
		$id = (int) $id_or_email;
		$email = get_user_meta( $id, 'user_email', TRUE);
	} elseif ( !empty( $id_or_email->user_id ) ) {
		$email = get_user_meta( $id_or_email->user_id, 'user_email', TRUE);
	}
	$email = strtolower( $email );
	
	if ( empty( $email ) && empty( $id_or_email->comment_author_url ) )
		return $avatar;

	$user_id = is_email( $email ) ? md5( $email ) : md5( $id_or_email->comment_author_url );

	$transient = 'DS_avatar' . $size . $user_id;
	if ( $cached_avatar = get_site_transient( $transient ) )
		return $cached_avatar;

	if( strstr( $id_or_email->comment_author_url, 'facebook.com' ) ) {
		$new_avatar = dirtysuds_better_avatars_facebook( $id_or_email->comment_author_url );
		$class = 'facebook';
	} else if( strstr( $email, 'facebook.com' ) ) {
		$new_avatar = dirtysuds_better_avatars_facebook( $email );
	} else if(preg_match('#http://twitter.com/([a-z0-9_]+)#i', $id_or_email->comment_author_url,$twitter)) {
		$new_avatar = 'https://api.twitter.com/1/users/profile_image?screen_name='.$twitter[1];
		$class = 'twitter';
	} else if(preg_match('#([^@]+)@gmail.com#i', $email, $gmail)) {
		$new_avatar = dirtysuds_better_avatars_gmail($gmail[1]);
		$class = 'google';
	} else if(strstr($id_or_email->comment_author_url,'tumblr.com')) {
		$new_avatar = dirtysuds_better_avatars_tumblr($id_or_email->comment_author_url).$size;
		$class = 'tumblr';
	} else if( strstr( $id_or_email->comment_author_url, 'wordpress.com' ) ) {
		$new_avatar = dirtysuds_better_avatars_blavatar( $id_or_email->comment_author_url );
		$class = 'blavatar';
	} else {
		set_site_transient( $transient, $avatar, HOUR_IN_SECONDS );
		return $avatar;
	}

	if (empty($new_avatar)) {
		set_site_transient( $transient, $avatar, HOUR_IN_SECONDS );
		return $avatar;
	}

	if ( empty( $email ) ) {
		$cached_avatar = "<img alt='{$alt}' src='{$new_avatar}' class='avatar avatar-{$size} photo {$class}' height='{$size}' width='{$size}' />";
		set_site_transient( $transient, $cached_avatar, HOUR_IN_SECONDS );
		return $cached_avatar;
	}
		
	$cached_avatar = strtr($avatar,array(
		'd='.get_option('avatar_default') => 'd='.urlencode($new_avatar),
		"avatar-{$size} photo" => "avatar-{$size} photo {$class}",
	));
	set_site_transient( $transient, $cached_avatar, HOUR_IN_SECONDS );
	return $cached_avatar;
}
add_filter('get_avatar', 'dirtysuds_better_avatars', 10, 5);