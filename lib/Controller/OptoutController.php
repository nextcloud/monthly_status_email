<?php

/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\MonthlyStatusEmail\Controller;

use OCA\MonthlyStatusEmail\Service\MailSender;
use OCA\MonthlyStatusEmail\Service\NotFoundException;
use OCA\MonthlyStatusEmail\Service\NotificationTrackerService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\Attribute\UserRateLimit;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\NotFoundResponse;
use OCP\AppFramework\Http\Response;
use OCP\AppFramework\Http\Template\PublicTemplateResponse;
use OCP\IRequest;
use OCP\IUserSession;

/**
 * Controller responsible for opting out of the notifications.
 */
class OptoutController extends Controller {
	public function __construct(
		string $appName,
		IRequest $request,
		private readonly IUserSession $session,
		private readonly NotificationTrackerService $service,
		private readonly MailSender $mailSender,
	) {
		parent::__construct($appName, $request);
	}

	#[NoAdminRequired]
	public function update(bool $optedOut): DataResponse {
		try {
			$user = $this->session->getUser();
			if ($user === null) {
				throw new \RuntimeException('User not logged in');
			}

			$trackedNotification = $this->service->updateOptedOut($user->getUID(), $optedOut);
			if (!$optedOut) {
				// Use just activated the monthly email again
				$this->mailSender->sendStatusEmailActivation($user, $trackedNotification);
			}
			return new DataResponse(['done' => true]);
		} catch (NotFoundException $exception) {
			return new DataResponse(['done' => false, 'exception' => 'not-found']);
		}
	}

	#[NoCSRFRequired]
	#[PublicPage]
	#[UserRateLimit(limit: 10, period: 120)]
	public function displayOptingOutPage(string $token): PublicTemplateResponse {
		// This page is required since link in emails get often visited with
		// GET requests by email clients. JS will take care of sending POST
		// request once the page is loaded.
		return new PublicTemplateResponse($this->appName, 'public-opting-out', [
			'token' => $token,
		]);
	}

	#[PublicPage]
	#[UserRateLimit(limit: 10, period: 120)]
	public function updateOptingOut(string $token): DataResponse|NotFoundResponse {
		try {
			$this->service->uptadeOptedOutByToken($token, true);
			return new DataResponse([
				'done' => true
			]);
		} catch (NotFoundException $exception) {
			return new NotFoundResponse();
		}
	}
}
