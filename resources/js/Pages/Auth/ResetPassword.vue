<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PasswordRequirements from '@/Components/PasswordRequirements.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, useForm } from '@inertiajs/vue3';

const props = defineProps({
    email: {
        type: String,
        default: '',
    },
    status: {
        type: String,
    },
    devOtp: {
        type: String,
        default: null,
    },
});

const form = useForm({
    email: props.email,
    otp: '',
    password: '',
    password_confirmation: '',
});

const resendForm = useForm({
    email: props.email,
});

const submit = () => {
    form.post(route('password.store'), {
        onFinish: () => form.reset('password', 'password_confirmation', 'otp'),
    });
};

const resendCode = () => {
    resendForm.email = form.email;
    resendForm.post(route('password.email'), {
        preserveScroll: true,
    });
};
</script>

<template>
    <GuestLayout :back-href="route('password.request')" back-label="Back">
        <Head title="Reset Password" />

        <div class="mb-4 text-sm text-gray-600">
            Enter the 6-digit code sent to your email, then choose a new password.
        </div>

        <div
            v-if="devOtp"
            class="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950"
        >
            <p class="font-semibold">Development verification code</p>
            <p class="mt-1 font-mono text-2xl tracking-[0.35em]">{{ devOtp }}</p>
            <p class="mt-2 text-amber-900/90">
                Mail is set to <span class="font-mono">log</span>, so the code is shown here instead of your inbox.
                To receive real emails, configure SMTP in your <span class="font-mono">.env</span> file.
            </p>
        </div>

        <div
            v-if="status"
            class="mb-4 text-sm font-medium text-green-600"
        >
            {{ status }}
        </div>

        <form @submit.prevent="submit">
            <div>
                <InputLabel for="email" value="Email" />

                <TextInput
                    id="email"
                    type="email"
                    class="mt-1 block w-full"
                    v-model="form.email"
                    required
                    autocomplete="username"
                />

                <InputError class="mt-2" :message="form.errors.email" />
            </div>

            <div class="mt-4">
                <div class="flex items-center justify-between">
                    <InputLabel for="otp" value="Verification code" />
                    <button
                        type="button"
                        class="text-sm font-medium text-blue-600 hover:text-blue-500 disabled:opacity-50"
                        :disabled="resendForm.processing || !form.email"
                        @click="resendCode"
                    >
                        Resend code
                    </button>
                </div>

                <TextInput
                    id="otp"
                    type="text"
                    inputmode="numeric"
                    pattern="[0-9]*"
                    maxlength="6"
                    class="mt-1 block w-full tracking-[0.35em]"
                    v-model="form.otp"
                    required
                    autofocus
                    autocomplete="one-time-code"
                    placeholder="000000"
                />

                <InputError class="mt-2" :message="form.errors.otp" />
            </div>

            <div class="mt-4">
                <InputLabel for="password" value="New password" />

                <TextInput
                    id="password"
                    type="password"
                    class="mt-1 block w-full"
                    v-model="form.password"
                    required
                    autocomplete="new-password"
                />

                <InputError class="mt-2" :message="form.errors.password" />
                <PasswordRequirements :password="form.password" show-checks />
            </div>

            <div class="mt-4">
                <InputLabel
                    for="password_confirmation"
                    value="Confirm password"
                />

                <TextInput
                    id="password_confirmation"
                    type="password"
                    class="mt-1 block w-full"
                    v-model="form.password_confirmation"
                    required
                    autocomplete="new-password"
                />

                <InputError
                    class="mt-2"
                    :message="form.errors.password_confirmation"
                />
            </div>

            <div class="mt-4 flex items-center justify-end">
                <PrimaryButton
                    :class="{ 'opacity-25': form.processing }"
                    :disabled="form.processing"
                >
                    Reset password
                </PrimaryButton>
            </div>
        </form>
    </GuestLayout>
</template>
