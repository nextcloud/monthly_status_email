/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { createAppConfig } from '@nextcloud/vite-config'

export default createAppConfig({
	personalSettings: 'src/main-personal-settings.ts',
	publicOptout: 'src/main-public-optout.ts',
})
