/*!
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: MIT
 */

import {
	configureNextcloud,
	runExec,
	startNextcloud,
	stopNextcloud,
	waitOnNextcloud,
} from '@nextcloud/e2e-test-server/docker'

const serverBranch = process.env.PLAYWRIGHT_NC_SERVER_BRANCH ?? 'master'

/**
 * Starts the Nextcloud server.
 */
async function start() {
	return await startNextcloud(serverBranch, true, {
		exposePort: 8089,
	})
}

/**
 * Stops the Nextcloud server and exits the process.
 */
async function stop() {
	process.stderr.write('Stopping Nextcloud server…\n')
	await stopNextcloud()
	process.exit(0)
}

process.on('SIGTERM', stop)
process.on('SIGINT', stop)

// Start the Nextcloud docker container
const ip = await start()
await waitOnNextcloud(ip)

// Install PHP composer
await runExec(
	['sh', '-c', 'curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer'],
	{ user: 'root', verbose: true },
)

// Clone notifications app and install PHP dependencies
await runExec(['git', 'clone', '--depth=1', `--branch=${serverBranch}`, 'https://github.com/nextcloud/notifications.git', 'apps/notifications'], { verbose: true })
await runExec(['sh', '-c', 'cd apps/notifications && composer install --no-dev --no-scripts --no-cache --no-interaction'], { verbose: true })

// Configure Nextcloud
await configureNextcloud(['monthly_status_email', 'notifications'])

// Idle to wait for shutdown
while (true) {
	await new Promise((resolve) => setTimeout(resolve, 5000))
}
