/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { recommended } from '@nextcloud/eslint-config'
import CypressEslint from 'eslint-plugin-cypress/flat'
import { defineConfig } from 'eslint/config'

export default defineConfig([
	...recommended,

	{
		files: ['cypress/**'],
		...CypressEslint.configs.recommended,
	},
])
