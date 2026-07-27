/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { createRandomUser, login } from '@nextcloud/e2e-test-server/playwright'
import { expect, test } from '@playwright/test'

test.describe('Personal settings', () => {
	test.beforeEach(async ({ page }) => {
		const user = await createRandomUser()
		await login(page.request, user)
	})

	test('Toggle monthly_status_email settings', async ({ page }) => {
		await page.goto('/settings/user/notifications')
		const settings = page.locator('#monthly-notifications-settings')
		await expect(settings).toContainText('Monthly Status Email')

		const toggle = settings.getByRole('checkbox', { name: 'Send status email' })
		await expect(toggle).toBeChecked()
		await toggle.click({ force: true })
		await expect(toggle).not.toBeChecked()

		await page.reload()
		await expect(toggle).not.toBeChecked()
	})
})
