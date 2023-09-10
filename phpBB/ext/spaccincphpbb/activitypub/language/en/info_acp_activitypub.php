<?php

if (!defined('IN_PHPBB'))
{
	exit;
}

if (empty($lang) || !is_array($lang))
{
	$lang = [];
}

$lang = array_merge($lang, [
	'ACP_SPACCINC_ACTIVITYPUB_TITLE' => 'ActivityPub for phpBB Module',
	'ACP_SPACCINC_ACTIVITYPUB_SETFEDERATION' => 'Enable ActivityPub federation for this board',
	'ACP_SPACCINC_ACTIVITYPUB_SETDOMAIN' => 'Full domain name to use for federation',
	'ACP_SPACCINC_ACTIVITYPUB_SETDOMAIN_INFO' => 'The domain on which federation will be handled. NEVER change this after enabling federation, unless you know the implications.',
]);
