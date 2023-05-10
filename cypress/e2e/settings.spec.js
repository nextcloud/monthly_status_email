import { randUser } from '../utils/index.js'
const user = randUser()

describe('Personal settings', function() {
	before(function() {
		cy.createUser(user)
	})

	beforeEach(function() {
		cy.login(user)
	})

	it('Toggle monthly_status_email settings', function() {
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
