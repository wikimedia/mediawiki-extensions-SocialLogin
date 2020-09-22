<?php
class twitter_com implements SocialLoginPlugin {
	/**
	 * @inheritDoc
	 */
	public static function login( $code ) {
		return false;
	}

	/**
	 * @inheritDoc
	 */
	public static function check( $id, $access_token ) {
		return false;
	}

	/**
	 * @inheritDoc
	 */
	public static function loginUrl() {
		global $wgTwitterAppId;
		$host = $_SERVER["SERVER_NAME"];
		return "https://api.twitter.com/oauth/authorize?client_id=$wgTwitterAppId&display=popup&redirect_uri=http://$host/Special:SocialLogin?service=twitter.com&response_type=code";
	}
}
