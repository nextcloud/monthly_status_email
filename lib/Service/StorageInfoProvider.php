<?php


namespace OCA\MonthlyStatusEmail\Service;

use OCP\IUser;

class StorageInfoProvider {
	public function getStorageInfo(IUser $user): array {
		// make sure FS is setup before querying storage related stuff...
		\OC_Util::setupFS($user->getUID());

		// Handle storage specific events
		return \OC_Helper::getStorageInfo('/');
	}
}
