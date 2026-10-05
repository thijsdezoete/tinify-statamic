import ApiCredits from './components/ApiCredits.vue';
import CompressLibrary from './components/CompressLibrary.vue';

Statamic.booting(() => {
    Statamic.$components.register('tinify_api_credits-fieldtype', ApiCredits);
    Statamic.$components.register('tinify_compress_library-fieldtype', CompressLibrary);
});
