<template>
	<div id="monthly-notifications-settings" class="section">
		<h2>{{ t('settings', 'Monthly summary notifications') }}</h2>
		<p class="settings-hint">
			{{ t('settings', 'Here you can decide which group can access some of the admin settings.') }}
		</p>
		<p>
			<input id="send-notifications"
				v-model="sendNotifications"
				type="checkbox"
				class="checkbox">
			<label for="send-notifications">{{ t('monthly_notifications', 'Send monthly summary') }}</label>
		</p>
	</div>
</template>

<script>
import { generateUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import { loadState } from '@nextcloud/initial-state'

export default {
	name: 'PersonalSettings',
	data() {
		return {
			sendNotifications: !loadState('monthly_notifications', 'opted-out', false),
		}
	},
	watch: {
		sendNotifications() {
			this.saveSetting()
		},
	},
	methods: {
		async saveSetting() {
			const data = {
				optedOut: !this.sendNotifications,
			}
			await axios.post(generateUrl('/apps/monthly_notifications/') + 'update', data)
		},
	},
}
</script>
