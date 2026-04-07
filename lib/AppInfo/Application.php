<?php

/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\MonthlyStatusEmail\AppInfo;

use OCA\MonthlyStatusEmail\Listener\FirstLoginListener;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\User\Events\UserFirstTimeLoggedInEvent;

class Application extends App implements IBootstrap {
	public const APP_ID = 'monthly_status_email';

	public function __construct() {
		parent::__construct(self::APP_ID);
	}

	public function register(IRegistrationContext $context): void {
		$context->registerEventListener(UserFirstTimeLoggedInEvent::class, FirstLoginListener::class);
	}

	public function boot(IBootContext $context): void {
	}
}
