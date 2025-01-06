/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

const path = require('path')
const webpackConfig = require('@nextcloud/webpack-vue-config')

webpackConfig.entry = {
	personalSettings: path.join(__dirname, 'src', 'main-personal-settings.js'),
	publicOptout: path.join(__dirname, 'src', 'main-public-optout.js'),
}

module.exports = webpackConfig
