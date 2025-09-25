<?php
namespace modules\contact\controllers;

use Craft;
use craft\web\Controller;
use SpomkyLabs\Pki\ASN1\Component\Length;
use yii\web\Response;

class ContactController extends Controller
{
	/** @var array|int|bool actions allowed anonymously */
	protected array|int|bool $allowAnonymous = ['send'];

	public function actionSend(): ?Response
	{
		$request = Craft::$app->getRequest();

		$fname = trim((string) $request->getBodyParam('fname'));
		$lname = trim((string) $request->getBodyParam('lname'));
		// $name = (string)$request->getBodyParam('name');
		$email = trim((string) $request->getBodyParam('email'));
		$message = (string) $request->getBodyParam('message');
		$countryCode = (string) $request->getBodyParam('countryCode');
		$phone = (string) $request->getBodyParam('phone');
		$company = (string) $request->getBodyParam('company');
		$subject = (string) $request->getBodyParam('subject');
		$address = (string) $request->getBodyParam('address');

		try {
			// Basic validation
			
			// Check if strings and proper length
			if (!is_string($fname) || !is_string($lname)) {
				throw new \RuntimeException('First name and last name must be text.');
			}

			if (strlen($fname) < 2 || strlen($fname) > 20 || strlen($lname) < 2 || strlen($lname) > 20) {
				throw new \RuntimeException('First name and last name must be between 2 and 20 characters.');
			}

			if (!preg_match("/^[A-Za-z\s'-]+$/", $fname) || !preg_match("/^[A-Za-z\s'-]+$/", $lname)) {
				throw new \RuntimeException('Names can only contain letters, spaces, hyphens, and apostrophes.');
			}
			// Email Validation
			if (!is_string($email) || $email === '') {
				throw new \RuntimeException('Email is required.');
			}

			if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
				throw new \RuntimeException('Please enter a valid email address.');
			}
			if (strlen($phone) > 10 || strlen($phone) < 10) {
				throw new \RuntimeException('Phone number must be exactly 10 digits.');
			}
			$name = $fname . ' ' . $lname;
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


