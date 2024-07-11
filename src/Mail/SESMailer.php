<?php

namespace Symbiote\SilverStripeSESMailer\Mail;

use Aws\Ses\SesClient;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Core\Config\Config;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mailer\Mailer as SymfonyMailer;
use Symfony\Component\Mime\Email as SymfonyEmail;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Exception;
use Psr\Log\LoggerInterface;

/**
 * A mailer implementation which uses Amazon's Simple Email Service.
 *
 * This mailer uses the SendRawEmail endpoint, so it supports sending attachments. Note that this only sends sending
 * emails to up to 50 recipients at a time, and Amazon's standard SES usage limits apply.
 *
 * Does not support inline images.
 */
class SESMailer
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
     * @param SymfonyEmail $email
     */
    public function send(SymfonyEmail $email)
    {
        $config = Config::inst()->get('Symbiote\SilverStripeSESMailer\Mail\Config');
        $from = $email->getFrom()[0]->getAddress();
        $to = $email->getTo()[0]->getAddress();

        // Create Symfony Email instance
        $symfonyEmail = new SymfonyEmail();
        $symfonyEmail->from(new Address($from));
        $symfonyEmail->to(new Address($to));
        $symfonyEmail->subject($email->getSubject());
        $symfonyEmail->html($email->getHtmlBody());

        // Create Symfony Mailer instance
        $dsn = sprintf('smtp://%s:%s@email-smtp.%s.amazonaws.com:587', $config['key'], $config['secret'], $config['region']);
        $transport = Transport::fromDsn($dsn);
        $mailer = new SymfonyMailer($transport);

        try {
            $mailer->send($symfonyEmail);
        } catch (TransportExceptionInterface $e) {
            Injector::inst()->get(LoggerInterface::class)->error($e->getMessage());
            throw new Exception('Failed to send email: ' . $e->getMessage(), 0, $e);
        }

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
            if (strpos($ex->getMessage(), "cURL error 56")) {
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
