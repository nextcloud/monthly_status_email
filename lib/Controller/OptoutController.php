<?php

/**
 * @copyright Copyright (c) 2021 Carl Schwan <carl@carlschwan.eu>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

declare(strict_types=1);

namespace OCA\MonthlyStatusEmail\Controller;

use OCA\MonthlyStatusEmail\Service\NotFoundException;
use OCA\MonthlyStatusEmail\Service\NotificationTrackerService;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\NotFoundResponse;
use OCP\AppFramework\Http\Response;
use OCP\AppFramework\Http\Template\PublicTemplateResponse;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IRequest;
use OCP\AppFramework\Controller;
use OCP\IUserSession;

/**
 * Controller responsible for opting out of the notifications.
 */
class OptoutController extends Controller {
	/**
	 * @var NotificationTrackerService
	 */
	private $service;
	/**
	 * @var IUserSession
	 */
	private $session;

	/**
	 * ApiController constructor.
	 *
	 * @param string $appName
	 * @param IRequest $request
	 */
	public function __construct(
		$appName,
		IRequest $request,
		IUserSession $session,
		NotificationTrackerService $service
	) {
		parent::__construct($appName, $request);
		$this->service = $service;
		$this->session = $session;
		$this->appName = $appName;
	}

	/**
	 * @NoAdminRequired
	 */
	public function update(bool $optedOut): DataResponse {
		try {
			$this->service->updateOptedOut($this->session->getUser()->getUID(), $optedOut);
			return new DataResponse(['done' => true]);
		} catch (NotFoundException $exception) {
			return new DataResponse(['done' => false, 'exception' => 'not-found']);
		}
	}

	/**
	 * @PublicPage
	 * @NoCSRFRequired
	 * @UserRateThrottle
	 * @param bool $token
	 * @param bool $optedOut
	 * @return DataResponse
	 */
	public function displayOptingOutPage(string $token): TemplateResponse {
		// This page is required since link in emails get often visited with
		// GET requests by email clients. JS will take care of sending POST
		// request once the page is loaded.
		return new PublicTemplateResponse($this->appName, 'public-opting-out', [
			'token' => $token,
		]);
	}

	/**
	 * @PublicPage
	 * @UserRateThrottle
	 * @param bool $token
	 * @return DataResponse
	 */
	public function updateOptingOut(string $token): Response {
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
