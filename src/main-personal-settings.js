/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import Vue from 'vue'
import PersonalSettings from './components/PersonalSettings.vue'

Vue.prototype.t = t
Vue.prototype.n = n
Vue.prototype.OC = OC
Vue.prototype.OCA = OCA

const View = Vue.extend(PersonalSettings)
const setting = new View()
setting.$mount('#monthly-notifications-settings')
