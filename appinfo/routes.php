<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

/**
 * Create your routes in here. The name is the lowercase name of the controller
 * without the controller part, the stuff after the hash is the method.
 * e.g. page#index -> OCA\ServerInfo\Controller\PageController->index()
 *
 * The controller class has to be registered in the application.php file since
 * it's instantiated in there
 */

return [
	'routes' => [
		['name' => 'optout#update', 'url' => '/update', 'verb' => 'POST'],
		['name' => 'optout#displayOptingOutPage', 'url' => '/optout', 'verb' => 'GET'],
		['name' => 'optout#updateOptingOut', 'url' => '/optout', 'verb' => 'POST'],
	],
];
