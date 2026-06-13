<script setup lang="ts">
import { onMounted, ref } from "vue";
import type { Column, FormField } from "@/components/CRUDTable.vue";
import CRUDTable from "@/components/CRUDTable.vue";
import team from "@/routes/team";
import { ShieldCheck, Loader2 } from "lucide-vue-next";
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
			{ title: "Jabatan", href: team.positions().url },
		],
	},
});

const columns: Column<any>[] = [
	{ key: "name", label: "Nama Jabatan" },
	{ key: "slug", label: "Slug" },
	{ key: "description", label: "Deskripsi" },
	{
		key: "starting_page",
		label: "Halaman Awal",
		render: (val) => {
			const labelMap: Record<string, string> = {
				"/dashboard": "Dashboard",
				"/reports": "Laporan (Reports)",
				"/pos": "Kasir (POS)",
				"/kitchen": "Dapur (Kitchen)",
			};
			return labelMap[val] || val || "—";
		},
	},
];

const fields: FormField[] = [
	{ key: "name", label: "Nama Jabatan", type: "text", required: true },
	{ key: "description", label: "Deskripsi", type: "textarea" },
	{
		key: "starting_page",
		label: "Halaman Awal (Starting Page)",
		type: "select",
		options: [
			{ value: "/dashboard", label: "Dashboard" },
			{ value: "/reports", label: "Laporan (Reports)" },
			{ value: "/pos", label: "Kasir (POS)" },
			{ value: "/kitchen", label: "Dapur (Kitchen)" },
		],
	},
];

// Permission Settings State
const permissions = ref<any[]>([]);
const selectedPosition = ref<any | null>(null);
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
	selectedPosition.value = { ...row };
	isPermissionsModalOpen.value = true;
};

const hasPermission = (permName: string): boolean => {
	if (!selectedPosition.value || !selectedPosition.value.permissions) {
		return false;
	}
	return selectedPosition.value.permissions.some((p: any) => p.name === permName);
};

const togglePermission = async (permName: string) => {
	if (!selectedPosition.value || savingPermissions.value) {
		return;
	}

	savingPermissions.value = true;
	let currentPerms = (selectedPosition.value.permissions || []).map((p: any) => p.name);

	if (currentPerms.includes(permName)) {
		currentPerms = currentPerms.filter((p: any) => p !== permName);
	} else {
		currentPerms.push(permName);
	}

	try {
		const res = await fetch(`/api/positions/${selectedPosition.value.id}/permissions`, {
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
			const updatedPosition = await res.json();
			selectedPosition.value = updatedPosition;
			
			// Refresh parent CRUD table data so it updates local permissions reference
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
        <CRUDTable ref="crudTableRef" resource-name="Jabatan" api-url="/api/positions" :columns="columns" :form-fields="fields" auditable-type="Position">
            <template #actions="{ row }">
                <Button
                    size="icon-sm"
                    variant="ghost"
                    class="text-brand-500 hover:text-brand-600 hover:bg-brand-500/10 h-8 w-8"
                    @click="openPermissionsModal(row)"
                    title="Atur Hak Akses Jabatan"
                >
                    <ShieldCheck class="h-4.5 w-4.5" />
                </Button>
            </template>
        </CRUDTable>

        <!-- Permissions Checkbox List Modal -->
        <Dialog :open="isPermissionsModalOpen" @update:open="isPermissionsModalOpen = $event">
            <DialogContent class="sm:max-w-[500px]">
                <DialogHeader>
                    <div class="flex items-center gap-2">
                        <ShieldCheck class="h-5 w-5 text-brand-500" />
                        <DialogTitle class="text-sm font-bold text-foreground">
                            Atur Hak Akses: {{ selectedPosition?.name }}
                        </DialogTitle>
                    </div>
                    <DialogDescription class="text-xs text-muted-foreground mt-1">
                        Centang perizinan modul yang dapat diakses oleh karyawan dengan jabatan ini.
                    </DialogDescription>
                </DialogHeader>

                <div class="py-4 max-h-[350px] overflow-y-auto space-y-3 pr-1">
                    <div 
                        v-for="perm in permissions" 
                        :key="perm.id"
                        class="flex items-start gap-3.5 p-3 rounded-lg border hover:bg-muted/50 transition-colors"
                        :class="hasPermission(perm.name) ? 'border-brand-500/20 bg-brand-500/[0.01]' : ''"
                    >
                        <div class="flex items-center h-5 mt-0.5">
                            <input
                                :id="`perm-pos-${perm.id}`"
                                type="checkbox"
                                :checked="hasPermission(perm.name)"
                                :disabled="savingPermissions"
                                @change="togglePermission(perm.name)"
                                class="h-4 w-4 rounded border-gray-300 dark:border-slate-800 text-brand-600 focus:ring-brand-500 cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed"
                            />
                        </div>
                        <label :for="`perm-pos-${perm.id}`" class="flex-1 cursor-pointer select-none">
                            <div class="flex items-center gap-1.5">
                                <span class="text-xs font-bold text-foreground">{{ formatPermissionName(perm.name) }}</span>
                                <Loader2 v-if="savingPermissions && selectedPosition" class="h-3 w-3 animate-spin text-brand-500" />
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
