/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { randUser } from '../../utils/index.js'
const user = randUser()

describe('Personal settings', () => {
	let user1

	beforeEach(() => {
		cy.createRandomUser()
			.then(_user => {
				user1 = _user
			})

		cy.login(user1)
	})

	it('Toggle monthly_status_email settings', () => {
		cy.visit('/settings/user/notifications')
		cy.get('#monthly-notifications-settings')
			.should('contain', 'Monthly Status Email')
		cy.get('#monthly-notifications-settings input#send-notifications')
			.should('be.checked')
		cy.get('#monthly-notifications-settings label[for="send-notifications"]')
			.click()
		cy.get('#monthly-notifications-settings input#send-notifications')
			.should('not.be.checked')

		cy.reload()

		cy.get('#monthly-notifications-settings input#send-notifications')
			.should('not.be.checked')
	})
})
