/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { recommended } from '@nextcloud/eslint-config'
import { defineConfig } from 'eslint/config'
import globals from 'globals'

export default defineConfig([
	...recommended,
	{
		files: ['playwright/**/*.js', 'playwright/**/*.ts'],
		languageOptions: {
			globals: {
				...globals.node,
			},
		},
	},
])
