<?php
namespace modules\contact\controllers;

use Craft;
use craft\web\Controller;
use yii\web\Response;

class ContactController extends Controller
{
    /** @var array|int|bool actions allowed anonymously */
    protected array|int|bool $allowAnonymous = ['send'];

    public function actionSend(): ?Response
    {
        $request = Craft::$app->getRequest();

        $name = (string)$request->getBodyParam('name');
        $email = (string)$request->getBodyParam('email');
		$message = (string)$request->getBodyParam('message');
		$countryCode = (string)$request->getBodyParam('countryCode');
		$phone = (string)$request->getBodyParam('phone');
		$company = (string)$request->getBodyParam('company');
		$subject = (string)$request->getBodyParam('subject');
		$address = (string)$request->getBodyParam('address');

        try {
            // Basic validation
            if ($name === '' || $email === '' || $message === '') {
                throw new \RuntimeException('Please fill out all required fields.');
            }

			// Build a readable text body including optional fields
			$lines = [
				"Name: $name",
				"Email: $email",
				$phone !== '' ? "Phone: " . ($countryCode !== '' ? "$countryCode " : '') . $phone : null,
				$company !== '' ? "Company: $company" : null,
				$address !== '' ? "Address: $address" : null,
				"",
				"Message:",
				$message,
			];
			$textBody = implode("\n", array_values(array_filter($lines, static fn($v) => $v !== null && $v !== '')));

			// Send an email
			$toAddress = Craft::$app->projectConfig->get('email.fromEmail') ?: 'you@example.com';
			$subjectLine = $subject !== '' ? $subject : 'New Contact Form Message';
			$sent = Craft::$app->getMailer()
				->compose()
				->setTo($toAddress)
				->setFrom([$toAddress => Craft::$app->name])
				->setReplyTo([$email => $name])
				->setSubject($subjectLine)
				->setTextBody($textBody)
				->send();

            if (!$sent) {
                throw new \RuntimeException('Failed to send your message.');
            }
            Craft::$app->getSession()->setNotice('Message sent successfully!');
        } catch (\Throwable $e) {
            Craft::error($e->getMessage(), __METHOD__);
            Craft::$app->getSession()->setError($e->getMessage());
        }

        return $this->redirectToPostedUrl();
    }
}


