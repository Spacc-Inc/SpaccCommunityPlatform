<?php

namespace spaccinc\activitypub\controller;

use ErrorException;
use Symfony\Component\HttpFoundation\Response;

class activitypub_controller
{
	/** @var \phpbb\request\request */
	protected $request;

	/** @var \phpbb\db\driver\driver_interface */
	protected $db;

	// Quick way to force any PHP warning to be an error that halts execution and makes nothing return,
	// so that we don't need complex error handling in case of bad queries
	public function exception_error_handler($severity, $message, $file, $line)
	{
		if ($message !== 'Only variables should be passed by reference')
		{
			throw new ErrorException($message, 0, $severity, $file, $line);
		}
	}

	public function __construct(
		\phpbb\request\request            $request,
		\phpbb\db\driver\driver_interface $db,
	){
		$this->request = $request;
		$this->db      = $db;

		$this->site_name = $this->get_sql_row('
			SELECT config_value
			FROM ' . CONFIG_TABLE . "
			WHERE config_name = 'sitename'
		")['config_value'];

		$this->server_name = $this->get_sql_row('
			SELECT config_value
			FROM ' . CONFIG_TABLE . "
			WHERE config_name = 'server_name'
		")['config_value'];

		$this->server_addr = ((!empty($this->request->server('HTTPS')) && (strtolower($this->request->server('HTTPS')) == 'on' || $this->request->server('HTTPS') == '1')) ? 'https://' : 'http://') . $this->server_name;
	}

	//private function server_name()
	//{
	//	// <https://area51.phpbb.com/docs/dev/3.3.x/request/request.html#server>
	//	//return strtolower(htmlspecialchars_decode($this->request->header('Host', $this->request->server('SERVER_NAME'))));
	//}

	//private function server_addr()
	//{
	//	// <https://stackoverflow.com/a/56373183>
	//	$proto = (!empty($this->request->server('HTTPS')) && (strtolower($this->request->server('HTTPS')) == 'on' || $this->request->server('HTTPS') == '1')) ? 'https://' : 'http://';
	//	return $proto . $this->server_name;
	//}

	private function get_sql_row($sql)
	{
		$result = $this->db->sql_query($sql);
		$data = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);
		return $data;
	}

	public function nodeinfo_known()
	{
		set_error_handler([$this, 'exception_error_handler']);
		$server_addr = $this->server_addr;
		$response = new Response(json_encode([
			'links' => [[
				'rel' => 'http://nodeinfo.diaspora.software/ns/schema/2.0',
				'href' => $server_addr . '/activitypub?&mode=nodeinfo&version=2.0',
			]],
		]), 200);
		$response->headers->set('Content-Type', 'application/jrd+json; charset=utf-8');
		restore_error_handler();
		return $response;
	}

	public function webfinger()
	{
		set_error_handler([$this, 'exception_error_handler']);
		$server_name = $this->server_name;
		$server_addr = $this->server_addr;
		$resource = $this->request->variable('resource', '');

		$subject = '';
		$href = [];

		//if ($resource == '')
		//{
		//	return new JsonResponse([], 500);
		//}

		if (str_starts_with($resource, 'acct:'))
		{
			$tokens = explode('@', substr($resource, strlen('acct:')));
			if ($tokens[0] === '')
			{
				array_shift($tokens);
			}
			$name = strtolower($tokens[0]);
			$subject = 'acct:' . $name . '@' . $server_name;

			$data = $this->get_sql_row('
				SELECT user_id
				FROM ' . USERS_TABLE . "
				WHERE username_clean = '" . $this->db->sql_escape($name) . "'
			");

			$href['self'] = '&mode=user&u=' . $data['user_id'];
			$href['profile-page'] = '/memberlist.php?mode=viewprofile&u=' . $data['user_id'];
		}

		$response = new Response(json_encode([
			'subject' => $subject,
			'links' => [[
				'rel' => 'self',
				'type' => 'application/activity+json',
				'href' => $server_addr . '/activitypub?' . $href['self'],
			], [
				'rel' => 'http://webfinger.net/rel/profile-page',
				'type' => 'text/html',
				'href' => $server_addr . $href['profile-page'],
			]],
		]), 200);
		$response->headers->set('Content-Type', 'application/jrd+json; charset=utf-8');
		restore_error_handler();
		return $response;
	}

	public function activitypub()
	{
		set_error_handler([$this, 'exception_error_handler']);
		$server_addr = $this->server_addr;
		$uri_id = htmlspecialchars_decode($server_addr . $this->request->server('REQUEST_URI'));
		$mode = $this->request->variable('mode', '');

		$response = [];

		switch ($mode)
		{
			case 'nodeinfo':
				$version = $this->request->variable('version', '');
				switch ($version)
				{
					//case '1.0':
					//	// ...
					//break;

					case '2.0':
						$response = [
							'version' => '2.0',
							'software' => [
								'name' => 'phpBB ActivityPub',
								'version' => '0.0.1',
							],
							'protocols' => [
								'activitypub',
							],
							'services' => [
								'inbound' => [],
								'outbound' => [],
							],
							//'usage' => [
							//	'users' => [
							//		'total' => ,
							//	],
							//	'localPosts' => ,
							//],
							//'openRegistrations' => true,
							'metadata' => [
								'nodeName' => $this->site_name,
							],
						];
					break;
				}
			break;

			case 'user':
				$u = $this->request->variable('u', '');

				$data = $this->get_sql_row('
					SELECT *
					FROM ' . USERS_TABLE . "
					WHERE user_id = '" . $this->db->sql_escape($u) . "'
				");

				$icon_ext = end(explode('.', $data['user_avatar']));

//$config = [
//	"private_key_bits" => 2048,
//	"private_key_type" => OPENSSL_KEYTYPE_RSA,
//];
//
//$keypair = openssl_pkey_new($config);
//openssl_pkey_export($keypair, $private_key);
//
//$public_key = openssl_pkey_get_details($keypair);
//$public_key = $public_key["key"];

				$response = [
					'@context' => [
						'https://www.w3.org/ns/activitystreams',
						'https://w3id.org/security/v1',
					],
					'id' => $uri_id,
					'inbox' => $server_addr . '/activitypub?&mode=inbox&u=' . $u,
					'outbox' => $server_addr . '/activitypub?&mode=outbox&u=' . $u,
					'endpoints' => [
						'sharedInbox' => $server_addr . '/activitypub&mode=inbox',
					],
					'type' => 'Person',
					//'discoverable' => true,
					//'manuallyApprovesFollowers' => false,
					//'published' => $data['user_regdate'],
					'preferredUsername' => $data['username_clean'],
					'name' => $data['username'],
					'url' => $server_addr . '/memberlist.php?mode=viewprofile&u=' . $u,
					'icon' => [
						'type' => 'Image',
						'mediaType' => 'image/' . ($icon_ext === 'jpg' ? 'jpeg' : $icon_ext),
						'url' => $server_addr . '/download/file.php?avatar=' . $data['user_avatar'],
					],
					//'publicKey' => [
					//	'id' => $uri_id . '#main-key',
					//	'owner' => $uri_id,
					//	'publicKeyPem' => $public_key,
					//],
				];
			break;

			case 'inbox':
				
			break;

			case 'outbox':
				$u = $this->request->variable('u', '');

				$user_posts = $this->get_sql_row('
					SELECT user_posts
					FROM ' . USERS_TABLE . "
					WHERE user_id = '" . $this->db->sql_escape($u) . "'
				")['user_posts'];

				$response = [
					'@context' => 'https://www.w3.org/ns/activitystreams',
					'id' => $uri_id,
					'type' => 'OrderedCollection',
					'totalItems' => (int)$user_posts,
					'first' => $uri_id . '&view=page',
					'last' => $uri_id . '&view=page&min_id=0',
				];
			break;

			//case 'post':
			//	
			//break;
		}

		$response = new Response(json_encode($response), 200);
		$response->headers->set('Content-Type', 'application/activity+json; charset=utf-8');
		restore_error_handler();
		return $response;
	}
}
