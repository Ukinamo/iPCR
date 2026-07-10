<script setup>
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import UserAvatar from '@/Components/UserAvatar.vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

defineProps({
    status: {
        type: String,
    },
});

const page = usePage();
const user = computed(() => page.props.auth.user);
const previewUrl = ref(null);
const fileInput = ref(null);

const form = useForm({
    photo: null,
});

const displayPhotoUrl = computed(() => previewUrl.value ?? user.value.profile_photo_url);

function selectPhoto() {
    fileInput.value?.click();
}

function onFileChange(event) {
    const file = event.target.files?.[0];

    if (!file) {
        return;
    }

    if (previewUrl.value) {
        URL.revokeObjectURL(previewUrl.value);
    }

    previewUrl.value = URL.createObjectURL(file);
    form.photo = file;
}

function uploadPhoto() {
    form.post(route('profile.photo.update'), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            if (previewUrl.value) {
                URL.revokeObjectURL(previewUrl.value);
                previewUrl.value = null;
            }

            form.reset();
            if (fileInput.value) {
                fileInput.value.value = '';
            }
        },
    });
}

function removePhoto() {
    router.delete(route('profile.photo.destroy'), {
        preserveScroll: true,
        onSuccess: () => {
            if (previewUrl.value) {
                URL.revokeObjectURL(previewUrl.value);
                previewUrl.value = null;
            }

            form.reset();
            if (fileInput.value) {
                fileInput.value.value = '';
            }
        },
    });
}
</script>

<template>
    <section>
        <header>
            <h2 class="text-lg font-medium text-gray-900">Profile Photo</h2>
            <p class="mt-1 text-sm text-gray-600">
                Upload a profile picture. JPG, PNG, or WebP up to 2 MB.
            </p>
        </header>

        <div class="mt-6 flex flex-wrap items-center gap-6">
            <UserAvatar
                :name="user.name"
                :photo-url="displayPhotoUrl"
                size="lg"
            />

            <div class="space-y-3">
                <input
                    ref="fileInput"
                    type="file"
                    accept="image/jpeg,image/png,image/webp"
                    class="hidden"
                    @change="onFileChange"
                />

                <div class="flex flex-wrap gap-2">
                    <SecondaryButton type="button" @click="selectPhoto">
                        Choose photo
                    </SecondaryButton>

                    <PrimaryButton
                        v-if="form.photo"
                        type="button"
                        :disabled="form.processing"
                        @click="uploadPhoto"
                    >
                        Save photo
                    </PrimaryButton>

                    <SecondaryButton
                        v-if="user.profile_photo_url"
                        type="button"
                        @click="removePhoto"
                    >
                        Remove
                    </SecondaryButton>
                </div>

                <InputError :message="form.errors.photo" />

                <p
                    v-if="status === 'profile-photo-updated'"
                    class="text-sm font-medium text-green-600"
                >
                    Profile photo updated.
                </p>

                <p
                    v-if="status === 'profile-photo-removed'"
                    class="text-sm font-medium text-green-600"
                >
                    Profile photo removed.
                </p>
            </div>
        </div>
    </section>
</template>
