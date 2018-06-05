<?php
if (!defined('MEDIAWIKI')) die('Not an entry point.');

$wgExtensionCredits['specialpage'][] = array(
        'name' => 'SocialLogin',
        'author' => 'Luft-on',
        'url' => 'https://www.mediawiki.org/wiki/Extension:SocialLogin',
        'descriptionmsg' => 'sl-desc',
        'version' => '0.11.0',
);

$dir = dirname(__FILE__) . '/';

$wgAutoloadClasses['SocialLogin'] = $dir . 'SocialLogin.body.php'; # Попросите MediaWiki загрузить тело основного файла.
$wgAutoloadClasses['SocialLoginPlugin'] = $dir . 'SocialLogin.body.php';

if ( ! isset( $wgSocialLoginServices ) ) {
	$wgSocialLoginServices = array();
}

foreach ($wgSocialLoginServices as $key => $value) {
	$name = str_replace('.', '_', $key);
	$wgAutoloadClasses[$name] = $dir . "/plugins/$key.php";
}

$wgMessagesDirs['SocialLogin'] = __DIR__ . '/i18n';
$wgExtensionMessagesFiles['SocialLoginAlias'] = $dir . 'SocialLogin.alias.php';
$wgSpecialPages['SocialLogin'] = 'SocialLogin'; # Сообщите MediaWiki о Вашей новой спецстранице.
