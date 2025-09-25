<?php
namespace modules\contact\controllers;

use Craft;
use craft\web\Controller;
use craft\helpers\App;
use yii\web\UploadedFile;
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
		$terms = $request->getBodyParam('terms');
		$uploadedFile = UploadedFile::getInstanceByName('file');
		$privacyPolicy = $request->getBodyParam('privacyPolicy');
		$website = trim((string) $request->getBodyParam('website'));
		$honeypot = trim((string) $request->getBodyParam('website'));
		$recaptchaToken = (string) $request->getBodyParam('g-recaptcha-response');

		try {
			// reCAPTCHA v3 verification
			$secret = (string) (App::env('RECAPTCHA_SECRET_KEY') ?? '');
			if ($secret === '') {
				throw new \RuntimeException('reCAPTCHA is not configured.');
			}
			if ($recaptchaToken === '') {
				throw new \RuntimeException('reCAPTCHA verification failed.');
			}
			$client = Craft::createGuzzleClient(['timeout' => 5]);
			$response = $client->post('https://www.google.com/recaptcha/api/siteverify', [
				'form_params' => [
					'secret' => $secret,
					'response' => $recaptchaToken,
					'remoteip' => $request->getUserIP(),
				],
			]);
			$payload = json_decode((string) $response->getBody(), true) ?: [];
			if (!(isset($payload['success']) && $payload['success'] === true)) {
				throw new \RuntimeException('reCAPTCHA check failed.');
			}
			// Optional score/action checks for v3
			$score = isset($payload['score']) ? (float) $payload['score'] : 0.0;
			$action = isset($payload['action']) ? (string) $payload['action'] : '';
			if ($score < 0.5 || ($action && $action !== 'submit')) {
				throw new \RuntimeException('Suspicious activity detected.');
			}
			// Honeypot: if filled, treat as spam and silently succeed
			if ($website !== '') {
				Craft::warning('Honeypot triggered; dropping submission.', __METHOD__);
				Craft::$app->getSession()->setNotice('Message sent successfully!');
				return $this->redirectToPostedUrl();
			}
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
            if ($honeypot !== '') {
                // Bot detected: stop processing
                Craft::info('Honeypot triggered. Possible bot submission.', __METHOD__);
                Craft::$app->getSession()->setError('Unable to submit form.'); 
                return $this->redirectToPostedUrl();
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
			// Terms and privacy policy
			$lines[] = $terms ? "Terms Accepted: Yes" : "Terms Accepted: No";
			$lines[] = $privacyPolicy ? "Privacy Policy Accepted: Yes" : "Privacy Policy Accepted: No";
			$textBody = implode("\n", array_values(array_filter($lines, static fn($v) => $v !== null && $v !== '')));
			// Validate uploaded file if present
			if ($uploadedFile && $uploadedFile->error === UPLOAD_ERR_OK) {
				$allowedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'mp4'];
				$allowedMimeTypes = ['image/jpeg', 'image/png', 'image/webp', 'video/mp4'];
				$originalName = (string) $uploadedFile->name;
				$extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
				$mimeType = (string) $uploadedFile->type;
				$maxBytes = 20 * 1024 * 1024; // 20 MB cap

				if (!in_array($extension, $allowedExtensions, true)) {
					throw new \RuntimeException('Invalid file type. Allowed: JPEG, JPG, PNG, WEBP, MP4.');
				}
				if (!in_array($mimeType, $allowedMimeTypes, true)) {
					throw new \RuntimeException('Invalid file MIME type.');
				}
				if ($uploadedFile->size > $maxBytes) {
					throw new \RuntimeException('File is too large. Max 20 MB.');
				}
			}

			// Send an email
			$toAddress = Craft::$app->projectConfig->get('email.fromEmail') ?: 'you@example.com';
			$subjectLine = $subject !== '' ? $subject : 'New Contact Form Message';
			$messageModel = Craft::$app->getMailer()
				->compose()
				->setTo($toAddress)
				->setFrom([$toAddress => Craft::$app->name])
				->setReplyTo([$email => $name])
				->setSubject($subjectLine)
				->setTextBody($textBody);

			if ($uploadedFile && $uploadedFile->error === UPLOAD_ERR_OK) {
				$messageModel->attach($uploadedFile->tempName, [
					'fileName' => $uploadedFile->name,
					'contentType' => $uploadedFile->type ?: null,
				]);
			}

			$sent = $messageModel->send();

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


