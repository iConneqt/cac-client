<?php

namespace CAC\Component\ESP\Api\Engine;

/**
 * iConneqt E-Ngine compatibility layer
 *
 * Abstract interface definition exposing only compatibility methods.
 *
 * @copyright (c) 2017, Advanced CRMMail Technology B.V., Netherlands
 * @license BDS-3-clause
 * @author Martijn W. van der Lee
 * @author Crazy Awesome Company <info@crazyawesomecompany.com>
 */
interface EngineApiInterface
{
		
    public function __construct(array $config);

    /**
     * Create a new E-Ngine Mailing from content
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
    public function createMailingFromContent($htmlContent, $textContent, $subject, $fromName, $fromEmail, $replyTo = null, $title = null);

    /**
     * Create a new mailing based on an E-Ngine Template
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
    public function createMailingFromTemplate($templateId, $subject, $fromName, $fromEmail, $replyTo = null, $title = null);

    /**
     * Create a new mailing based on an E-Ngine Template
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
    public function createMailingFromTemplateWithReplacements($templateId, $replacements, $subject, $fromName, $fromEmail, $replyTo = null, $title = null);

    public function sendMailing($mailingId, array $users, $date = null, $mailinglistId = null);

    public function sendMailingWithAttachment($mailingId, array $user, $date = null, $mailinglistId = null, $attachments = array());
	
    /**
     * Select a Mailinglist
     *
     * @param integer $mailinglistId
     *
     * @return string
     */
    public function selectMailinglist($mailinglistId);

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
    public function subscribeUser(array $user, $mailinglistId, $confirmed = false);

    /**
     * Unsubscribe a User from a Mailinglist
     *
     * @param string  $email         The emailaddress to unsubscribe
     * @param integer $mailinglistId The mailinglist id to unsubscribe the user from
     * @param bool    $confirmed     Is the unsubscription already confirmed
     *
     * @return string
     *
     * @throws EngineApiException
     */
    public function unsubscribeUser($email, $mailinglistId, $confirmed = false);

    /**
     * Get all mailinglists of the account
     *
     * @return array
     */
    public function getMailinglists();

    /**
     * Get all unsubscriptions from a mailingslist of a specific time period
     *
     * @param integer   $mailinglistId
     * @param \DateTime $from
     * @param \DateTime $till
     *
     * @return array
     */
    public function getMailinglistUnsubscriptions($mailinglistId, \DateTime $from, \DateTime $till = null);

    /**
     * Get Mailinglist Subscriber information
     *
     * @param integer $mailinglistId
     * @param string  $email
     * @param array   $columns
     *
     * @return array
     */
    public function getMailinglistUser($mailinglistId, $email, $columns=array());

    /**
     * Dummy method. Not functional
     * @param LoggerInterface $logger
     */
    public function setLogger();
}
