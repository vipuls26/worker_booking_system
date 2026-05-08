<script setup>
import { onMounted, ref } from 'vue';
import { toast } from 'vue-sonner';
import { adminUsers, blockAdminUser, deleteAdminUser, unblockAdminUser, verifyAdminUser } from '../../api/admin';
import AdminTable from '../../components/admin/AdminTable.vue';
import PaginationControls from '../../components/admin/PaginationControls.vue';
import ConfirmDialog from '../../components/common/ConfirmDialog.vue';
import StatusBadge from '../../components/common/StatusBadge.vue';
import FormSelect from '../../components/forms/FormSelect.vue';
import SearchFilter from '../../components/forms/SearchFilter.vue';
import { useDebouncedWatch } from '../../composables/useDebouncedWatch';
import AdminLayout from '../../layouts/AdminLayout.vue';

const loading = ref(false);
const users = ref([]);
const meta = ref({});
const search = ref('');
const role = ref('');
const deleting = ref(null);
const blocking = ref(null);
const roleOptions = [
    { id: '', name: 'All roles' },
    { id: 'customer', name: 'Customer' },
    { id: 'worker', name: 'Worker' },
];
const chipBase = 'inline-flex items-center justify-center rounded-md px-2.5 py-1.5 text-xs font-semibold transition-all duration-150 hover:-translate-y-0.5 active:translate-y-0.5';
const neutralChip = `${chipBase} bg-blue-50 text-blue-700 shadow-[0_2px_0_#bfdbfe,0_6px_12px_rgba(37,99,235,0.12)] hover:bg-blue-100 active:shadow-[0_1px_0_#bfdbfe,0_4px_8px_rgba(37,99,235,0.12)] dark:bg-blue-500/10 dark:text-blue-300 dark:shadow-[0_2px_0_rgba(59,130,246,0.18)]`;
const dangerChip = `${chipBase} bg-red-50 text-red-700 shadow-[0_2px_0_#fecaca,0_6px_12px_rgba(220,38,38,0.12)] hover:bg-red-100 active:shadow-[0_1px_0_#fecaca,0_4px_8px_rgba(220,38,38,0.12)] dark:bg-red-500/10 dark:text-red-300 dark:shadow-[0_2px_0_rgba(248,113,113,0.18)]`;

async function load(page = 1) {
    loading.value = true;
    try {
        const response = await adminUsers({ search: search.value, role: role.value, page });
        users.value = response.data.data.users;
        meta.value = response.data.data.meta;
    } catch {
        toast.error('Unable to load users');
    } finally {
        loading.value = false;
    }
}

useDebouncedWatch(
    () => role.value,
    () => load(),
);

async function confirmBlockToggle() {
    if (!blocking.value) {
        return;
    }

    const user = blocking.value;

    try {
        user.is_blocked ? await unblockAdminUser(user.id) : await blockAdminUser(user.id);
        toast.success(user.is_blocked ? 'User unblocked' : 'User blocked');
        blocking.value = null;
        await load(meta.value.current_page || 1);
    } catch (error) {
        toast.error(error.response?.data?.message || 'Unable to update user status');
    }
}

async function verifyUser(user) {
    if (user.is_admin_verified) {
        return;
    }

    try {
        await verifyAdminUser(user.id);
        toast.success('User verified');
        await load(meta.value.current_page || 1);
    } catch (error) {
        toast.error(error.response?.data?.message || 'Unable to update verification');
    }
}

async function confirmDelete() {
    try {
        await deleteAdminUser(deleting.value.id);
        toast.success('User deleted');
        deleting.value = null;
        await load();
    } catch (error) {
        toast.error(error.response?.data?.message || 'Unable to delete user');
    }
}

onMounted(load);
</script>

<template>
    <AdminLayout title="Users Management">
        <div class="space-y-4">
            <div class="grid gap-3 md:grid-cols-[1fr_220px]">
                <SearchFilter v-model="search" placeholder="Search name or email" @search="load()" />
                <FormSelect id="role_filter" v-model="role" label="Role" :options="roleOptions" />
            </div>
            <AdminTable :columns="[{ key: 'user', label: 'User' }, { key: 'role', label: 'Role' }, { key: 'email_status', label: 'Email status' }, { key: 'account_status', label: 'Account status' }, { key: 'admin_approval', label: 'Admin approval' }]" :loading="loading" :has-records="users.length > 0">
                <tr v-for="user in users" :key="user.id">
                    <td class="px-4 py-3">
                        <p class="font-medium text-gray-900 dark:text-white">{{ user.name }}</p>
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ user.email }}</p>
                    </td>
                    <td class="px-4 py-3 text-sm capitalize text-gray-700 dark:text-gray-200">{{ user.role?.slug }}</td>
                    <td class="px-4 py-3">
                        <span
                            class="inline-flex rounded-full px-2 py-1 text-xs font-medium"
                            :class="user.email_verified_at
                                ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300'
                                : 'bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-300'"
                        >
                            {{ user.email_verified_at ? 'Email verified' : 'Email pending' }}
                        </span>
                    </td>
                    <td class="px-4 py-3"><StatusBadge :value="user.is_blocked ? 'blocked' : 'active'" /></td>
                    <td class="px-4 py-3">
                        <span
                            class="inline-flex rounded-full px-2 py-1 text-xs font-medium"
                            :class="user.is_admin_verified
                                ? 'bg-blue-100 text-blue-700 dark:bg-blue-950 dark:text-blue-300'
                                : 'bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-300'"
                        >
                            {{ user.is_admin_verified ? 'Admin approved' : 'Needs approval' }}
                        </span>
                    </td>
                    <td class="px-4 py-3 text-right">
                        <div class="flex flex-wrap justify-end gap-2">
                        <span
                            v-if="!user.is_admin_verified && !user.email_verified_at"
                            class="inline-flex items-center justify-center rounded-md bg-amber-50 px-2.5 py-1.5 text-xs font-semibold text-amber-700 dark:bg-amber-500/10 dark:text-amber-300"
                        >
                            Waiting email
                        </span>
                        <button
                            v-else-if="!user.is_admin_verified"
                            :class="neutralChip"
                            @click="verifyUser(user)"
                        >
                            Verify
                        </button>
                        <button :class="neutralChip" @click="blocking = user">{{ user.is_blocked ? 'Unblock' : 'Block' }}</button>
                        <button :class="dangerChip" @click="deleting = user">Delete</button>
                        </div>
                    </td>
                </tr>
            </AdminTable>
            <PaginationControls :meta="meta" @change="load" />
        </div>
        <ConfirmDialog
            :open="Boolean(blocking)"
            :title="blocking?.is_blocked ? 'Unblock user' : 'Block user'"
            :message="blocking?.is_blocked
                ? `Allow ${blocking?.name || 'this user'} to access platform features again?`
                : `Block ${blocking?.name || 'this user'} from protected platform features? Email verification and admin approval will both reset. After unblock, they must verify email and wait for admin approval again.`"
            @cancel="blocking = null"
            @confirm="confirmBlockToggle"
        />
        <ConfirmDialog :open="Boolean(deleting)" title="Delete user" message="This user account will be deleted." @cancel="deleting = null" @confirm="confirmDelete" />
    </AdminLayout>
</template>
