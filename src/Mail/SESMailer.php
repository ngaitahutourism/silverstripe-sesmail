<?php

namespace Symbiote\SilverStripeSESMailer\Mail;

use Aws\Ses\SesClient;
use SilverStripe\Control\Email\Mailer;
use SilverStripe\Control\Email\Email;
use SilverStripe\Core\Injector\Injector;
use Exception;
use Psr\Log\LoggerInterface;
use SilverStripe\Core\Config\Config;
use PHPMailer\PHPMailer\PHPMailer;

/**
 * A mailer implementation which uses Amazon's Simple Email Service.
 *
 * This mailer uses the SendRawEmail endpoint, so it supports sending attachments. Note that this only sends sending
 * emails to up to 50 recipients at a time, and Amazon's standard SES usage limits apply.
 *
 * Does not support inline images.
 */
class SESMailer implements Mailer
{

	/**
	 * @var SesClient
	 */
	protected $client;

	/**
     * Uses QueuedJobs module when sending emails
     *
     * @var boolean
     */
	protected $useQueuedJobs = true;

	/**
	 * @var array|null
	 */
	protected $lastResponse = null;

	/**
	 * @param array $config
	 */
	public function __construct()
	{
		$config = Config::inst()->get('Symbiote\SilverStripeSESMailer\Mail\Config');
        $this->client = SesClient::factory($config);
	}

	/**
	 * @param boolean $bool
	 *
	 * @return $this
	 */
	public function setUseQueuedJobs($bool)
	{
		$this->useQueuedJobs = $bool;

		return $this;
	}

	/**
	 * @return array|null
	 */
	public function getLastResponse()
	{
		return $this->lastResponse;
	}

	/**
	 * @param SilverStripe\Control\Email
	 */
	public function send($email)
	{
        $config = Injector::inst()->get('SilverStripe\Control\Email\Mailer');
        $from = array_keys($email->getFrom())[0];
        $to = array_keys($email->getTo())[0];

        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Username   = $config->key;
        $mail->Password   = $config->secret;
        $mail->Host       = 'email-smtp.' . $config->region . '.amazonaws.com';
        $mail->Port       = 587;
        $mail->SMTPAuth   = true;
        $mail->SMTPSecure = 'tls';
        $mail->addAddress($to);
        $mail->setFrom($from);
        $mail->isHTML(true);
        $mail->Subject    = $email->getSubject();
        $mail->Body       = $email->body;
        $mail->Send();

		return true;
	}

	/**
	 * Send an email via SES. Expects an array of valid emails and a raw email body that is valid.
	 *
	 * @param array $destinations array of emails addresses this email will be sent to
	 * @param string $rawMessageText Raw email message text must contain headers; and otherwise be a valid email body
	 * @return Array Amazon SDK response
	 */
	public function sendSESClient ($destinations, $rawMessageText) {

		try {
			$response = $this->client->sendRawEmail(array(
				'Destinations' => $destinations,
				'RawMessage' => array('Data' => $rawMessageText)
			));
		} catch (Exception $ex) {
			/*
			 * Amazon SES has intermittent issues with SSL connections being dropped before response is full received
			 * and decoded we're catching it here and trying to send again, the exception doesn't have an error code or
			 * similar to check on so we have to relie on magic strings in the error message. The error we're catching
			 * here is normally:
			 *
			 * AWS HTTP error: cURL error 56: SSL read: error:00000000:lib(0):func(0):reason(0), errno 104
			 * (see http://curl.haxx.se/libcurl/c/libcurl-errors.html) (server): 100 Continue
			 *
			 * Without the line break, so we check for the 'cURL error 56' as it seems likely to be consistent across
			 * systems/sites
			 */
			if(strpos($ex->getMessage(), "cURL error 56")) {
				$response = $this->client->sendRawEmail(array(
					'Destinations' => $destinations,
					'RawMessage' => array('Data' => $rawMessageText)
				));
			} else {
				throw $ex;
			}
		}

		return $response;
	}
}
