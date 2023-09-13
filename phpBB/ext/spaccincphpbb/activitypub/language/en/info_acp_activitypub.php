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
	'ACP_SPACCINC_ACTIVITYPUB_SETDOMAIN_INFO' => 'The domain on which federation will be handled. You will never be able to change this later! (unless you know all implications)',
	'ACP_SPACCINC_ACTIVITYPUB_SETDOMAIN_CONFIRM' => 'I understand that the federation domain will not be able to be changed after having set it.'
]);
