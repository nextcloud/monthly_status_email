/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'

(function() {
	const queryString = window.location.search
	const urlParams = new URLSearchParams(queryString)

	axios.post(generateUrl('/apps/monthly_status_email/') + 'optout', {
		token: urlParams.get('token'),
	})
})()
