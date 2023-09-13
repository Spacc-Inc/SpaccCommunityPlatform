<?php

namespace spaccincphpbb\activitypub\migrations;

class install_acp_module extends \phpbb\db\migration\migration
{
	public function effectively_installed()
	{
		return isset($this->config['spaccinc_activitypub_setfederation']);
	}

	public static function depends_on()
	{
		return ['\phpbb\db\migration\data\v320\v320'];
	}

	public function update_data()
	{
		return [
			['config.add', ['spaccinc_activitypub_setfederation', 0]],
			['config.add', ['spaccinc_activitypub_setdomain', '']],

			['module.add', [
				'acp',
				'ACP_CAT_DOT_MODS',
				'ACP_SPACCINC_ACTIVITYPUB_TITLE'
			]],
			['module.add', [
				'acp',
				'ACP_SPACCINC_ACTIVITYPUB_TITLE',
				[
					'module_basename'	=> '\spaccincphpbb\activitypub\acp\activitypub_module',
					'modes'				=> ['settings'],
				],
			]],
		];
	}
}
