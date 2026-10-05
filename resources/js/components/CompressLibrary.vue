<script setup>
import { computed, getCurrentInstance, ref } from 'vue';
import { Fieldtype } from '@statamic/cms';
import { Button, Checkbox, ConfirmationModal } from '@statamic/cms/ui';

const props = defineProps(Fieldtype.props);
const { $axios } = getCurrentInstance().proxy;
const force = ref(false);
const confirming = ref(false);
const busy = ref(false);
const message = ref('');
const error = ref('');

const confirmation = computed(() => force.value
    ? __('Re-compress every supported image you can edit, including already checked images? This covers all containers and uses additional Tinify quota. Images are rewritten using saved settings, including format conversion.')
    : __('Compress supported images you can edit across all containers? Already checked images will be skipped. This uses Tinify quota and rewrites images using saved settings, including format conversion.'));

async function compress() {
    if (busy.value) return;

    busy.value = true;
    message.value = '';
    error.value = '';

    try {
        const { data } = await $axios.post(props.meta.url, { force: force.value });
        message.value = data.message;

        if (data.skipped > 0) {
            message.value += ' ' + __(':count already checked images skipped.', { count: data.skipped });
        }

        Statamic.$toast.success(message.value);
    } catch (exception) {
        const errors = Object.values(exception.response?.data?.errors ?? {}).flat();
        error.value = errors[0] || exception.response?.data?.message || __('The compression request failed. Check the connection and server logs before trying again.');
        Statamic.$toast.error(error.value);
    } finally {
        busy.value = false;
        confirming.value = false;
    }
}
</script>

<template>
    <div class="space-y-4">
        <p class="text-sm text-gray-600 dark:text-gray-300">
            {{ __('Compress JPEG, PNG, WebP, AVIF and SVG images across all containers, including those excluded from automatic optimization. Only images you can edit are included.') }}
        </p>
        <p class="text-sm text-gray-600 dark:text-gray-300">
            {{ __('Save any settings changes before running. Compression uses your Tinify quota and the site’s normal queue; use a background queue worker for large libraries.') }}
        </p>
        <Checkbox
            v-model="force"
            :disabled="busy"
            :label="__('Re-compress already checked images')"
            :description="__('Off by default to avoid spending quota on images already processed.')"
        />
        <Button
            variant="primary"
            :loading="busy"
            :text="__('Compress entire Asset library')"
            @click="confirming = true"
        />
        <p v-if="message" role="status" class="text-sm">{{ message }}</p>
        <p v-if="error" role="alert" class="text-sm text-red-600 dark:text-red-400">{{ error }}</p>
        <ConfirmationModal
            v-model:open="confirming"
            :title="__('Compress entire Asset library')"
            :body-text="confirmation"
            :button-text="__('Start compression')"
            :busy="busy"
            :cancellable="!busy"
            @confirm="compress"
        />
    </div>
</template>
