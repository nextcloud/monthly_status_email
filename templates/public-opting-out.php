<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

/** @var \OCP\IL10N $l */
/** @var array $_ */

script('monthly_status_email', 'monthly_status_email-publicOptout');
?>
<div class="section">
	<h2><?php echo $l->t('Monthly status update'); ?></h2>

	<p><?php echo $l->t('You just unsubscribed from the monthly status update. You can any time subscribe again from your account.') ?></p>
</div>
