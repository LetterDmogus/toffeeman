<script setup lang="ts">
import { Head } from "@inertiajs/vue3";
import { onMounted, ref } from "vue";
import type { Column, FormField } from "@/components/CRUDTable.vue";
import CRUDTable from "@/components/CRUDTable.vue";
import { ShieldCheck, Loader2, Lock } from "lucide-vue-next";
import { Button } from "@/components/ui/button";
import {
	Dialog,
	DialogContent,
	DialogDescription,
	DialogHeader,
	DialogTitle,
} from "@/components/ui/dialog";

defineOptions({
	layout: {
		breadcrumbs: [
			{ title: "Dashboard", href: "/dashboard" },
			{ title: "Manajemen Tim", href: "#" },
			{ title: "Hak Akses Role", href: route("team.roles-permissions") },
		],
	},
});

const columns: Column<any>[] = [
	{ 
		key: "name", 
		label: "Nama Role",
		render: (val: any) => {
			if (val === 'superadmin') return 'Super Admin';
			return String(val).charAt(0).toUpperCase() + String(val).slice(1).replace("-", " ");
		}
	},
	{ 
		key: "is_system", 
		label: "Tipe Role",
		render: (val: any, row: any) => {
			if (['superadmin', 'admin', 'user', 'customer'].includes(row.name)) {
				return "Bawaan Sistem";
			}
			return "Kustom";
		}
	},
];

const fields: FormField[] = [
	{ key: "name", label: "Nama Role", type: "text", required: true },
];

// Permission Settings State
const permissions = ref<any[]>([]);
const selectedRole = ref<any | null>(null);
const isPermissionsModalOpen = ref(false);
const savingPermissions = ref(false);
const crudTableRef = ref<any>(null);

const loadPermissions = async () => {
	try {
		const res = await fetch("/api/permissions", {
			headers: { Accept: "application/json" },
		});
		if (res.ok) {
			permissions.value = await res.json();
		}
	} catch (e) {
		console.error(e);
	}
};

onMounted(loadPermissions);

const openPermissionsModal = (row: any) => {
	selectedRole.value = { ...row };
	isPermissionsModalOpen.value = true;
};

const hasPermission = (permName: string): boolean => {
	if (!selectedRole.value || !selectedRole.value.permissions) {
		return false;
	}
	return selectedRole.value.permissions.some((p: any) => p.name === permName);
};

const togglePermission = async (permName: string) => {
	if (!selectedRole.value || selectedRole.value.name === 'superadmin' || savingPermissions.value) {
		return;
	}

	savingPermissions.value = true;
	let currentPerms = (selectedRole.value.permissions || []).map((p: any) => p.name);

	if (currentPerms.includes(permName)) {
		currentPerms = currentPerms.filter((p: any) => p !== permName);
	} else {
		currentPerms.push(permName);
	}

	try {
		const res = await fetch(`/api/roles/${selectedRole.value.id}/permissions`, {
			method: "POST",
			headers: {
				"Content-Type": "application/json",
				Accept: "application/json",
				"X-CSRF-TOKEN":
					(document.querySelector('meta[name="csrf-token"]') as any)?.content ||
					"",
			},
			body: JSON.stringify({
				permissions: currentPerms,
			}),
		});

		if (res.ok) {
			const updatedRole = await res.json();
			selectedRole.value = updatedRole;
			
			// Refresh parent CRUD table data
			if (crudTableRef.value && typeof crudTableRef.value.fetchData === 'function') {
				crudTableRef.value.fetchData();
			}
		}
	} catch (e) {
		console.error(e);
	} finally {
		savingPermissions.value = false;
	}
};

const formatRoleName = (name: string): string => {
	if (name === 'superadmin') return 'Super Admin';
	return name.charAt(0).toUpperCase() + name.slice(1).replace("-", " ");
};

const formatPermissionName = (name: string): string => {
	return name.replace("-", " ").toUpperCase();
};

const getPermissionDescription = (name: string): string => {
	const descMap: Record<string, string> = {
		'manage-catalog': 'Mengelola menu makanan, kategori, resep, addon, paket, dan diskon promo.',
		'manage-ops': 'Mengatur operasional harian meja, stok barang/peralatan, masuk-keluar barang, opname, dan bahan baku.',
		'manage-team': 'Menambahkan, memperbarui, mengatur jabatan, dan memblokir staf/karyawan serta hak akses user.',
		'view-reports': 'Melihat dashboard statistik, grafik penjualan, laba rugi, laporan kas, dan ringkasan performa.',
		'pos-access': 'Hak akses penuh ke layar Kasir (Point of Sale) untuk input pesanan dan pembayaran meja.',
		'kitchen-access': 'Hak akses penuh ke layar Dapur (Kitchen Display System) untuk monitor antrean memasak makanan.',
	};
	return descMap[name] || 'Deskripsi hak akses belum diatur.';
};
</script>

<template>
    <div class="p-6">
        <CRUDTable ref="crudTableRef" resource-name="Role" api-url="/api/roles" :columns="columns" :form-fields="fields">
            <template #actions="{ row }">
                <Button
                    size="icon-sm"
                    variant="ghost"
                    class="text-brand-500 hover:text-brand-600 hover:bg-brand-500/10 h-8 w-8"
                    @click="openPermissionsModal(row)"
                    title="Atur Hak Akses Role"
                >
                    <ShieldCheck class="h-4.5 w-4.5" />
                </Button>
            </template>
        </CRUDTable>

        <!-- Permissions Checkbox List Modal -->
        <Dialog :open="isPermissionsModalOpen" @update:open="isPermissionsModalOpen = $event">
            <DialogContent class="sm:max-w-[500px]">
                <DialogHeader>
                    <div class="flex items-center justify-between w-full">
                        <div class="flex items-center gap-2">
                            <ShieldCheck class="h-5 w-5 text-brand-500" />
                            <DialogTitle class="text-sm font-bold text-foreground">
                                Atur Hak Akses: {{ formatRoleName(selectedRole?.name) }}
                            </DialogTitle>
                        </div>
                        <div v-if="selectedRole?.name === 'superadmin'" class="flex items-center gap-1.5 text-rose-500 bg-rose-500/5 px-2 py-0.5 rounded-full border border-rose-500/10 text-[9px] font-bold">
                            <Lock class="h-3 w-3" />
                            Terkunci
                        </div>
                    </div>
                    <DialogDescription class="text-xs text-muted-foreground mt-1">
                        Centang perizinan modul yang dapat diakses oleh user dengan role ini.
                    </DialogDescription>
                </DialogHeader>

                <div class="py-4 max-h-[350px] overflow-y-auto space-y-3 pr-1">
                    <!-- Superadmin locked notice -->
                    <div v-if="selectedRole?.name === 'superadmin'" class="bg-rose-500/5 border border-rose-500/10 rounded-xl p-3 flex gap-3 text-rose-600 mb-2">
                        <Lock class="h-4 w-4 shrink-0 mt-0.5" />
                        <div class="text-[10px] space-y-0.5">
                            <p class="font-bold">Role Administrator Utama</p>
                            <p class="leading-relaxed text-muted-foreground">
                                Role <strong class="text-foreground">Super Admin</strong> memiliki akses absolut ke seluruh sistem secara permanen dan tidak dapat diubah.
                            </p>
                        </div>
                    </div>

                    <div 
                        v-for="perm in permissions" 
                        :key="perm.id"
                        class="flex items-start gap-3.5 p-3 rounded-lg border hover:bg-muted/50 transition-colors"
                        :class="(selectedRole?.name === 'superadmin' || hasPermission(perm.name)) ? 'border-brand-500/20 bg-brand-500/[0.01]' : ''"
                    >
                        <div class="flex items-center h-5 mt-0.5">
                            <input
                                :id="`perm-role-${perm.id}`"
                                type="checkbox"
                                :checked="selectedRole?.name === 'superadmin' || hasPermission(perm.name)"
                                :disabled="selectedRole?.name === 'superadmin' || savingPermissions"
                                @change="togglePermission(perm.name)"
                                class="h-4 w-4 rounded border-gray-300 dark:border-slate-800 text-brand-600 focus:ring-brand-500 cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed"
                            />
                        </div>
                        <label :for="`perm-role-${perm.id}`" class="flex-1 cursor-pointer select-none">
                            <div class="flex items-center gap-1.5">
                                <span class="text-xs font-bold text-foreground">{{ formatPermissionName(perm.name) }}</span>
                                <Loader2 v-if="savingPermissions && selectedRole" class="h-3 w-3 animate-spin text-brand-500" />
                            </div>
                            <p class="text-[10px] text-muted-foreground mt-0.5 leading-relaxed">
                                {{ getPermissionDescription(perm.name) }}
                            </p>
                        </label>
                    </div>
                </div>
            </DialogContent>
        </Dialog>
    </div>
</template>
