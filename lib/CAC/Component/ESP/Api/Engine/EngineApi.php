<?php

namespace CAC\Component\ESP\Api\Engine;

/**
 * iConneqt E-Ngine compatibility layer
 *
 * API Client to connect to the iConneqt REST API
 * Serves as a drop-in replacement for the CAC E-ngine client.
 *
 * @copyright (c) 2017, Advanced CRMMail Technology B.V., Netherlands
 * @license BDS-3-clause
 * @author Martijn W. van der Lee
 * @author Crazy Awesome Company <info@crazyawesomecompany.com>
 */
class EngineApi implements EngineApiInterface
{

	/**
	 * Map of "E-ngine fieldrole => iConneqt fieldrole".
	 * E-ngine: https://wiki.e-ngine.nl/e-ngine/available-fieldnames/
	 * iConneqt: /doc/Customfield roles.md
	 * @var array
	 */
	private static $fieldrole_translations = [
		'firstname' => 'namefirst',
		'infix' => 'namelastprefix',
		'lastname' => 'namelast',
		'gender' => 'gender',
		'dateofbirth' => 'dateofbirth',
		'phonenumber' => 'telephone',
		'street' => 'street',
		'postalcode' => 'postalcode',
		'housenumber' => 'streetnumber',
		'housenumber_add' => '',
		'city' => 'city',
		'country' => 'country',
	];

	/**
	 * Api configuration
	 * @var array
	 */
	private $config;

	/**
	 * iConneqt REST API Client (direct connection)
	 * @var \Iconneqt\Api\Rest\Client\Client 
	 */
	private $client;

	/**
	 * Default mailing list
	 * @var int
	 */
	private $listid;

	/**
	 * @param array $config
	 * @throws EngineApiException
	 */
	public function __construct(array $config)
	{
		$this->config = array_replace_recursive([
			"wsdl" => null,
			"secure" => false,
			"domain" => "demo.iconneqt.nl", // "",
			"path" => "", // "path" => "/soap/server.live.php",
			"customer" => "",
			"user" => "",
			"password" => "",
			"trace" => false,
			"mailinglist" => null,
				], $config
		);

		if (!empty($this->config['mailinglist'])) {
			$this->listid = (int) $this->config['mailinglist'];
		}

		$this->client = new \Iconneqt\Api\Rest\Client\Client('https://' . $this->config['domain'], $this->config['user'], $this->config['password']);
	}

	/**
	 * Create a new iConneqt Mailing from content
	 *
	 * @param string $htmlContent
	 * @param string $textContent
	 * @param string $subject
	 * @param string $fromName
	 * @param string $fromEmail
	 * @param string $replyTo
	 *
	 * @return integer
	 *
	 * @throws EngineApiException
	 */
	public function createMailingFromContent($htmlContent, $textContent, $subject, $fromName, $fromEmail, $replyTo = null, $title = null)
	{
		if (!$this->listid) {
			throw new EngineApiException("No `mailinglist` selected");
		}

		if (null === $replyTo) {
			$replyTo = $fromEmail;
		}

		if (null === $title) {
			$title = $subject;
		}

		// E-ngine Mailing = iConneqt newsletter + delivery
		try {
			$newsletterid = $this->client->put("newsletters", [
				'name' => utf8_encode($title),
				'subject' => utf8_encode(self::replaceFieldMarkers($subject)),
				'html' => utf8_encode(self::replaceFieldMarkers($htmlContent)),
				'text' => utf8_encode(self::replaceFieldMarkers($textContent)),
					], null, false, false);

			$deliveryid = $this->client->put("newsletters/{$newsletterid}/deliveries", [
				'list' => $this->listid,
				'from_name' => $fromName,
				'from_email' => $fromEmail,
				'reply_email' => $replyTo,
					], null, false, false);
		} catch (\Iconneqt\Api\Rest\Client\StatusCodeException $e) {
			throw new EngineApiException(sprintf('Could not create mailing from content. Engine Result: [%s]', (string) $e));
		}

		return $deliveryid;
	}

	/**
	 * Create a new mailing based on an iConneqt Template
	 *
	 * @param integer $templateId
	 * @param string  $subject
	 * @param string  $fromName
	 * @param string  $fromEmail
	 * @param string  $replyTo
	 * @param string  $title
	 *
	 * @return integer
	 *
	 * @throws EngineApiException
	 */
	public function createMailingFromTemplate($templateId, $subject, $fromName, $fromEmail, $replyTo = null, $title = null)
	{
		return $this->createMailingFromTemplateWithReplacements($templateId, [], $subject, $fromName, $fromEmail, $replyTo = null, $title = null);
	}

	/**
	 * Create a new mailing based on an iConneqt Template
	 *
	 * @param integer $templateId
	 * @param array   $replacements
	 * @param string  $subject
	 * @param string  $fromName
	 * @param string  $fromEmail
	 * @param string  $replyTo
	 * @param string  $title
	 *
	 * @return integer
	 *
	 * @throws EngineApiException
	 */
	public function createMailingFromTemplateWithReplacements($templateId, $replacements, $subject, $fromName, $fromEmail, $replyTo = null, $title = null)
	{
		if (!$this->listid) {
			throw new EngineApiException("No `mailinglist` selected");
		}

		if (null === $replyTo) {
			$replyTo = $fromEmail;
		}

		if (null === $title) {
			$title = $subject;
		}

		// $replacements is map of [ from => to ], with a `%%key%%` pattern
		$fromto = [];
		foreach ($replacements as $key => $value) {
			$fromto[] = [
				'from' => '%%' . $key . '%%',
				'to' => utf8_encode($value),
			];
		}

		// E-ngine Mailing = iConneqt newsletter + delivery
		try {
			$newsletterid = $this->client->put("newsletters", [
				'name' => utf8_encode($title),
				'template' => $templateId,
				'subject' => self::replaceFieldMarkers($subject), // overwrites template subject
				'replacements' => $fromto,
					], null, false, false);

			$deliveryid = $this->client->put("newsletters/{$newsletterid}/deliveries", [
				'list' => $this->listid,
				'from_name' => $fromName,
				'from_email' => $fromEmail,
				'reply_email' => $replyTo,
					], null, false, false);
		} catch (\Iconneqt\Api\Rest\Client\StatusCodeException $e) {
			throw new EngineApiException(sprintf('Could not create mailing from template. Engine Result: [%s]', (string) $e));
		}

		return $deliveryid;
	}

	/**
	 * Send a created mailing
	 * 
	 * @param type $mailingId Unique identifier of the mailing to be send
	 * @param array $users List of maps width details of the recipients.
	 * 	The map contains a key 'email' with the emailaddress of the recipient.
	 *  Optional additional fields and values may be provided for replacement.
	 * @param \DateTime $date the date when to send.
	 * 	If the date is now or in the past, the mail will be sent immediately.
	 *  If you are sending a lot of	emails, you should set a date slightly in
	 *  the future; mails will be send out within the next minute instead.
	 * @param null $mailinglistId Deprecated
	 * 
	 * @return integer number of uses succesfully sent to
	 * 
	 * @throws EngineApiException
	 */
	public function sendMailing($mailingId, array $users, $date = null, $mailinglistId = null)
	{
		// Check if users are set
		if (empty($users)) {
			throw new EngineApiException("No users to send mailing");
		}

		foreach ($users as $user) {
			$this->sendMailingWithAttachment($mailingId, $user, $date, $mailinglistId, []);
		}

		// Return number of users. In any failed, an exception has been thrown.
		return count($users);
	}

	/**
	 * Send a created mailing
	 * 
	 * @param type $mailingId Unique identifier of the mailing to be send
	 * @param array $user Map with details of the recipient.
	 * 	The map contains a key 'email' with the emailaddress of the recipient.
	 *  Optional additional fields and values may be provided for replacement.
	 * @param \DateTime $date the date when to send.
	 * 	If the date is now or in the past, the mail will be sent immediately.
	 * @param null $mailinglistId Deprecated
	 * @param string[] $attachments List of URL's to files to attach.
	 * 
	 * @return integer number of uses succesfully sent to
	 * 
	 * @throws EngineApiException
	 */
	public function sendMailingWithAttachment($mailingId, array $user, $date = null, $mailinglistId = null, $attachments = array())
	{

		if (null === $date) {
			$date = date("Y-m-d H:i:s");
		} elseif ($date instanceof \DateTime) {
			$date = $date->format("Y-m-d H:i:s");
		}

		// Check if user is set
		if (empty($user)) {
			throw new EngineApiException("No user to send mailing");
		}

		// E-ngine mailing = iConneqt delivery
		// E-ngine mailing-subscriber = iConneqt delivery-recipient
		try {
			$email = $user['email'];
			unset($user['email']);

			$this->client->post("deliveries/{$mailingId}/recipients", [
				'emailaddress' => $email,
				'senddate' => $date,
				'fields' => array_map(function($value, $field) {
							if (isset(self::$fieldrole_translations[$field])) {
								$field = self::$fieldrole_translations[$field];
							}

							return [
								'field' => $field,
								'value' => $value,
							];
						}, $user, array_keys($user)),
				'attachments' => array_map(function($url) {
							return [
								'url' => $url,
								'name' => basename(parse_url($url, PHP_URL_PATH)),
							];
						}, $attachments),
					], null, false, false);
		} catch (\Iconneqt\Api\Rest\Client\StatusCodeException $e) {
			throw new EngineApiException(sprintf('Could not send mailing [%d]. Engine Result: [%s]', $mailingId, (string) $e));
		}

		return true;
	}

	/**
	 * Select a Mailinglist
	 *
	 * @param integer $mailinglistId
	 *
	 * @return string
	 */
	public function selectMailinglist($mailinglistId)
	{
		$this->listid = $mailinglistId;

		return true;
	}

	/**
	 * Subscribe a User to a Mailinglist
	 *
	 * @param array   $user          The user data
	 * @param integer $mailinglistId The mailinglist id to subscribe the user
	 * @param bool    $confirmed     Is the user already confirmed
	 *
	 * @return string
	 *
	 * @throws EngineApiException
	 */
	public function subscribeUser(array $user, $mailinglistId, $confirmed = false)
	{
		if ($mailinglistId === null) {
			if ($this->listid) {
				$mailinglistId = $this->listid;
			} else {
				throw new EngineApiException("No `mailinglist` selected");
			}
		}
		
		try {
			$data = [
				'emailaddress'	=> $user['email'],
			];
			unset($user['email']);
			
			if ($confirmed) {
				$data['confirmed'] = true;
			}
			
			$fields = $this->client->get("lists/{$mailinglistId}/fields", null, false, false);			
			$data['fields'] = [];		
			foreach ($fields as $field) {
				// match by name				
				if (isset($user[$field->name])) {
					$data['fields'][$field->id] = $user[$field->name];
					unset($user[$field->name]);
				}
				// else, match by role
				elseif (!empty($field->role)) {
					// match by e_role first
					foreach (array_keys(self::$fieldrole_translations, $field->role) as $e_role) {
						if (isset($user[$e_role])) {
							// match by role
							$data['fields'][$field->id] = $user[$e_role];
							unset($user[$e_role]);
							continue 2;	// next field!
						}
					}
					// otherwise, match by iconneqt role
					if (isset($user[$field->role])) {
						$data['fields'][$field->id] = $user[$field->role];
						unset($user[$field->role]);
					}
				}
			}				
			
			// Handle remaining user fields
			foreach ($user as $key => $value) {
				switch ($user[$key]) {
					case 'ip_subscription':		$data['requestip'] = $value; break;
					case 'date_subscription':	$data['requestdate'] = strtotime($value); break;
					case 'ip_confirmed':		$data['confirmip'] = $value; break;
					case 'date_confirmed':		$data['confirmdate'] = $data['subscribedate'] = strtotime($value); break;
					default:					$data[$key] = $value;
				}
			}
			
			$this->client->put("lists/{$mailinglistId}/subscribers", $data, null, false, false);
		} catch (\Iconneqt\Api\Rest\Client\StatusCodeException $e) {
			throw new EngineApiException(sprintf('User not subscribed to mailinglist. Engine Result: [%s]', (string) $e));
		}

		return 'OK_BEDANKT';
	}

	/**
	 * Unsubscribe a User from a Mailinglist
	 *
	 * @param string  $email         The emailaddress to unsubscribe
	 * @param null    $mailinglistId Deprecated
	 * @param bool    $confirmed     Is the unsubscription already confirmed
	 *
	 * @return string
	 *
	 * @throws EngineApiException
	 */
	public function unsubscribeUser($email, $mailinglistId, $confirmed = false)
	{
		try {
			$this->client->post("subscribers/{$email}/status", [
				'status' => 'unsubscribed',
					], null, false, false);
		} catch (\Iconneqt\Api\Rest\Client\StatusCodeException $e) {
			throw new EngineApiException(sprintf('User not unsubscribed from mailinglist. Engine Result: [%s]', (string) $e));
		}

		return 'OK';
	}

	/**
	 * Get all mailinglists of the account
	 *
	 * @return array
	 */
	public function getMailinglists()
	{
		try {
			$lists = $this->client->get("lists", null, false, false);
		} catch (\Iconneqt\Api\Rest\Client\StatusCodeException $e) {
			throw new EngineApiException(sprintf('User not unsubscribed from mailinglist. Engine Result: [%s]', (string) $e));
		}

		return array_map(function($list) {
			return [
				'mailinglistid' => $list->id,
				'uniqueid' => $list->id,
				'name' => $list->name,
			];
		}, $lists);
	}

	/**
	 * Get all unsubscriptions from a mailingslist of a specific time period
	 *
	 * @param integer   $mailinglistId
	 * @param \DateTime $from
	 * @param \DateTime $till
	 *
	 * @return array
	 */
	public function getMailinglistUnsubscriptions($mailinglistId, \DateTime $from, \DateTime $till = null)
	{
//@todo not yet implemented
//        if (null === $till) {
//            // till now if no till is given
//            $till = new \DateTime();
//        }
//
//        $result = $this->performRequest(
//            'Mailinglist_getUnsubscriptions',
//            $from->format('Y-m-d H:i:s'),
//            $till->format('Y-m-d H:i:s'),
//            null,
//            array('self', 'admin', 'hard', 'soft', 'spam', 'zombie'),
//            $mailinglistId
//        );
//
//        return $result;
	}

	/**
	 * Get Mailinglist Subscriber information
	 *
	 * @param null|integer $mailinglistId Use selected list if null.
	 * @param string $email
	 * @param array $columns
	 *
	 * @return array
	 */
	public function getMailinglistUser($mailinglistId = null, $email, $columns = array())
	{
		if ($mailinglistId === null) {
			if ($this->listid) {
				$mailinglistId = $this->listid;
			} else {
				throw new EngineApiException("No `mailinglist` selected");
			}
		}

		if (empty($columns)) {
			$columns = ['email', 'firstname', 'infix', 'lastname'];
		}

		try {
			$fields = $this->client->get("lists/{$mailinglistId}/fields", null, false, false);
			$subscriber = $this->client->get("lists/{$mailinglistId}/subscribers/{$email}", null, false, false);
			$data = $this->client->get("subscribers/{$subscriber->id}/fields", null, false, false);
			$result = [];
			
			foreach ($columns as $column) {
				if ($column == 'email') {
					$result['email'] = $subscriber->email;
				}
				
				$role = isset(self::$fieldrole_translations[$column]) ? self::$fieldrole_translations[$column] : null;
				foreach ($fields as $field) {
					if (($role && $field->role == $role) || ($field->name == $column) || ($field->id == $column)) {
						$fieldid = $field->id;
						foreach ($data as $datum) {
							if ($datum->id == $field->id) {
								$result[$column] = $datum->value;
								continue 3;
							}
						}
					}
				}
			}
			
			return $result;
		} catch (\Iconneqt\Api\Rest\Client\StatusCodeException $e) {
			throw new EngineApiException(sprintf('User not on mailinglist. Engine Result: [%s]', (string) $e));
		}
	}

	public function setLogger()
	{
		// deprecated
	}

	/**
	 * Replace `{{name}}` with `%%name%%` patterns in content
	 * @param string $content
	 * @return string
	 */
	private static function replaceFieldMarkers($content)
	{
		return preg_replace('/(?:{{([^}]+)}})/', '%%\\1%%', $content);
	}

}
