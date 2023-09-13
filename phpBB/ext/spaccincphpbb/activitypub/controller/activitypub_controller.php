<?php

namespace spaccincphpbb\activitypub\controller;

use ErrorException;
use DOMDocument;
use DOMXpath;
use Symfony\Component\HttpFoundation\Response;

class activitypub_controller
{
	protected $config;
	protected $db;
	protected $request;

	// Quick way to force any PHP warning to be an error that halts execution and makes nothing return,
	// so that we don't need complex error handling in case of bad queries
	public function exception_error_handler($severity, $message, $file, $line)
	{
		if ($message !== 'Return type of phpbb\datetime::format($format = \'\', $force_absolute = false) should either be compatible with DateTime::format(string $format): string, or the #[\ReturnTypeWillChange] attribute should be used to temporarily suppress the notice' && $message !== 'Only variables should be passed by reference' && !(str_contains($message, ' service is private, getting it from the container is deprecated ') || str_contains($message, 'You should either make the service public, or stop using the container directly and use dependency injection instead.'))
		){
			print $message;
			throw new ErrorException($message, 0, $severity, $file, $line);
		}
	}

	public function __construct(
		\phpbb\config\config              $config,
		\phpbb\db\driver\driver_interface $db,
		\phpbb\request\request            $request,
	){
		$this->config  = $config;
		$this->db      = $db;
		$this->request = $request;

		$this->server_name = $this->config['server_name'];
		$this->server_addr = ((!empty($this->request->server('HTTPS')) && (strtolower($this->request->server('HTTPS')) == 'on' || $this->request->server('HTTPS') == '1')) ? 'https://' : 'http://') . $this->server_name;
	}

	private function iso_time($time)
	{
		return gmdate("Y-m-d\TH:i:s\Z", $time);
	}

	private function get_sql_row($sql)
	{
		$result = $this->db->sql_query($sql);
		$data = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);
		return $data;
	}

	private function get_sql_rows($sql)
	{
		$result = $this->db->sql_query($sql);
		$data = $this->db->sql_fetchrowset($result);
		$this->db->sql_freeresult($result);
		return $data;
	}

	private function get_bbcode_flags($data)
	{
		return
			($data['enable_bbcode'] ? OPTION_FLAG_BBCODE : 0) +
			($data['enable_smilies'] ? OPTION_FLAG_SMILIES : 0) +
			($data['enable_magic_url'] ? OPTION_FLAG_LINKS : 0);
	}

	private function make_post_attachments($html)
	{
		$attachments = [];

		$dom = new DOMDocument;
		$dom->loadHTML($html);
		$xpath = new DOMXpath($dom);
		$imgs = $dom->getElementsByTagName('img');
		$atts = $xpath->query('//div[@class="inline-attachment"]');

		// TODO: currently this picks up emojis, and must so be fixed
		//foreach($imgs as $item){
		//	$attachments[] = [
		//		'type' => 'Document',
		//		'mediaType' => 'image/*',
		//		'url' => $item->getAttribute('src'),
		//		//'name' => null,
		//	];
		//}
		unset($item);

		foreach($atts as $item){
			$file_name = explode('<!--', explode('-->', $item->ownerDocument->saveHtml($item))[1])[0];

			$file_id = $this->get_sql_row('
				SELECT attach_id
				FROM ' . ATTACHMENTS_TABLE . "
				WHERE real_filename='" . $this->db->sql_escape($file_name) . "'
			")['attach_id'];

			$attachments[] = [
				'type' => 'Document',
				'mediaType' => 'image/jpeg',
				'url' => $this->server_addr . '/download/file.php?id=' . $file_id,
				'name' => null,
			];
		}
		unset($item);

		return $attachments;
	}

	private function make_post_object($data, $in_create)
	{
		$uri_id = $this->server_addr . '/activitypub?&mode=post&post_id=' . $data['post_id'];
		$uri_user = $this->server_addr . '/activitypub?&mode=user&user_id=' . $data['poster_id'];

		$post_html = generate_text_for_display($data['post_text'], $data['bbcode_uid'], $data['bbcode_bitfield'], $this->get_bbcode_flags($data));
		$post_time = $this->iso_time($data['post_time']);
		// Note: #Public in to and followers in cc for public post, opposite for unlisted!
		$post_to = ['https://www.w3.org/ns/activitystreams#Public'];
		$post_cc = [];

		$note = [
			'id' => $uri_id,
			'type' => 'Note',
			'published' => $post_time,
			//'updated' => ,
			'attributedTo' => $uri_user,
			//'inReplyTo' => null,
			'to' => $post_to,
			'cc' => $post_cc,
			'url' => $this->server_addr . '/viewtopic.php?p=' . $data['post_id'] . '#p' . $data['post_id'],
			//'mediaType' => 'text/html',
			//'summary' => null,
			'content' => $post_html,
			//'contentMap' => [ 'it' => '' ],
			'attachment' => $this->make_post_attachments($post_html),
		];

		if ($in_create)
		{
			return [
				'@context' => [
					'https://www.w3.org/ns/activitystreams',
				],
				'id' => $uri_id,
				'type' => 'Create',
				'actor' => $uri_user,
				'published' => $post_time,
				//'updated' => ,
				'to' => $post_to,
				'cc' => $post_cc,
				'object' => $note,
			];
		}
		else
		{
			$response[] = $note;
			return array_merge([
				'@context' => [
					'https://www.w3.org/ns/activitystreams',
				]
			], $note);
		}
	}

	public function nodeinfo_known()
	{
		if (!$this->config['spaccinc_activitypub_setfederation']) {
			return;
		}

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
		if (!$this->config['spaccinc_activitypub_setfederation']) {
			return;
		}

		set_error_handler([$this, 'exception_error_handler']);
		$server_name = $this->server_name;
		$server_addr = $this->server_addr;
		$resource = $this->request->variable('resource', '');

		$subject = '';
		$href = [];

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

			$href['self'] = '&mode=user&user_id=' . $data['user_id'];
			$href['profile-page'] = '/memberlist.php?mode=viewprofile&user_id=' . $data['user_id'];
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
		if (!$this->config['spaccinc_activitypub_setfederation']) {
			return;
		}

		set_error_handler([$this, 'exception_error_handler']);
		$server_addr = $this->server_addr;
		$uri_id = htmlspecialchars_decode($server_addr . $this->request->server('REQUEST_URI'));
		$mode = $this->request->variable('mode', '');

		$response = null;

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
								'version' => '0.0.1-dev',
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
								'nodeName' => $this->config['sitename'],
							],
						];
					break;
				}
			break;

			case 'user':
				$user_id = $this->request->variable('user_id', '');

				$data = $this->get_sql_row('
					SELECT *
					FROM ' . USERS_TABLE . "
					WHERE user_id = '" . $this->db->sql_escape($user_id) . "'
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
					'inbox' => $server_addr . '/activitypub?&mode=inbox&user_id=' . $user_id,
					'outbox' => $server_addr . '/activitypub?&mode=outbox&user_id=' . $user_id,
					'endpoints' => [
						'sharedInbox' => $server_addr . '/activitypub&mode=inbox',
					],
					'type' => 'Person',
					//'discoverable' => true,
					//'manuallyApprovesFollowers' => false,
					'published' => $this->iso_time($data['user_regdate']),
					'preferredUsername' => $data['username_clean'],
					'name' => $data['username'],
					'url' => $server_addr . '/memberlist.php?mode=viewprofile&user_id=' . $user_id,
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
				$user_id = $this->request->variable('user_id', '');
				$page = $this->request->variable('page', '');
				//$min_id = $this->request->variable('min_id', -1);
				//$max_id = $this->request->variable('max_id', -1);

				$post_count = (int)$this->get_sql_row('
					SELECT user_posts
					FROM ' . USERS_TABLE . "
					WHERE user_id = '" . $this->db->sql_escape($user_id) . "'
				")['user_posts'];

				if ($page === '')
				{
					$response = [
						'@context' => [
							'https://www.w3.org/ns/activitystreams',
						],
						'id' => $uri_id,
						'type' => 'OrderedCollection',
						'totalItems' => $post_count,
						'first' => $uri_id . '&page=-1',
						'last' => $uri_id . '&page=0',
					];
				}
				else
				{
					$items = [];
					$order = 'DESC';
					$limit = 20;
					$offset = 0;

					switch ($page)
					{
						// Oldest
						case '0':
							$order = 'DESC';
						break;

						// Newest
						case '-1':
							// ...
						break;

						// Any other page
						default:
							// ...
						break;
					}

					$data = $this->get_sql_rows('
						SELECT *
						FROM ' . POSTS_TABLE . "
						WHERE poster_id = '" . $this->db->sql_escape($user_id) . "'
						ORDER BY post_id " . $order . '
						LIMIT ' . $limit . '
						OFFSET ' . $offset
					);

					foreach ($data as &$item)
					{
						$items[] = $this->make_post_object($item, true);
					}
					unset($item);

					$response = [
						'@context' => [
							'https://www.w3.org/ns/activitystreams',
						],
						'id' => $uri_id,
						'type' => 'OrderedCollectionPage',
						//'prev' => '',
						//'next' => '',
						// ...
						'partOf' => $server_addr . '/activitypub?&mode=outbox&user_id=' . $user_id,
						//'ordererdItems' => $items,
					];
				}
			break;

			//case 'thread':
			//	

			case 'post':
				$response = $this->make_post_object($this->get_sql_row('
					SELECT *
					FROM ' . POSTS_TABLE . "
					WHERE post_id = '" . $this->db->sql_escape($this->request->variable('post_id', '')) . "'
				"), false);
			break;
		}

		$response = new Response(json_encode($response), 200);
		$response->headers->set('Content-Type', 'application/activity+json; charset=utf-8');
		restore_error_handler();
		return $response;
	}
}
