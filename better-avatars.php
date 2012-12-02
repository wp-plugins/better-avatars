<?php
/*
Plugin Name: Dirty Suds - Better Avatars
Plugin URI: http://515comics.com
Description: Tries hard to pull in avatars from Facebook and Twitter
Author: Pat Hawks
Version: 1.0
Author URI: http://dirtysuds.com
*/

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
	if(preg_match('#http://www.facebook.com/people/[^/]+/([a-z0-9_\-]+)#i', $id_or_email->comment_author_url,$facebook))
		return 'https://graph.facebook.com/'.$facebook[1].'/picture';

	if(preg_match('#http://www.facebook.com/profile.php\?id\=([0-9]+)#i', $id_or_email->comment_author_url,$facebook))
		return 'https://graph.facebook.com/'.$facebook[1].'/picture';

/*
 * If we're fetching a profile picture from an email address,
 * we need to ask Facebook for an obsfuscated URL. Otherwise,
 * one could piece the users email address together from the
 * image url, and WordPress explicitly promises commenters
 * that their email address will remain private. No such
 * claims are made for URLs.
*/

	if(preg_match('#([a-z0-9\.\-\_]+)@facebook.com#i', $id_or_email->comment_author_email,$facebook)) {
		$facebook_icon_url = 'https://graph.facebook.com/'.$facebook[1].'/picture';
		$facebook_icon     = @get_headers($gmail_icon_url,1);
		if(!empty($facebook_icon['Location']) && !strstr($facebook_icon['Location'],'/static-ak/')) // Make sure the avatar returned isn't the blank man
			return $facebook_icon['Location'];
	}

	return;
}

function dirtysuds_better_avatars($avatar, $id_or_email, $size, $default, $alt) {

	if (!strstr($id_or_email->comment_author_url,'http://www.facebook.com/') && !strstr($id_or_email->comment_author_url,'http://twitter.com/') && !strstr($id_or_email->comment_author_email,'@gmail.com'))
		return $avatar;

	if(strstr($id_or_email->comment_author_url.$id_or_email->comment_author_email,'facebook.com')) {
		$new_avatar = dirtysuds_better_avatars_facebook($id_or_email);
		$class = 'facebook';
	}

	if(preg_match('#http://twitter.com/([a-z0-9_]+)#i', $id_or_email->comment_author_url,$twitter)) {
		$new_avatar = 'https://api.twitter.com/1/users/profile_image?screen_name='.$twitter[1];
		$class = 'twitter';
	}

	if(preg_match('#([^@]+)@gmail.com#i', $id_or_email->comment_author_email,$gmail)) {
		$new_avatar = dirtysuds_better_avatars_gmail($gmail[1]);
		if (!empty($new_avatar))
			$class = 'google';
	}

	if (empty($new_avatar))
		return $avatar;

	if (empty($id_or_email->comment_author_email)) // ad516503a11cd5ca435acc9bb6523536 == md5('unknown@gravatar.com')
		return "<img alt='{$alt}' src='{$new_avatar}' class='avatar avatar-{$size} photo {$class}' height='{$size}' width='{$size}' />";

	return strtr($avatar,array(
		'd='.get_option('avatar_default') => 'd='.urlencode($new_avatar),
		"avatar-{$size} photo" => "avatar-{$size} photo {$class}",
	));
}
add_filter('get_avatar', 'dirtysuds_better_avatars', 10, 5);