<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
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

const route = useRoute();
const router = useRouter();
const loading = ref(false);
const users = ref([]);
const meta = ref({});
const search = ref('');
const role = ref('');
const filtersReady = ref(false);
const deleting = ref(null);
const blocking = ref(null);
const blockType = ref('unblock');

const roleOptions = [
    { id: '', name: 'All roles' },
    { id: 'customer', name: 'Customer' },
    { id: 'worker', name: 'Worker' },
];

const chipBase = 'inline-flex items-center justify-center rounded-md px-2.5 py-1.5 text-xs font-semibold transition-all duration-150 hover:-translate-y-0.5 active:translate-y-0.5';
const neutralChip = `${chipBase} bg-blue-50 text-blue-700 shadow-[0_2px_0_#bfdbfe,0_6px_12px_rgba(37,99,235,0.12)] hover:bg-blue-100 active:shadow-[0_1px_0_#bfdbfe,0_4px_8px_rgba(37,99,235,0.12)] dark:bg-blue-500/10 dark:text-blue-300 dark:shadow-[0_2px_0_rgba(59,130,246,0.18)]`;
const warningChip = `${chipBase} bg-amber-50 text-amber-700 shadow-[0_2px_0_#fde68a,0_6px_12px_rgba(217,119,6,0.12)] hover:bg-amber-100 active:shadow-[0_1px_0_#fde68a,0_4px_8px_rgba(217,119,6,0.12)] dark:bg-amber-500/10 dark:text-amber-300 dark:shadow-[0_2px_0_rgba(251,191,36,0.18)]`;
const dangerChip = `${chipBase} bg-red-50 text-red-700 shadow-[0_2px_0_#fecaca,0_6px_12px_rgba(220,38,38,0.12)] hover:bg-red-100 active:shadow-[0_1px_0_#fecaca,0_4px_8px_rgba(220,38,38,0.12)] dark:bg-red-500/10 dark:text-red-300 dark:shadow-[0_2px_0_rgba(248,113,113,0.18)]`;

const blockDialogTitle = computed(() => {
    if (!blocking.value) {
        return '';
    }

    if (blockType.value === 'unblock') {
        return 'Unblock user';
    }

    return blockType.value === 'partially_blocked' ? 'Partial block user' : 'Full block user';
});

const blockDialogMessage = computed(() => {
    if (!blocking.value) {
        return '';
    }

    if (blockType.value === 'unblock') {
        return `Allow ${blocking.value.name || 'this user'} to return to an active account status?`;
    }

    if (blockType.value === 'partially_blocked') {
        return blocking.value.role?.slug === 'worker'
            ? `Partially block ${blocking.value.name || 'this worker'}? They will keep account access, but pending requests will be cancelled and they will stop receiving or starting new work.`
            : `Partially block ${blocking.value.name || 'this user'}? They will keep account access, but open booking requests and pending booking work will be cancelled.`;
    }

    const activeBookingsCount = blocking.value.active_worker_bookings_count || 0;

    if (activeBookingsCount > 0) {
        return `Fully block ${blocking.value.name || 'this worker'}? This will reset email and worker verification, cancel future bookings, notify customers, and send paid bookings for refund review.`;
    }

    return `Fully block ${blocking.value.name || 'this user'}? This will reset email verification and require admin approval before the account can fully recover.`;
});

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
    () => [search.value, role.value],
    () => {
        if (! filtersReady.value) {
            return;
        }

        syncFiltersToRoute();
        load();
    },
);

function openBlockDialog(user, nextBlockType = 'unblock') {
    blocking.value = user;
    blockType.value = nextBlockType;
}

async function confirmBlockToggle() {
    if (!blocking.value) {
        return;
    }

    const user = blocking.value;

    try {
        if (blockType.value === 'unblock') {
            await unblockAdminUser(user.id);
            toast.success('User unblocked');
        } else {
            await blockAdminUser(user.id, blockType.value);
            toast.success(blockType.value === 'partially_blocked' ? 'User partially blocked' : 'User fully blocked');
        }

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

function applyRouteFilters() {
    if (route.query.search !== undefined) {
        search.value = String(route.query.search);
    }

    if (route.query.role !== undefined) {
        role.value = String(route.query.role);
    }
}

function syncFiltersToRoute() {
    router.replace({
        path: route.path,
        query: {
            ...(search.value ? { search: search.value } : {}),
            ...(role.value ? { role: role.value } : {}),
        },
    });
}

onMounted(() => {
    applyRouteFilters();
    filtersReady.value = true;
    load();
});
</script>

<template>
    <AdminLayout title="Users Management">
        <div class="space-y-4" data-testid="admin-users-page">
            <div class="grid gap-3 md:grid-cols-[1fr_220px]">
                <SearchFilter v-model="search" placeholder="Search name or email" @search="load()" />
                <FormSelect id="role_filter" v-model="role" label="Role" :options="roleOptions" />
            </div>

            <AdminTable :columns="[{ key: 'user', label: 'User' }, { key: 'role', label: 'Role' }, { key: 'email_status', label: 'Email status' }, { key: 'account_status', label: 'Account status' }, { key: 'admin_approval', label: 'Admin approval' }]" :loading="loading" :has-records="users.length > 0">
                <tr v-for="user in users" :key="user.id" :data-testid="`admin-user-row-${user.id}`">
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
                    <td class="px-4 py-3"><StatusBadge :value="user.account_status" /></td>
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
                                :data-testid="`admin-user-verify-${user.id}`"
                                :class="neutralChip"
                                @click="verifyUser(user)"
                            >
                                Verify
                            </button>
                            <button v-if="user.account_status === 'active'" :data-testid="`admin-user-partial-block-${user.id}`" :class="warningChip" @click="openBlockDialog(user, 'partially_blocked')">Partial block</button>
                            <button v-if="user.account_status === 'active' || user.account_status === 'partially_blocked'" :data-testid="`admin-user-full-block-${user.id}`" :class="dangerChip" @click="openBlockDialog(user, 'fully_blocked')">Full block</button>
                            <button v-if="user.account_status !== 'active'" :data-testid="`admin-user-unblock-${user.id}`" :class="neutralChip" @click="openBlockDialog(user, 'unblock')">Unblock</button>
                            <button :data-testid="`admin-user-delete-${user.id}`" :class="dangerChip" @click="deleting = user">Delete</button>
                        </div>
                    </td>
                </tr>
            </AdminTable>

            <PaginationControls :meta="meta" @change="load" />
        </div>

        <ConfirmDialog
            :open="Boolean(blocking)"
            :title="blockDialogTitle"
            :message="blockDialogMessage"
            @cancel="blocking = null"
            @confirm="confirmBlockToggle"
        />

        <ConfirmDialog :open="Boolean(deleting)" title="Delete user" message="This user account will be deleted." @cancel="deleting = null" @confirm="confirmDelete" />
    </AdminLayout>
</template>
