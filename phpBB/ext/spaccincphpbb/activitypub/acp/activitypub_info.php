<?php

namespace spaccincphpbb\activitypub\acp;

class activitypub_info
{
	public function module()
	{
		return [
			'filename'	=> '\spaccincphpbb\activitypub\acp\activitypub_module',
			'title'		=> 'ACP_SPACCINC_ACTIVITYPUB_TITLE',
			'modes'		=> [
				'settings'	=> [
					'title'	=> 'ACP_SPACCINC_ACTIVITYPUB_TITLE',
					'auth'	=> 'ext_spaccincphpbb/activitypub && acl_a_board',
					'cat'	=> ['ACP_SPACCINC_ACTIVITYPUB_TITLE'],
				],
			],
		];
	}
}
