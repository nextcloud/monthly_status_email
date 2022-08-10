<?php


namespace OCA\MonthlyStatusEmail\Service;

use OCP\IUser;
use OCP\Files\IRootFolder;
use OC\Files\View;

class StorageInfoProvider {
	/**
	 * Calculate the disc space for the given path
	 *
	 * @throws \OCP\Files\NotFoundException
	 */
	public function getStorageInfo(IUser $user): array {
		\OC_Util::setupFS($user->getUID());

		/** @var IRootFolder $rootFolder */
		$rootFolder = \OC::$server->get(IRootFolder::class);
		$userFolder = $rootFolder->getUserFolder($user->getUID());

		if (!($userFolder instanceof \OCP\Files\FileInfo)) {
			throw new \OCP\Files\NotFoundException();
		}
		$used = $userFolder->getSize(true);
		if ($used < 0) {
			$used = 0;
		}
		$quota = \OCP\Files\FileInfo::SPACE_UNLIMITED;
		$mount = $userFolder->getMountPoint();
		$storage = $mount->getStorage();
		$sourceStorage = $storage;
		$internalPath = $userFolder->getInternalPath();
		if ($sourceStorage->instanceOfStorage('\OC\Files\Storage\Wrapper\Quota')) {
			/** @var \OC\Files\Storage\Wrapper\Quota $storage */
			$quota = $sourceStorage->getQuota();
		}

		$free = $sourceStorage->free_space($internalPath);
		if ($free >= 0) {
			$total = $free + $used;
		} else {
			$total = $free; //either unknown or unlimited
		}
		if ($total > 0) {
			if ($quota > 0 && $total > $quota) {
				$total = $quota;
			}
			// prevent division by zero or error codes (negative values)
			$relative = round(($used / $total) * 10000) / 100;
		} else {
			$relative = 0;
		}

		$info = [
			'free' => $free,
			'used' => $used,
			'quota' => (int)$quota,
			'total' => $total,
			'relative' => $relative,
		];

		return $info;
	}
}
