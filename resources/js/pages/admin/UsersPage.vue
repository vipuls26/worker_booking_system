<script setup>
import { onMounted, ref } from 'vue';
import { toast } from 'vue-sonner';
import { adminUsers, blockAdminUser, deleteAdminUser, unblockAdminUser } from '../../api/admin';
import AdminTable from '../../components/admin/AdminTable.vue';
import PaginationControls from '../../components/admin/PaginationControls.vue';
import ConfirmDialog from '../../components/common/ConfirmDialog.vue';
import StatusBadge from '../../components/common/StatusBadge.vue';
import FormSelect from '../../components/forms/FormSelect.vue';
import SearchFilter from '../../components/forms/SearchFilter.vue';
import AdminLayout from '../../layouts/AdminLayout.vue';

const loading = ref(false);
const users = ref([]);
const meta = ref({});
const search = ref('');
const role = ref('');
const deleting = ref(null);
const roleOptions = [
    { id: '', name: 'All roles' },
    { id: 'customer', name: 'Customer' },
    { id: 'worker', name: 'Worker' },
];

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

async function toggleBlock(user) {
    user.is_blocked ? await unblockAdminUser(user.id) : await blockAdminUser(user.id);
    toast.success(user.is_blocked ? 'User unblocked' : 'User blocked');
    await load(meta.value.current_page || 1);
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
                <FormSelect id="role_filter" v-model="role" label="Role" :options="roleOptions" @update:model-value="load()" />
            </div>
            <AdminTable :columns="[{ key: 'user', label: 'User' }, { key: 'role', label: 'Role' }, { key: 'status', label: 'Status' }]" :loading="loading" :has-records="users.length > 0">
                <tr v-for="user in users" :key="user.id">
                    <td class="px-4 py-3">
                        <p class="font-medium text-gray-900 dark:text-white">{{ user.name }}</p>
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ user.email }}</p>
                    </td>
                    <td class="px-4 py-3 text-sm capitalize text-gray-700 dark:text-gray-200">{{ user.role?.slug }}</td>
                    <td class="px-4 py-3"><StatusBadge :value="user.is_blocked ? 'blocked' : 'active'" /></td>
                    <td class="px-4 py-3 text-right">
                        <button class="text-sm font-medium text-gray-900 dark:text-white" @click="toggleBlock(user)">{{ user.is_blocked ? 'Unblock' : 'Block' }}</button>
                        <button class="ml-3 text-sm font-medium text-red-600" @click="deleting = user">Delete</button>
                    </td>
                </tr>
            </AdminTable>
            <PaginationControls :meta="meta" @change="load" />
        </div>
        <ConfirmDialog :open="Boolean(deleting)" title="Delete user" message="This user account will be deleted." @cancel="deleting = null" @confirm="confirmDelete" />
    </AdminLayout>
</template>
