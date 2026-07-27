/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { recommendedVue2Javascript } from '@nextcloud/eslint-config'
import { defineConfig } from 'eslint/config'
import CypressEslint from 'eslint-plugin-cypress'

export default defineConfig([
	...recommendedVue2Javascript,

	{
		...CypressEslint.configs.recommended,
		files: ['cypress/**', '**/*.cy.*'],
	},
])
