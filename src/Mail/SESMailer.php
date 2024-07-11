<?php

namespace Symbiote\SilverStripeSESMailer\Mail;

use Aws\Ses\SesClient;
use SilverStripe\Control\Email\Emailer;
use SilverStripe\Control\Email\Email;
use SilverStripe\Core\Injector\Injector;
use Exception;
use Psr\Log\LoggerInterface;
use SilverStripe\Core\Config\Config;
use PHPMailer\PHPMailer\PHPMailer;

/**
 * A mailer implementation which uses Amazon's Simple Email Service.
 *
 * This mailer uses the SendRawEmail endpoint, so it supports sending attachments. Note that this only sends emails
 * to up to 50 recipients at a time, and Amazon's standard SES usage limits apply.
 *
 * Does not support inline images.
 */
class SESMailer implements Emailer
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
     * @param Email $email
     * @return bool
     */
    public function send($email)
    {
        $config = Injector::inst()->get('Symbiote\SilverStripeSESMailer\Mail\Config');
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
        $mail->send();

        return true;
    }

    /**
     * Send an email via SES. Expects an array of valid emails and a raw email body that is valid.
     *
     * @param array $destinations array of emails addresses this email will be sent to
     * @param string $rawMessageText Raw email message text must contain headers; and otherwise be a valid email body
     * @return array Amazon SDK response
     */
    public function sendSESClient($destinations, $rawMessageText)
    {
        try {
            $response = $this->client->sendRawEmail([
                'Destinations' => $destinations,
                'RawMessage' => ['Data' => $rawMessageText]
            ]);
        } catch (Exception $ex) {
            if (strpos($ex->getMessage(), "cURL error 56") !== false) {
                $response = $this->client->sendRawEmail([
                    'Destinations' => $destinations,
                    'RawMessage' => ['Data' => $rawMessageText]
                ]);
            } else {
                throw $ex;
            }
        }

        return $response;
    }
}
