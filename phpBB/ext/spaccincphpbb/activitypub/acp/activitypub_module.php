<?php

namespace spaccincphpbb\activitypub\acp;

class activitypub_module
{
	public $page_title;
	public $tpl_name;
	public $u_action;

	public function main($id, $mode)
	{
		global $phpbb_container;

		$acp_controller = $phpbb_container->get('spaccincphpbb.activitypub.controller.acp');
		$this->tpl_name = 'acp_spaccinc_activitypub_main';
		$this->page_title = 'ACP_SPACCINC_ACTIVITYPUB_TITLE';
		$acp_controller->set_page_url($this->u_action);
		$acp_controller->display_options();
	}
}
