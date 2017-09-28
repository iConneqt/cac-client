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
		$this->config = array_replace_recursive(
				array(
			"wsdl" => null,
			"secure" => false,
			"domain" => "demo.iconneqt.nl", // "",
			"path" => "", // "path" => "/soap/server.live.php",
			"customer" => "",
			"user" => "",
			"password" => "",
			"trace" => false,
			"mailinglist" => null,
				), $config
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
	
	private static function replaceFieldMarkers($content) {
		return preg_replace('/(?:{{([^}]+)}})/', '%%\\1%%', $content);
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
// @todo assume what is in the $user array?		
//        $result = $this->performRequest('Subscriber_set', $user, !$confirmed, $mailinglistId);
//
//        if (!in_array($result, array('OK_UPDATED', 'OK_CONFIRM', 'OK_BEDANKT'))) {
//            $e = new EngineApiException(sprintf('User not subscribed to mailinglist. Engine Result: [%s]', $result));
//            $e->setEngineCode($result);
//
//            throw $e;
//        }
//
//        return $result;
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
	 * @param integer $mailinglistId
	 * @param string  $email
	 * @param array   $columns
	 *
	 * @return array
	 */
	public function getMailinglistUser($mailinglistId, $email, $columns = array())
	{
//@todo not yet implemented
//        if (count($columns) == 0) {
//            $columns = array('email', 'firstname', 'infix', 'lastname');
//        }
//
//        $result = $this->performRequest(
//            'Subscriber_getByEmail',
//            $email,
//            $columns,
//            $mailinglistId
//        );
//
//        return $result;
	}

	public function setLogger()
	{
		// deprecated
	}

}
