# SilverStripe SES Mailer

Forked form [Symbiote SES Mail](https://github.com/symbiote/silverstripe-sesmail) with a [workaround](https://github.com/ngaitahutourism/silverstripe-sesmail/commit/2d2657b19fca4babc9839a6b335622a360961abb) to make it work with credentials stored in the .env file instead of the configuration one.

## Setting up

After installing the module, add configuration similar to the following
to enable the mailer

```yml
---
Name: AWSConfig
After:
    - '*'
---
SilverStripe\Core\Injector\Injector:
  SilverStripe\Control\Email\Mailer:
    class: Symbiote\SilverStripeSESMailer\Mail\SESMailer
    constructor:
      config:
        credentials:
          key: '`MY_KEY_CONST`'
          secret: '`MY_SECRET_CONST`'
        region: us-west-2
        version: '2010-12-01'
        signature_version: 'v4'
    properties:
      alwaysFrom: my@email.nz
      region: us-west-2
      key: '`MY_KEY_CONST`'
      secret: '`MY_SECRET_CONST`'

SilverStripe\Control\Email\Email:
  send_all_emails_from: my@email.nz
  admin_email: my@email.nz

Symbiote\SilverStripeSESMailer\Mail\Config:
  region: us-west-2
  version: '2010-12-01'
```
With `MY_KEY_CONST` and `MY_SECRET_CONST` defined in the project .env file

---

Emails will be sent through the QueuedJobs module if it is installed. You can
set the following configuration to bypass this behaviour even if QueuedJobs is
installed:

```yml
SilverStripe\Core\Injector\Injector:
  SilverStripe\Control\Email\Mailer:
    calls:
      - [ setUseQueuedJobs, [ false ] ]
```
