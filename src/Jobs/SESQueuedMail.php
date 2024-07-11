<?php

namespace Symbiote\SilverStripeSESMailer\Jobs;

use Symbiote\QueuedJobs\Services\AbstractQueuedJob;
use Symbiote\QueuedJobs\Services\QueuedJob;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Core\Config\Config;
use Symbiote\SilverStripeSESMailer\Mail\SESMailer;
use Exception;

if (class_exists(QueuedJob::class)) {
    /**
     * SESQueuedMail
     */
    class SESQueuedMail extends AbstractQueuedJob implements QueuedJob
    {
        public function __construct($destinations = array(), $subject = '', $rawMessageText = '')
        {
            if (!$destinations && !$subject && !$rawMessageText) {
                // Avoid QueuedJobService::initialiseJob errors
                return;
            }
            $this->To = $destinations;
            $this->Subject = $subject;
            $this->RawMessageText = $rawMessageText;
        }

        public function getTitle()
        {
            return 'Email To: ' . implode(', ', $this->To) . ' Subject: ' . $this->Subject;
        }

        public function getSignature()
        {
            return md5($this->Subject) . ' ' . implode(', ', $this->To);
        }

        public function getJobType()
        {
            $this->totalSteps = 1;

            return QueuedJob::QUEUED;
        }

        /**
         * Send this email. We try this only once and break as soon as something goes wrong to avoid sending multiple emails
         * or DoSing our job queue with slow processing emails.
         */
        public function process()
        {
            if ($this->isComplete) {
                return;
            }
            $this->currentStep += 1;

            // Detect issues with data
            $isCorrupt = false;
            $to = $this->To;
            $rawMessageText = $this->RawMessageText;
            if (!$to) {
                $this->addMessage('$this->To should not be empty.');
                $isCorrupt = true;
            }
            if (!$rawMessageText) {
                $this->addMessage('$this->RawMessageText should not be empty.');
                $isCorrupt = true;
            }
            if ($isCorrupt) {
                throw new Exception('Corrupted SESQueuedMail job (Missing "To" or "RawMessageText").', 'ERR');
            }

            $response = Injector::inst()->get(SESMailer::class)
                ->sendSESClient($to, $rawMessageText);

            $this->addMessage('SES Response: ' . print_r($response, true));

            if (isset($response['MessageId']) && strlen($response['MessageId']) &&
                (isset($response['@metadata']['statusCode']) && $response['@metadata']['statusCode'] == 200)) {
                $this->RawMessageText = 'Email Sent Successfully. Message body deleted';
                $this->isComplete = true;
                return;
            }
            $this->addMessage('Failed to send email.');
            if ($response) {
                throw new Exception(json_encode($response), 'ERR');
            }
            throw new Exception('Blank "response" result from SESMailer::sendSESClient()', 'ERR');
        }
    }
}
