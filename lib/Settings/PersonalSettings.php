<?php

/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\MonthlyStatusEmail\Settings;

use OCA\MonthlyStatusEmail\Service\NotificationTrackerService;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IInitialState;
use OCP\IConfig;
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
	/**
	 * @var string
	 */
	private $ncVersion;

	public function __construct(
		IInitialState $initialState,
		NotificationTrackerService $service,
		IUserSession $userSession,
		IConfig $config
	) {
		$this->initialState = $initialState;
		$this->service = $service;
		$this->userSession = $userSession;
		$this->ncVersion = $config->getSystemValueString('version', '0.0.0');
	}

	/**
	 * @return TemplateResponse
	 */
	public function getForm(): TemplateResponse {
		if (null !== $user = $this->userSession->getUser()) {
			$this->initialState->provideInitialState(
				'opted-out',
				$this->service->find($user->getUID())->getOptedOut());
		}
		return new TemplateResponse('monthly_status_email', 'settings-personal', []);
	}

	/**
	 * @return string
	 */
	public function getSection(): string {
		if (version_compare($this->ncVersion, '23.0.0', '<')) {
			return 'activity';
		}
		return 'notifications';
	}

	/**
	 * @return int
	 */
	public function getPriority(): int {
		return 50;
	}
}
