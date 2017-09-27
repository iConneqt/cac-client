<?php

namespace CAC\Component\ESP\Api\Engine;

/**
 * iConneqt E-Ngine compatibility layer
 *
 * Api Client to connect to the E-Ngine ESP webservice.
 *
 * @copyright (c) 2017, Advanced CRMMail Technology B.V., Netherlands
 * @license BDS-3-clause
 * @author Martijn W. van der Lee
 * @author Crazy Awesome Company <info@crazyawesomecompany.com>
 */
class EngineApi implements EngineApiInterface
{
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
                "domain" => "demo.iconneqt.nl",	// "",
				"path" => "",	// "path" => "/soap/server.live.php",
                "customer" => "",
                "user" => "",
                "password" => "",
                "trace" => false,
                "mailinglist" => null,
            ),
            $config
        );
		
		if (empty($this->config['mailinglist'])) {
			throw new EngineApiException("Configuration parameter `mailinglist` not set. Must be set.");
		} else {
			$this->listid = (int) $this->config['mailinglist'];
		}
		
		$this->client = new \Iconneqt\Api\Rest\Client\Client('https://' . $this->config['domain'], $this->config['user'], $this->config['password']);
    }

    public function createMailingFromContent($htmlContent, $textContent, $subject, $fromName, $fromEmail, $replyTo = null, $title = null)
    {
        if (null === $replyTo) {
            $replyTo = $fromEmail;
        }

        if (null === $title) {
            $title = $subject;
        }
		
		// E-ngine Mailing = iConneqt newsletter + delivery
		try {
			$newsletterid = $this->client->put("newsletters", [
				'name'		=> utf8_encode($title),
				'subject'	=> utf8_encode($subject),
				'html'		=> utf8_encode($htmlContent),
				'text'		=> utf8_encode($textContent),
			]);	
			
			$deliveryid = $this->client->put("newsletters/{$newsletterid}/deliveries", [
				'list'			=> $this->listid,
				'from_name'		=> $fromName,
				'from_email'	=> $fromEmail,
				'reply_email'	=> $replyTo,
			]);
		} catch (\Iconneqt\Api\Rest\Client\StatusCodeException $e) {
			throw new EngineApiException(sprintf('Could not create mailing from content. Engine Result: [%s]', $e->getMessage()));
		}
		
		return $deliveryid;
    }

    public function createMailingFromTemplate($templateId, $subject, $fromName, $fromEmail, $replyTo = null, $title = null)
    {
        return $this->createMailingFromTemplateWithReplacements($templateId, [], $subject, $fromName, $fromEmail, $replyTo = null, $title = null);
    }
	
    public function createMailingFromTemplateWithReplacements($templateId, $replacements, $subject, $fromName, $fromEmail, $replyTo = null, $title = null)
    {
        if (null === $replyTo) {
            $replyTo = $fromEmail;
        }

        if (null === $title) {
            $title = $subject;
        }
		
		// $replacements is map of [ from => to ], with a `{{key}}` pattern
		$fromto = [];
        foreach ($replacements as $key => $val) {
			$fromto[] = [
				'from'	=> '{{' . $key . '}}',
				'to'	=> utf8_encode($val),
			];
        }

		// E-ngine Mailing = iConneqt newsletter + delivery
		try {
			$newsletterid = $this->client->put("newsletters", [
				'name'			=> utf8_encode($title),
				'template'		=> $templateId,
				'subject'		=> $subject,	// overwrites template subject
				'replacements'	=> $fromto,
			]);	
			
			$deliveryid = $this->client->put("newsletters/{$newsletterid}/deliveries", [
				'list'			=> $this->listid,
				'from_name'		=> $fromName,
				'from_email'	=> $fromEmail,
				'reply_email'	=> $replyTo,
			]);
		} catch (\Iconneqt\Api\Rest\Client\StatusCodeException $e) {
			throw new EngineApiException(sprintf('Could not create mailing from template. Engine Result: [%s]', $e->getMessage()));
		}
		
		return $deliveryid;
    }

    public function sendMailing($mailingId, array $users, $date = null, $mailinglistId = null)
    {
        if (null === $date) {
            $date = date("Y-m-d H:i:s");
        } elseif ($date instanceof \DateTime) {
            $date = $date->format("Y-m-d H:i:s");
        }

		// $mailinglistId is ignored. Must be set for during creation of delivery

        // Check if users are set
        if (empty($users)) {
            throw new EngineApiException("No users to send mailing");
        }
		
		// E-ngine mailing = iConneqt delivery
		// E-ngine mailing-subscriber = iConneqt delivery-recipient
		try {
			foreach ($users as $user) {				
				$email = $user['email'];
				unset($user['email']);
				
				$this->client->post("deliveries/{$mailingId}/recipients", [
					'emailaddress'	=> $email,
					'senddate'		=> $date,
					'fields'		=> array_map(function($value, $field) {
											return [
												'field'	=> $field,
												'value' => $value,							
											];
										}, $user, array_keys($user)),
				]);
			}
		} catch (\Iconneqt\Api\Rest\Client\StatusCodeException $e) {
			throw new EngineApiException(sprintf('Could not send mailing [%d]. Engine Result: [%s]', $mailingId, $e->getMessage()));
		}
		
		// Return number of users. In any failed, an exception has been thrown.
		return count($users);
    }

    public function sendMailingWithAttachment($mailingId, array $user, $date = null, $mailinglistId = null, $attachments = array())
    {
		// $mailinglistId is not used in iConneqt.

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
			$this->client->post("deliveries/{$mailingId}/recipients", [
				'emailaddress'	=> $user,
				'senddate'		=> $date,
				'attachments'	=> array_map(function($url) {
										return [
											'url'	=> $url,
											'name'	=> basename(parse_url($url, PHP_URL_PATH)),
										];
									}, $attachments),											
			]);
		} catch (\Iconneqt\Api\Rest\Client\StatusCodeException $e) {
			throw new EngineApiException(sprintf('Could not send mailing [%d]. Engine Result: [%s]', $mailingId, $e->getMessage()));
		}

        return true;
    }

    public function selectMailinglist($mailinglistId)
    {
		$this->listid = $mailinglistId;
				
		return true;
    }

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

    public function unsubscribeUser($email, $mailinglistId, $confirmed = false)
    {
		// $mailinglistId and $confirmed are not used by iConneqt
		
		try {
			$this->client->post("subscribers/{$email}/status", [
				'status'	=> 'unsubscribed',
			]);
		} catch (\Iconneqt\Api\Rest\Client\StatusCodeException $e) {
			throw new EngineApiException(sprintf('User not unsubscribed from mailinglist. Engine Result: [%s]', $e->getMessage()));
		}
		
		return 'OK';
    }
	
    public function getMailinglists()
    {
//@todo what does this call return in E-ngine. Wiki still down?		
//        $result = $this->performRequest('Mailinglist_all');
//
//        return $result;
    }

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

    public function getMailinglistUser($mailinglistId, $email, $columns=array())
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
