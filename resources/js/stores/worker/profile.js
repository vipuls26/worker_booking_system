import { defineStore } from 'pinia';
import { getWorkerProfile, updateWorkerProfile } from '../../api/worker/profile';
import { useAuthStore } from '../auth';

function appendIfFilled(formData, key, value) {
    if (value !== null && value !== undefined && value !== '') {
        formData.append(key, value);
    }
}

function toFormData(payload) {
    const formData = new FormData();

    appendIfFilled(formData, 'bio', payload.bio);
    appendIfFilled(formData, 'experience_years', payload.experience_years);
    appendIfFilled(formData, 'address', payload.address);
    appendIfFilled(formData, 'city', payload.city);
    appendIfFilled(formData, 'phone', payload.phone);

    if (payload.profile_photo instanceof File) {
        formData.append('profile_photo', payload.profile_photo);
    }

    const skills = payload.skills
        .map((skill) => skill.trim())
        .filter(Boolean);

    if (skills.length === 0) {
        formData.append('skills', '');
    } else {
        skills.forEach((skill) => formData.append('skills[]', skill));
    }

    return formData;
}

export const useWorkerProfileStore = defineStore('workerProfile', {
    state: () => ({
        profile: null,
        loading: false,
        saving: false,
    }),

    actions: {
        async fetch() {
            this.loading = true;

            try {
                const response = await getWorkerProfile();
                this.profile = response.data.data.profile;
                this.syncAuthUser();

                return response.data;
            } finally {
                this.loading = false;
            }
        },

        async update(payload) {
            this.saving = true;

            try {
                const response = await updateWorkerProfile(toFormData(payload));
                this.profile = response.data.data.profile;
                this.syncAuthUser();

                return response.data;
            } finally {
                this.saving = false;
            }
        },

        syncAuthUser() {
            if (! this.profile?.user) {
                return;
            }

            const authStore = useAuthStore();
            authStore.setUser(this.profile.user);
        },
    },
});
