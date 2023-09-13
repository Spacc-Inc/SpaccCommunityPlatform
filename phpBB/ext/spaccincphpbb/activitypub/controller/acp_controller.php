<?php

namespace spaccincphpbb\activitypub\controller;

class acp_controller
{
	protected $config;
	protected $language;
	protected $log;
	protected $request;
	protected $template;
	protected $user;
	protected $u_action;

	public function __construct(
		\phpbb\config\config     $config,
		\phpbb\language\language $language,
		\phpbb\log\log           $log,
		\phpbb\request\request   $request,
		\phpbb\template\template $template,
		\phpbb\user              $user,
	){
		$this->config   = $config;
		$this->language = $language;
		$this->log      = $log;
		$this->request  = $request;
		$this->template = $template;
		$this->user     = $user;
	}

	public function display_options()
	{
		$this->language->add_lang('common', 'spaccincphpbb/activitypub');
		add_form_key('spaccincphpbb_activitypub_acp');
		$errors = [];

		if ($this->request->is_set_post('submit'))
		{
			if (!check_form_key('spaccincphpbb_activitypub_acp'))
			{
				$errors[] = $this->language->lang('FORM_INVALID');
			}

			if (empty($errors))
			{
				$this->config->set('spaccinc_activitypub_setfederation', $this->request->variable('spaccinc_activitypub_setfederation', 0));
				//$this->config->set('spaccinc_activitypub_setfederation', $this->request->variable('spaccinc_activitypub_setdomain', ''));
				$this->log->add('admin', $this->user->data['user_id'], $this->user->ip, 'LOG_ACP_SPACCINC_ACTIVITYPUB_SETTINGS');
				trigger_error($this->language->lang('CONFIG_UPDATED') . adm_back_link($this->u_action));
			}
		}

		$s_errors = !empty($errors);

		$this->template->assign_vars([
			'S_ERROR'   => $s_errors,
			'ERROR_MSG' => ($s_errors ? implode('<br />', $errors) : ''),
			'U_ACTION'  => $this->u_action,
			'SYS_SERVER_NAME' => $this->config['server_name'],
			'SPACCINC_ACTIVITYPUB_SETFEDERATION' => (bool)$this->config['spaccinc_activitypub_setfederation'],
			'SPACCINC_ACTIVITYPUB_SETDOMAIN'     =>       $this->config['spaccinc_activitypub_setdomain'],
		]);
	}

	public function set_page_url($u_action)
	{
		$this->u_action = $u_action;
	}
}
