<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import PasswordUpdatePanel from '../../components/account/PasswordUpdatePanel.vue';
import AppButton from '../../components/common/AppButton.vue';
import SkeletonCard from '../../components/common/SkeletonCard.vue';
import FormInput from '../../components/forms/FormInput.vue';
import { useApiErrors } from '../../composables/useApiErrors';
import { useYupValidation } from '../../composables/useYupValidation';
import DashboardLayout from '../../layouts/DashboardLayout.vue';
import { getWorkerVerification, submitWorkerVerification } from '../../api/worker/verification';
import { useWorkerProfileStore } from '../../stores/worker/profile';
import { workerProfileSchema } from '../../validation/profileSchemas';

const profileStore = useWorkerProfileStore();
const { errors, setApiError, clearApiErrors } = useApiErrors();
const { validationErrors, clearValidationErrors, validateWithSchema } = useYupValidation(workerProfileSchema);
const photoPreview = ref('');
const objectUrl = ref('');
const verification = ref(null);
const verificationSubmitting = ref(false);
const verificationIdProof = ref(null);
const verificationCertificates = ref([]);
const workerEmail = computed(() => profileStore.profile?.user?.email || '');

const form = reactive({
    profile_photo: null,
    bio: '',
    experience_years: 0,
    address: '',
    city: '',
    skills_text: '',
    phone: '',
});

const skills = computed(() => form.skills_text.split(','));
const profileCompletionChecks = computed(() => [
    { label: 'Profile photo', done: Boolean(photoPreview.value) },
    { label: 'Bio added', done: Boolean(form.bio.trim()) },
    { label: 'City and address', done: Boolean(form.city.trim() && form.address.trim()) },
    { label: 'Phone number', done: Boolean(form.phone.trim()) },
    { label: 'Skills listed', done: Boolean(skills.value.map((skill) => skill.trim()).filter(Boolean).length) },
    { label: 'Experience set', done: Number(form.experience_years || 0) > 0 },
    { label: 'Verification submitted', done: Boolean(verification.value?.id_proof_url || verificationIdProof.value) },
]);
const completedProfileChecks = computed(() => profileCompletionChecks.value.filter((item) => item.done).length);
const profileCompletionPercent = computed(() => Math.round((completedProfileChecks.value / profileCompletionChecks.value.length) * 100));
const completionToneClass = computed(() => {
    if (profileCompletionPercent.value >= 100) {
        return 'bg-emerald-500';
    }

    if (profileCompletionPercent.value >= 60) {
        return 'bg-blue-500';
    }

    return 'bg-amber-500';
});
const verificationStatusClasses = computed(() => {
    const status = verification.value?.status || 'pending';

    if (status === 'approved') {
        return 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300';
    }

    if (status === 'rejected') {
        return 'bg-red-50 text-red-700 dark:bg-red-500/10 dark:text-red-300';
    }

    return 'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-300';
});
const hasSubmittedProof = computed(() => Boolean(verification.value?.id_proof_url || verificationIdProof.value));
const verificationButtonText = computed(() => {
    if (verificationSubmitting.value) {
        return 'Submitting...';
    }

    return verification.value ? 'Submit updates' : 'Submit verification';
});

function fillForm(profile) {
    form.profile_photo = null;
    form.bio = profile?.bio || '';
    form.experience_years = profile?.experience_years || 0;
    form.address = profile?.address || '';
    form.city = profile?.city || '';
    form.skills_text = (profile?.skills || []).join(', ');
    form.phone = profile?.user?.phone || '';
    photoPreview.value = profile?.profile_photo_url || '';
}

function handlePhotoChange(event) {
    const file = event.target.files?.[0] || null;
    form.profile_photo = file;

    if (objectUrl.value) {
        URL.revokeObjectURL(objectUrl.value);
    }

    objectUrl.value = file ? URL.createObjectURL(file) : '';
    photoPreview.value = objectUrl.value || profileStore.profile?.profile_photo_url || '';
}

async function submit() {
    clearApiErrors();
    clearValidationErrors();

    const isValid = await validateWithSchema(form);

    if (! isValid) {
        toast.error('Please fix the highlighted worker profile fields.');

        return;
    }

    try {
        const response = await profileStore.update({
            ...form,
            skills: skills.value,
        });

        toast.success(response.message || 'Worker profile updated');
        fillForm(profileStore.profile);
    } catch (error) {
        setApiError(error);
        toast.error(error.response?.data?.message || 'Unable to update worker profile');
    }
}

function handleVerificationProofChange(event) {
    verificationIdProof.value = event.target.files?.[0] || null;
}

function handleVerificationCertificateChange(event) {
    verificationCertificates.value = Array.from(event.target.files || []);
}

async function loadVerification() {
    const response = await getWorkerVerification();
    verification.value = response.data.data.verification;
}

async function submitVerification() {
    if (! hasSubmittedProof.value) {
        toast.error('Please upload ID proof first');
        return;
    }

    clearApiErrors();
    verificationSubmitting.value = true;

    try {
        const payload = new FormData();
        if (verificationIdProof.value) {
            payload.append('id_proof', verificationIdProof.value);
        }

        payload.append('experience_years', form.experience_years || 0);
        payload.append('mobile_verified', '1');
        verificationCertificates.value.forEach((certificate) => {
            payload.append('certificates[]', certificate);
        });

        const response = await submitWorkerVerification(payload);
        verification.value = response.data.data.verification;
        verificationIdProof.value = null;
        verificationCertificates.value = [];
        await profileStore.fetch();
        toast.success(response.data.message || 'Verification submitted');
    } catch (error) {
        setApiError(error);
        toast.error(error.response?.data?.message || 'Unable to submit verification');
    } finally {
        verificationSubmitting.value = false;
    }
}

watch(() => profileStore.profile, fillForm);
watch(() => form.bio, () => clearValidationErrors('bio'));
watch(() => form.experience_years, () => clearValidationErrors('experience_years'));
watch(() => form.address, () => clearValidationErrors('address'));
watch(() => form.city, () => clearValidationErrors('city'));
watch(() => form.skills_text, () => clearValidationErrors('skills_text'));
watch(() => form.phone, () => clearValidationErrors('phone'));

onMounted(async () => {
    try {
        await profileStore.fetch();
        fillForm(profileStore.profile);
        await loadVerification();
    } catch {
        toast.error('Unable to load worker profile');
    }
});

onBeforeUnmount(() => {
    if (objectUrl.value) {
        URL.revokeObjectURL(objectUrl.value);
    }
});
</script>

<template>
    <DashboardLayout title="Worker Profile">
        <div v-if="profileStore.loading" class="grid gap-6 lg:grid-cols-[320px_1fr]">
            <SkeletonCard :lines="6" />
            <SkeletonCard :lines="9" :avatar="false" />
        </div>

        <form v-else class="grid gap-6 lg:grid-cols-[320px_1fr]" data-testid="worker-profile-page" @submit.prevent="submit">
            <section class="rounded-lg bg-white p-5 shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-white/10">
                <div class="flex flex-col items-center text-center">
                    <div class="flex size-36 items-center justify-center overflow-hidden rounded-lg bg-gray-100 text-gray-400 dark:bg-gray-950 dark:text-gray-500">
                        <img v-if="photoPreview" :src="photoPreview" alt="Worker profile preview" class="size-full object-cover">
                        <i v-else class="pi pi-user text-4xl" aria-hidden="true"></i>
                    </div>

                    <label class="mt-4 inline-flex cursor-pointer items-center gap-2 rounded-md border border-blue-200 bg-blue-50 px-4 py-2 text-sm font-medium text-blue-700 shadow-[0_3px_0_#bfdbfe,0_8px_16px_rgba(37,99,235,0.12)] transition-all duration-150 hover:-translate-y-0.5 hover:bg-blue-100 active:translate-y-0.5 active:shadow-[0_1px_0_#bfdbfe,0_5px_10px_rgba(37,99,235,0.12)] dark:border-white/10 dark:bg-white/5 dark:text-gray-200 dark:shadow-[0_3px_0_rgba(59,130,246,0.18)] dark:hover:bg-white/10">
                        <i class="pi pi-camera" aria-hidden="true"></i>
                        Upload photo
                        <input type="file" accept="image/*" class="sr-only" @change="handlePhotoChange">
                    </label>
                    <p v-if="errors.profile_photo?.length" class="mt-2 text-sm text-red-600 dark:text-red-400">{{ errors.profile_photo[0] }}</p>

                    <div class="mt-5 rounded-md bg-gray-50 px-4 py-3 text-sm text-gray-600 dark:bg-gray-950 dark:text-gray-300">
                        <p class="font-medium text-gray-900 dark:text-white">
                            {{ profileStore.profile?.is_verified ? 'Verified worker' : 'Verification pending' }}
                        </p>
                        <p class="mt-1">Keep your details accurate so customers can book the right local help.</p>
                    </div>

                    <div class="mt-5 rounded-lg border border-gray-200 p-4 text-left dark:border-white/10">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-sm font-semibold text-gray-900 dark:text-white">Profile completion</p>
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Complete every item to improve trust and reduce booking friction.</p>
                            </div>
                            <span class="rounded-full bg-gray-100 px-2.5 py-1 text-xs font-semibold text-gray-700 dark:bg-white/10 dark:text-gray-200">
                                {{ profileCompletionPercent }}%
                            </span>
                        </div>
                        <div class="mt-3 h-2 overflow-hidden rounded-full bg-gray-200 dark:bg-white/10">
                            <div class="h-full rounded-full transition-all" :class="completionToneClass" :style="{ width: `${profileCompletionPercent}%` }"></div>
                        </div>
                        <div class="mt-3 grid gap-2">
                            <div v-for="item in profileCompletionChecks" :key="item.label" class="flex items-center justify-between gap-3 rounded-md bg-gray-50 px-3 py-2 text-sm dark:bg-gray-950">
                                <span class="text-gray-700 dark:text-gray-200">{{ item.label }}</span>
                                <span
                                    class="rounded-full px-2 py-0.5 text-xs font-semibold"
                                    :class="item.done ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300' : 'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-300'"
                                >
                                    {{ item.done ? 'Done' : 'Needed' }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-5 rounded-lg border border-gray-200 p-4 text-left dark:border-white/10" data-testid="worker-verification-panel">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <h2 class="text-sm font-semibold text-gray-900 dark:text-white">Worker verification</h2>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Submit ID proof for admin approval.</p>
                        </div>
                        <span :class="['inline-flex shrink-0 rounded-full px-2.5 py-1 text-xs font-semibold capitalize', verificationStatusClasses]" data-testid="worker-verification-status">
                            {{ verification?.status || 'pending' }}
                        </span>
                    </div>

                    <p v-if="verification?.rejection_reason" class="mt-3 rounded-md bg-red-50 p-3 text-sm text-red-700 dark:bg-red-500/10 dark:text-red-300">
                        {{ verification.rejection_reason }}
                    </p>

                    <a
                        v-if="verification?.id_proof_url"
                        :href="verification.id_proof_url"
                        target="_blank"
                        rel="noreferrer"
                        class="mt-3 inline-flex items-center gap-2 text-sm font-medium text-gray-700 hover:text-gray-950 dark:text-gray-300 dark:hover:text-white"
                    >
                        <i class="pi pi-id-card" aria-hidden="true"></i>
                        View submitted proof
                    </a>

                    <div v-if="verification?.certificates?.length" class="mt-3 flex flex-wrap gap-2">
                        <a
                            v-for="(certificate, index) in verification.certificates"
                            :key="certificate.path"
                            :href="certificate.url"
                            target="_blank"
                            rel="noreferrer"
                            class="inline-flex items-center gap-2 rounded-md border border-gray-200 px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50 dark:border-white/10 dark:text-gray-300 dark:hover:bg-white/5"
                        >
                            <i class="pi pi-file" aria-hidden="true"></i>
                            Certificate {{ index + 1 }}
                        </a>
                    </div>

                    <div class="mt-4 space-y-3">
                        <label class="block">
                            <span class="text-sm font-medium text-gray-700 dark:text-gray-200">{{ verification?.id_proof_url ? 'Replace ID proof' : 'ID proof' }}</span>
                            <input
                                type="file"
                                accept=".jpg,.jpeg,.png,.pdf"
                                data-testid="worker-verification-id-proof"
                                class="mt-1 block w-full rounded-md border border-gray-300 bg-white text-sm text-gray-900 file:mr-3 file:border-0 file:bg-gray-900 file:px-3 file:py-2 file:text-sm file:font-semibold file:text-white dark:border-white/10 dark:bg-gray-950 dark:text-white dark:file:bg-white dark:file:text-gray-950"
                                @change="handleVerificationProofChange"
                            >
                            <span v-if="verificationIdProof" class="mt-1 block text-xs text-gray-500 dark:text-gray-400">{{ verificationIdProof.name }}</span>
                        </label>
                        <p v-if="errors.id_proof?.length" class="text-sm text-red-600 dark:text-red-400">{{ errors.id_proof[0] }}</p>

                        <label class="block">
                            <span class="text-sm font-medium text-gray-700 dark:text-gray-200">{{ verification?.certificates?.length ? 'Replace certificates' : 'Certificates optional' }}</span>
                            <input
                                type="file"
                                accept=".jpg,.jpeg,.png,.pdf"
                                multiple
                                data-testid="worker-verification-certificates"
                                class="mt-1 block w-full rounded-md border border-gray-300 bg-white text-sm text-gray-900 file:mr-3 file:border-0 file:bg-gray-900 file:px-3 file:py-2 file:text-sm file:font-semibold file:text-white dark:border-white/10 dark:bg-gray-950 dark:text-white dark:file:bg-white dark:file:text-gray-950"
                                @change="handleVerificationCertificateChange"
                            >
                            <span v-if="verificationCertificates.length" class="mt-1 block text-xs text-gray-500 dark:text-gray-400">
                                {{ verificationCertificates.map((certificate) => certificate.name).join(', ') }}
                            </span>
                        </label>
                        <p v-if="errors.certificates?.length" class="text-sm text-red-600 dark:text-red-400">{{ errors.certificates[0] }}</p>

                        <AppButton type="button" icon="pi-send" :loading="verificationSubmitting" data-testid="worker-verification-submit" @click="submitVerification">
                            {{ verificationButtonText }}
                        </AppButton>
                    </div>
                </div>
            </section>

            <section class="rounded-lg bg-white p-5 shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-white/10">
                <div class="mb-5">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Profile details</h2>
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Bio, service area, phone, address, and skills.</p>
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <div class="md:col-span-2">
                        <FormInput id="worker_email" :model-value="workerEmail" label="Email" type="email" readonly />
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">This email is tied to your login and verification flow.</p>
                    </div>
                    <FormInput id="worker_phone" v-model="form.phone" label="Phone" type="tel" autocomplete="tel" :error="validationErrors.phone || errors.phone || []" />
                    <FormInput id="worker_city" v-model="form.city" label="City" :error="validationErrors.city || errors.city || []" />
                    <FormInput id="worker_experience" v-model="form.experience_years" label="Experience years" type="number" min="0" max="60" step="1" :error="validationErrors.experience_years || errors.experience_years || []" />
                    <FormInput id="worker_skills" v-model="form.skills_text" label="Skills" :error="validationErrors.skills_text || errors.skills || []" />
                </div>

                <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">Separate multiple skills with commas.</p>

                <div class="mt-4 grid gap-4">
                    <div>
                        <label for="worker_address" class="block text-sm font-medium text-gray-700 dark:text-gray-200">Address</label>
                        <textarea
                            id="worker_address"
                            v-model="form.address"
                            rows="3"
                            class="mt-1 block w-full rounded-md border-gray-300 bg-white text-gray-900 shadow-sm focus:border-gray-900 focus:ring-gray-900 dark:border-white/10 dark:bg-gray-950 dark:text-white dark:focus:border-white dark:focus:ring-white"
                        ></textarea>
                        <p v-if="(validationErrors.address || errors.address)?.length" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ (validationErrors.address || errors.address)[0] }}</p>
                    </div>

                    <div>
                        <label for="worker_bio" class="block text-sm font-medium text-gray-700 dark:text-gray-200">Bio</label>
                        <textarea
                            id="worker_bio"
                            v-model="form.bio"
                            rows="5"
                            class="mt-1 block w-full rounded-md border-gray-300 bg-white text-gray-900 shadow-sm focus:border-gray-900 focus:ring-gray-900 dark:border-white/10 dark:bg-gray-950 dark:text-white dark:focus:border-white dark:focus:ring-white"
                        ></textarea>
                        <p v-if="(validationErrors.bio || errors.bio)?.length" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ (validationErrors.bio || errors.bio)[0] }}</p>
                    </div>
                </div>

                <div class="mt-6 flex justify-end">
                    <div class="w-full sm:w-auto">
                        <AppButton type="submit" icon="pi-save" :loading="profileStore.saving">
                            {{ profileStore.saving ? 'Saving...' : 'Save profile' }}
                        </AppButton>
                    </div>
                </div>
            </section>
        </form>

        <div class="mt-6 xl:max-w-3xl">
            <PasswordUpdatePanel />
        </div>
    </DashboardLayout>
</template>
