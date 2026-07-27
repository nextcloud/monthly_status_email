<!--
  - SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<NcSettingsSection
		:name="t('monthly_status_email', 'Monthly Status Email')"
		:description="t('monthly_status_email', 'Receive monthly status mails with a summary of usaged storage and usage hints')">
		<NcCheckboxRadioSwitch v-model="sendNotifications" type="switch">
			{{ t('monthly_status_email', 'Send status email') }}
		</NcCheckboxRadioSwitch>
	</NcSettingsSection>
</template>

<script setup lang="ts">
import axios from '@nextcloud/axios'
import { loadState } from '@nextcloud/initial-state'
import { t } from '@nextcloud/l10n'
import { generateUrl } from '@nextcloud/router'
import { ref, watch } from 'vue'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcSettingsSection from '@nextcloud/vue/components/NcSettingsSection'

const sendNotifications = ref(!loadState('monthly_status_email', 'opted-out', false))

watch(sendNotifications, async (value) => {
	await axios.post(generateUrl('/apps/monthly_status_email/') + 'update', {
		optedOut: !value,
	})
})
</script>
