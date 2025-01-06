<?php

/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\MonthlyStatusEmail\Service;

use OC\Files\View;
use OCP\IUser;

class NoFileUploadedDetector {
	public function hasNotUploadedFiles(IUser $user): bool {
		try {
			$view = new View('/' . $user->getUID() . '/files/');
		} catch (\Exception $e) {
			return false;
		}
		$directoryContents = $view->getDirectoryContent('');
		echo count($directoryContents);
		return count($directoryContents) === 0;
	}
}
