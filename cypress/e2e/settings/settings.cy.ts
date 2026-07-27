/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

describe('Personal settings', () => {
	beforeEach(() => {
		cy.createRandomUser().then((user) => {
			cy.login(user)
		})
	})

	it('Toggle monthly_status_email settings', () => {
		cy.visit('/settings/user/notifications')
		cy.get('#monthly-notifications-settings')
			.should('contain', 'Monthly Status Email')

		cy.findByRole('checkbox', { name: 'Send status email' })
			.should('be.checked')
			.click({ force: true })

		cy.findByRole('checkbox', { name: 'Send status email' })
			.should('not.be.checked')

		cy.reload()
		cy.findByRole('checkbox', { name: 'Send status email' })
			.should('not.be.checked')
	})
})
