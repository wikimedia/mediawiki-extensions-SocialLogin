<?php
interface SocialLoginPlugin {
	/**
	 * @param string $code
	 * @return array|false
	 */
	public static function login( $code );

	/**
	 * @param int $id
	 * @param string $access_token
	 * @return array|false
	 */
	public static function check( $id, $access_token );

	/**
	 * @return string
	 */
	public static function loginUrl();
}

/**
 * @param string $url
 * @param array|string|false $data
 * @return string|false
 */
function SLgetContents( $url, $data = false ) {
	$ch = curl_init();
	curl_setopt( $ch, CURLOPT_URL, $url );
	curl_setopt( $ch, CURLOPT_HEADER, 0 );
	curl_setopt( $ch, CURLOPT_POST, $data ? 1 : 0 );
	if ( $data ) {
		curl_setopt( $ch, CURLOPT_POSTFIELDS, $data );
	}
	curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
	curl_setopt( $ch, CURLOPT_TIMEOUT, 10 );
	$output = curl_exec( $ch );
	curl_close( $ch );
	return $output;
}

class SocialLogin extends SpecialPage {
	function __construct() {
		global $wgHooks;
		parent::__construct( 'SocialLogin' );
		$wgHooks['UserLoadAfterLoadFromSession'][] = $this;
	}

	/**
	 * @param string $url
	 * @param array|string|false $data
	 * @return string|false
	 */
	static function getContents( $url, $data = false ) {
		$ch = curl_init();
		curl_setopt( $ch, CURLOPT_URL, $url );
		curl_setopt( $ch, CURLOPT_HEADER, 0 );
		curl_setopt( $ch, CURLOPT_POST, $data ? 1 : 0 );
		if ( $data ) {
			curl_setopt( $ch, CURLOPT_POSTFIELDS, $data );
		}
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
		curl_setopt( $ch, CURLOPT_TIMEOUT, 10 );
		$output = curl_exec( $ch );
		curl_close( $ch );
		return $output;
	}

	/**
	 * @param string $str
	 * @return string
	 */
	static function translit( $str ) {
		$tr = [
			"А" => "A","Б" => "B","В" => "V","Г" => "G",
			"Д" => "D","Е" => "E","Ж" => "J","З" => "Z","И" => "I",
			"Й" => "Y","К" => "K","Л" => "L","М" => "M","Н" => "N",
			"О" => "O","П" => "P","Р" => "R","С" => "S","Т" => "T",
			"У" => "U","Ф" => "F","Х" => "H","Ц" => "TS","Ч" => "CH",
			"Ш" => "SH","Щ" => "SCH","Ъ" => "","Ы" => "YI","Ь" => "",
			"Э" => "E","Ю" => "YU","Я" => "YA","а" => "a","б" => "b",
			"в" => "v","г" => "g","д" => "d","е" => "e","ж" => "j",
			"з" => "z","и" => "i","й" => "y","к" => "k","л" => "l",
			"м" => "m","н" => "n","о" => "o","п" => "p","р" => "r",
			"с" => "s","т" => "t","у" => "u","ф" => "f","х" => "h",
			"ц" => "ts","ч" => "ch","ш" => "sh","щ" => "sch","ъ" => "y",
			"ы" => "yi","ь" => "","э" => "e","ю" => "yu","я" => "ya"
		];
		return strtr( $str, $tr );
	}

	/**
	 * @param string $name
	 * @return string
	 */
	static function processName( $name ) {
		global $wgContLang;
		$name = $wgContLang->ucfirst( $name );
		$name = self::translit( $name );
		$name = strtolower( $name );
		$name = str_replace( " ", "_", $name );
		$name = preg_replace( "/[^a-zA-Z0-9_]/i", "", $name );
		$name = ltrim( $name, "0123456789_" );
		return $name;
	}

	/**
	 * @param string $name
	 * @return bool
	 */
	static function userExist( $name ) {
		$user = User::newFromName( $name );
		return $user && $user->getId();
	}

	/**
	 * @param string $email
	 * @return bool
	 */
	function emailExist( $email ) {
		$dbr = wfGetDB( DB_MASTER );
		$res = $dbr->selectRow( 'user', [ 'user_id' ], [ 'user_email' => $email ], __METHOD__ );
		return isset( $res->user_id ) && $res->user_id;
	}

	/**
	 * @param string[] $names
	 * @return string
	 */
	static function generateName( $names ) {
		$possibles = [];
		foreach ( $names as $name ) {
			$name = self::processName( $name );
			if ( !$name ) {
				continue;
			}
			$possibles[] = $name;
			if ( !self::userExist( $name ) ) {
				return $name;
			}
		}
		foreach ( $possibles as $possible ) {
			$i = 1;
			while ( self::userExist( $possible . "_$i" ) ) {
				$i++;
			}
			return $possible . "_$i";
		}

		$dbr = wfGetDB( DB_MASTER );
		$res = $dbr->selectRow( 'sociallogin', [ 'MAX(id) as max' ], [], __METHOD__ );
		$maxId = $res->max ? $res->max + 1 : 1;
		$result = "user_$maxId";
		return $result;
	}

	/**
	 * @param User $user
	 * @return bool
	 */
	function onUserLoadAfterLoadFromSession( $user ) {
		global $wgRequest, $wgOut, $wgContLang, $wgSocialLoginServices, $wgSocialLoginAddForms;

		$wgRequest->getSession()->persist();
		$action = $wgRequest->getText( 'action', 'auth' );
		switch ( $action ) {
			case "auth":
				$wgOut->addHTML( "<table style='width: 100%'><tr>" );
				$w = 100 / ( count( $wgSocialLoginServices ) || 1 );
				$accounts = [];
				foreach ( $wgSocialLoginServices as $key => $name ) {
					$accounts[$key] = '';
					$n = explode( '.', $key );
					$wgOut->addHTML( "<td style='width: $w%'><div style='width: 95%' class='slbutton " . $n[0] . "'>$name</div></td>" );
				}
				$wgOut->addHTML( "</tr>" );
				if ( $user->isLoggedIn() ) {
					$wgOut->addHTML( "<tr>" );
					$dbr = wfGetDB( DB_MASTER );
					$res = $dbr->select( 'sociallogin', [ 'profile', 'full_name' ], [ 'user_id' => $user->getId() ], __METHOD__ );
					foreach ( $res as $row ) {
						$s = explode( '@', $row->profile );
						$s = $s[1];
						$accounts[$s] .= "<p id='" .
							preg_replace( "/[@\.]/i", "_", $row->profile ) . "'>" .
							$row->full_name .
							" <a href=\"javascript:unlink('" . $row->profile . "')\">(" .
							$this->msg( 'sl-unlink' )->escaped() . ")</a></p>";
					}
					foreach ( $wgSocialLoginServices as $key => $name ) {
						$wgOut->addHTML( "<td>" . $accounts[$key] . "</td>" );
					}
					$wgOut->addHTML( "</tr>" );
					$wgOut->addHTML( "</table>" );
				} else {
					$wgOut->addHTML( "</table>" );
				}
				$wgOut->addHeadItem( 'Authorization', "<script type='text/javascript' src='/extensions/SocialLogin/auth.js'></script>" );
				$scripts = "$(function() {";
				foreach ( $wgSocialLoginServices as $key => $name ) {
					$s = explode( '.', $key );
					$s = $s[0];
					$scripts .= "
						$('.$s').click(function() {
							login('" . call_user_func( [ str_replace( ".", "_", $key ), "loginUrl" ] ) . "', function(code) {
								tryLogin({service: '$key', code: code}, function(response) {
									if (response == 'yes') document.location.href = '/';
									else hacking();
								});
							});
						});";
				}
				$scripts .= "});";
				$wgOut->addHeadItem( 'Login scripts', "<script type='text/javascript'>$scripts</script>" );
				if ( $wgSocialLoginAddForms && !$user->isLoggedIn() ) {
					$wgOut->addHTML( $this->msg( 'sl-login-register' )->escaped() );
				}
				break;
			case "signin":
				$name = $wgContLang->ucfirst( $wgRequest->getText( 'name' ) );
				$pass = $wgRequest->getText( 'pass' );
				$error = "";
				if ( !User::isValidUserName( $name ) ) {
					$error .= "<li>" . $this->msg( 'sl-invalid-name', $name )->escaped() . "</li>";
				}
				if ( !self::userExist( $name ) ) {
					$error .= "<li>" . $this->msg( 'sl-user-not-exist', $name )->escaped() . "</li>";
				}
				$newUser = User::newFromName( $name );
				if ( !$newUser->isValidPassword( $pass ) ) {
					$error .= "<li>" . $this->msg( 'sl-invalid-password' )->escaped() . "</li>";
				}
				if ( $error ) {
					$wgOut->addHTML( "<ul class='error'>$error</ul>" );
				} else {
					$user->setId( $newUser->getId() );
					$user->loadFromId();
					$user->setCookies();
					$user->saveSettings();
					Hooks::run( 'UserLoginComplete', [ &$user, $this ] );
					$wgOut->addHTML( $this->msg( 'sl-login-success' )->escaped() );
				}
				break;
			case "signup":
				$name = $wgContLang->ucfirst( $wgRequest->getText( 'name' ) );
				$realname = $wgRequest->getText( 'realname' );
				$email = $wgRequest->getText( 'email' );
				$pass1 = $wgRequest->getText( 'pass' );
				$pass2 = $wgRequest->getText( 'pass_confirm' );
				$error = "";
				if ( !User::isValidUserName( $name ) ) {
					$error .= "<li>" . $this->msg( 'sl-invalid-name', $name )->escaped() . "</li>";
				}
				if ( self::userExist( $name ) ) {
					$error .= "<li>" . $this->msg( 'sl-user-exist', $name )->escaped() . "</li>";
				}
				if ( !Sanitizer::validateEmail( $email ) ) {
					$error .= "<li>" . $this->msg( 'sl-invalid-email', $email )->escaped() . "</li>";
				}
				if ( $this->emailExist( $email ) ) {
					$error .= "<li>" . $this->msg( 'sl-email-exist', $name )->escaped() . "</li>";
				}
				// Note: Добавить проверку на валидность пароля
				if ( !$pass1 ) {
					$error .= "<li>" . $this->msg( 'sl-invalid-password' )->escaped() . "</li>";
				}
				if ( $pass1 != $pass2 ) {
					$error .= "<li>" . $this->msg( 'sl-passwords-not-equal' )->escaped() . "</li>";
				}
				if ( $error ) {
					$wgOut->addHTML( "<ul class='error'>$error</ul>" );
				} else {
					$newUser = User::createNew( $name, [
						'email' => $email,
						'real_name' => $realname,
						'token' => MWCryptRand::generateHex( 32 )
					] );
					$newUser->setInternalPassword( $pass1 );
					$newUser->saveSettings();
					$user->setId( $newUser->getId() );
					$user->loadFromId();
					$user->setCookies();
					$user->saveSettings();
					Hooks::run( 'UserLoginComplete', [ &$user, $this ] );
					$wgOut->addHTML( $this->msg( 'sl-login-success' )->escaped() );
				}
				break;
			case "login":
				$profile = $wgRequest->getText( 'profile' );
				$service = $wgRequest->getText( 'service' );
				$code = $wgRequest->getText( 'code' );
				$auth = call_user_func( [ str_replace( ".", "_", $service ), "login" ], $code );
				if ( !$auth ) {
					return true;
				}
				$dbr = wfGetDB( DB_MASTER );
				$res = $dbr->selectRow( 'sociallogin', [ 'user_id' ], [ 'profile' => $auth['profile'] ], __METHOD__ );
				$user_id = $res ? ( $res->user_id ? $res->user_id : false ) : false;
				if ( $user_id ) {
					$user->setID( $user_id );
					$user->loadFromId();
					$user->setCookies();
					$user->saveSettings();
					Hooks::run( 'UserLoginComplete', [ &$user, $this ] );
					$wgOut->addHTML( $this->msg( 'sl-login-success' )->escaped() );
				} else {
					if ( $user->isLoggedIn() ) {
						$dbr = wfGetDB( DB_MASTER );
						$res = $dbr->insert( 'sociallogin', [
							"user_id" => $user->getId(),
							"profile" => $auth["profile"],
							"full_name" => $auth["realname"]
						] );
						$wgOut->addHTML( $this->msg( 'sl-account-connected', $auth["realname"], $wgSocialLoginServices[$service] )->escaped() );
					} else {
						$wgOut->addHTML( $this->msg( 'sl-sign-forms', $auth["access_token"], $auth["service"], $auth["id"], $auth["name"], $auth["realname"], $auth["email"] )->escaped() );
					}
				}
				break;
			case "create":
				$access_token = $wgRequest->getText( 'access_token' );
				$service = $wgRequest->getText( 'service' );
				$id = $wgRequest->getText( 'id' );
				$name = $wgContLang->ucfirst( $wgRequest->getText( 'name' ) );
				$realname = $wgRequest->getText( 'realname' );
				$email = $wgRequest->getText( 'email' );
				$pass1 = $wgRequest->getText( 'pass' );
				$pass2 = $wgRequest->getText( 'pass_confirm' );
				$auth = call_user_func( [ str_replace( ".", "_", $service ), "check" ], $id, $access_token );
				if ( !$auth ) {
					$wgOut->addHTML( $this->msg( 'sl-hacking' )->escaped() );
				} else {
					$error = "";
					if ( !$access_token ) {
						$error .= "<li>" . $this->msg( 'sl-missing-param', 'access_token' )->escaped() . "</li>";
					}
					if ( !$service ) {
						$error .= "<li>" . $this->msg( 'sl-missing-param', 'service' )->escaped() . "</li>";
					}
					if ( !$id ) {
						$error .= "<li>" . $this->msg( 'sl-missing-param', 'id' )->escaped() . "</li>";
					}
					if ( !User::isValidUserName( $name ) ) {
						$error .= "<li>" . $this->msg( 'sl-invalid-name', $name )->escaped() . "</li>";
					}
					if ( self::userExist( $name ) ) {
						$error .= "<li>" . $this->msg( 'sl-user-exist', $name )->escaped() . "</li>";
					}
					if ( !Sanitizer::validateEmail( $email ) ) {
						$error .= "<li>" . $this->msg( 'sl-invalid-email', $email )->escaped() . "</li>";
					}
					if ( $this->emailExist( $email ) ) {
						$error .= "<li>" . $this->msg( 'sl-email-exist', $name )->escaped() . "</li>";
					}
					// Note: Добавить проверку на валидность пароля
					if ( !$pass1 ) {
						$error .= "<li>" . $this->msg( 'sl-invalid-password' )->escaped() . "</li>";
					}
					if ( $pass1 != $pass2 ) {
						$error .= "<li>" . $this->msg( 'sl-passwords-not-equal' )->escaped() . "</li>";
					}
					if ( $error ) {
						$wgOut->addHTML( "<ul class='error'>$error</ul>" );
						$wgOut->addHTML( $this->msg( 'sl-sign-forms', $access_token, $service, $id, $name, $realname, $email )->escaped() );
					} else {
						$newUser = User::createNew( $name, [
							'email' => $email,
							'real_name' => $realname,
							'token' => MWCryptRand::generateHex( 32 )
						] );
						$newUser->setInternalPassword( $pass1 );
						$newUser->saveSettings();
						$user->setId( $newUser->getId() );
						$user->loadFromId();
						$user->setCookies();
						$user->saveSettings();
						Hooks::run( 'UserLoginComplete', [ &$user, $this ] );
						$dbr = wfGetDB( DB_MASTER );
						$res = $dbr->insert( 'sociallogin', [
							"user_id" => $newUser->getId(),
							"profile" => $auth["profile"],
							"full_name" => $auth["realname"]
						] );
						$wgOut->addHTML( $this->msg( 'sl-login-success' )->escaped() );
					}
				}
				break;
			case "connect":
				$access_token = $wgRequest->getText( 'access_token' );
				$service = $wgRequest->getText( 'service' );
				$id = $wgRequest->getText( 'id' );
				$name = $wgContLang->ucfirst( $wgRequest->getText( 'name' ) );
				$pass = $wgRequest->getText( 'pass' );
				$auth = call_user_func( [ str_replace( ".", "_", $service ), "check" ], $id, $access_token );
				if ( !$auth ) {
					$wgOut->addHTML( $this->msg( 'sl-hacking' )->escaped() );
				} else {
					$error = "";
					if ( !$access_token ) {
						$error .= "<li>" . $this->msg( 'sl-missing-param', 'access_token' )->escaped() . "</li>";
					}
					if ( !$service ) {
						$error .= "<li>" . $this->msg( 'sl-missing-param', 'service' )->escaped() . "</li>";
					}
					if ( !$id ) {
						$error .= "<li>" . $this->msg( 'sl-missing-param', 'id' )->escaped() . "</li>";
					}
					if ( !User::isValidUserName( $name ) ) {
						$error .= "<li>" . $this->msg( 'sl-invalid-name', $name )->escaped() . "</li>";
					}
					if ( !self::userExist( $name ) ) {
						$error .= "<li>" . $this->msg( 'sl-user-not-exist', $name )->escaped() . "</li>";
					}
					$newUser = User::newFromName( $name );
					if ( !$newUser->isValidPassword( $pass ) ) {
						$error .= "<li>" . $this->msg( 'sl-invalid-password' )->escaped() . "</li>";
					}
					if ( $error ) {
						$wgOut->addHTML( "<ul class='error'>$error</ul>" );
						$wgOut->addHTML( $this->msg( 'sl-sign-forms', $access_token, $service, $id, $name, '', '' )->escaped() );
					} else {
						$user->setId( $newUser->getId() );
						$user->loadFromId();
						$user->setCookies();
						$user->saveSettings();
						Hooks::run( 'UserLoginComplete', [ &$user, $this ] );
						$dbr = wfGetDB( DB_MASTER );
						$res = $dbr->insert( 'sociallogin', [
							"user_id" => $newUser->getId(),
							"profile" => $auth["profile"],
							"full_name" => $auth["realname"]
						] );
						$wgOut->addHTML( $this->msg( 'sl-account-connected', $auth["realname"], $wgSocialLoginServices[$service] )->escaped() );
					}
				}
				break;
			case 'unlink':
				if ( !$user->isLoggedIn() ) {
					exit( 'no' );
				} else {
					$profile = $wgRequest->getText( 'profile' );
					$dbr = wfGetDB( DB_MASTER );
					$res = $dbr->selectRow( 'sociallogin', [ 'user_id' ], [ 'profile' => $profile ], __METHOD__ );
					if ( $res && $res->user_id && $user->getId() == $res->user_id ) {
						$dbr = wfGetDB( DB_MASTER );
						$res = $dbr->delete( 'sociallogin', [ 'profile' => $profile ] );
						$dbr->commit();
						exit( 'yes' );
					} else {
						exit( 'no' );
					}
				}
				break;
		}
		return true;
	}

	/**
	 * @inheritDoc
	 */
	function execute( $par ) {
		global $wgOut;
		$wgOut->addHeadItem( 'Zocial Styles', "<link type='text/css' href='/extensions/SocialLogin/css/style.css' rel='stylesheet' />" );
		$this->setHeaders();
	}
}
