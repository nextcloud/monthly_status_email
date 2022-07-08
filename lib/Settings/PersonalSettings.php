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

namespace OCA\MonthlyStatusEmail\Settings;

use OCA\MonthlyStatusEmail\Service\NotificationTrackerService;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IInitialState;
use OCP\IUserSession;
use OCP\Settings\ISettings;

class PersonalSettings implements ISettings {
	/**
	 * @var IInitialState
	 */
	private $initialState;
	/**
	 * @var NotificationTrackerService
	 */
	private $service;
	/**
	 * @var IUserSession
	 */
	private $userSession;

	public function __construct(
		IInitialState $initialState,
		NotificationTrackerService $service,
		IUserSession $userSession
	) {
		$this->initialState = $initialState;
		$this->service = $service;
		$this->userSession = $userSession;
	}

	/**
	 * @return TemplateResponse
	 */
	public function getForm(): TemplateResponse {
		$this->initialState->provideInitialState('opted-out', $this->service->find($this->userSession->getUser()->getUID())->getOptedOut());
		return new TemplateResponse('monthly_status_email', 'settings-personal', []);
	}

	/** {@inheritDoc} */
	public function getSection(): string {
		return 'personal-info';
	}

	/** {@inheritDoc} */
	public function getPriority(): int {
		return 0;
	}
}
