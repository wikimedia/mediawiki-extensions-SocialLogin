<?php
class google_com implements SocialLoginPlugin {
	/**
	 * @inheritDoc
	 */
	public static function login( $code ) {
		global $wgGoogleSecret, $wgGoogleAppId;
		$host = $_SERVER["SERVER_NAME"];
		$r = SLgetContents( "https://accounts.google.com/o/oauth2/token", [
			"redirect_uri" => "http://$host/Special:SocialLogin?service=google.com",
			"client_id" => $wgGoogleAppId,
			"client_secret" => $wgGoogleSecret,
			"grant_type" => "authorization_code",
			"code" => $code
		] );
		$response = json_decode( $r );
		if ( !isset( $response->access_token ) ) {
			return false;
		}
		$access_token = $response->access_token;
		$r = SLgetContents( "https://www.googleapis.com/oauth2/v1/userinfo?access_token=$access_token" );
		$response = json_decode( $r );
		$id = $response->id;
		$e = explode( "@", $response->email );
		$e = $e[0];
		$name = SocialLogin::generateName( [ $e, $response->family_name . " " . $response->given_name ] );

		return [
			"id" => $id,
			"service" => "google.com",
			"profile" => "$id@google.com",
			"name" => $name,
			"email" => $response->email,
			"realname" => $response->family_name . " " . $response->given_name,
			"access_token" => $access_token
		];
	}

	/**
	 * @inheritDoc
	 */
	public static function check( $id, $access_token ) {
		$r = SLgetContents( "https://www.googleapis.com/oauth2/v1/userinfo?access_token=$access_token" );
		$response = json_decode( $r );
		if ( !isset( $response->id ) || $response->id != $id ) {
			return false;
		} else {
			return [
				"id" => $id,
				"service" => "google.com",
				"profile" => "$id@google.com",
				"realname" => $response->family_name . " " . $response->given_name,
				"access_token" => $access_token
			];
		}
	}

	/**
	 * @inheritDoc
	 */
	public static function loginUrl() {
		global $wgGoogleAppId;
		$host = $_SERVER["SERVER_NAME"];
		return 'https://accounts.google.com/o/oauth2/auth?client_id=' . $wgGoogleAppId .
			'&scope=https%3A%2F%2Fwww.googleapis.com%2Fauth%2Fuserinfo.email+https%3A%2F%2Fwww.googleapis.com%2Fauth%2Fuserinfo.profile' .
			'&display=popup&redirect_uri=http://' . $host .
			'/Special:SocialLogin?service=google.com&response_type=code';
	}
}
