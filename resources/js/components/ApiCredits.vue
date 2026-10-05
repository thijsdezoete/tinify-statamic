<script setup>
import { getCurrentInstance, onMounted, ref } from 'vue';
import { Fieldtype } from '@statamic/cms';
import { Button } from '@statamic/cms/ui';

const props = defineProps(Fieldtype.props);
const { $axios } = getCurrentInstance().proxy;
const used = ref(null);
const remaining = ref(null);
const busy = ref(false);
const error = ref('');

async function refresh() {
    if (busy.value) return;

    busy.value = true;
    used.value = null;
    remaining.value = null;
    error.value = '';

    try {
        const { data } = await $axios.get(props.meta.url);

        if (!data.keyValid) {
            error.value = __('Unable to retrieve credits. Check the saved API key and your Tinify account, then try again.');
        } else {
            used.value = data.compressionCount;
            remaining.value = data.remainingCredits;

            if (used.value === null || remaining.value === null) {
                error.value = __('Tinify did not return all credit information. Try refreshing later.');
            }
        }
    } catch {
        error.value = __('Unable to retrieve credits. Check the connection and try refreshing again.');
    } finally {
        busy.value = false;
    }
}

onMounted(refresh);
</script>

<template>
    <div class="space-y-2">
        <div class="flex flex-wrap items-center gap-3">
            <div role="status" aria-live="polite">
                <p v-if="busy">{{ __('Checking Tinify…') }}</p>
                <dl v-else class="space-y-1">
                    <div class="flex items-baseline gap-2">
                        <dt>{{ __('Credits remaining') }}</dt>
                        <dd class="font-semibold">{{ remaining === null ? __('Unavailable') : remaining.toLocaleString() }}</dd>
                    </div>
                    <div class="flex items-baseline gap-2">
                        <dt>{{ __('Used this month') }}</dt>
                        <dd>{{ used === null ? __('Unavailable') : used.toLocaleString() }}</dd>
                    </div>
                </dl>
            </div>
            <Button :loading="busy" :text="__('Refresh')" @click="refresh" />
        </div>
        <p v-if="error" role="alert" class="text-sm text-red-600 dark:text-red-400">{{ error }}</p>
        <p class="text-sm text-gray-600 dark:text-gray-300">
            {{ __('Account-wide balance reported by Tinify, including assigned credits. Save API key changes before refreshing.') }}
        </p>
    </div>
</template>
