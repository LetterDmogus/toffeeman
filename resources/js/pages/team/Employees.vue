<script setup lang="ts">
import { onMounted, ref, computed, watch } from "vue";
import type { Column, FormField } from "@/components/CRUDTable.vue";
import CRUDTable from "@/components/CRUDTable.vue";
import team from "@/routes/team";

defineOptions({
	layout: {
		breadcrumbs: [
			{ title: "Dashboard", href: "/dashboard" },
			{ title: "Manajemen Tim", href: "#" },
			{ title: "Karyawan", href: team.employees().url },
		],
	},
});

const columns: Column<any>[] = [
	{ key: "name", label: "Nama", render: (val, row) => row.user?.name || "—" },
	{
		key: "email",
		label: "Email",
		render: (val, row) => row.user?.email || "—",
	},
	{
		key: "position_id",
		label: "Jabatan",
		render: (val, row) => row.user?.position?.name || "—",
	},
	{
		key: "salary",
		label: "Gaji",
		render: (val) => `Rp ${Number(val).toLocaleString("id-ID")}`,
	},
	{ key: "status", label: "Status" },
];

const positionOptions = ref<{ value: any; label: string }[]>([]);
const crudTableRef = ref<any>(null);

const fields = computed<FormField[]>(() => [
	{ key: "name", label: "Nama Lengkap", type: "text", required: true },
	{ key: "email", label: "Email Staf", type: "text", required: true },
	{ key: "phone", label: "No. Telpon", type: "phone" },
	{
		key: "position_id",
		label: "Jabatan",
		type: "select",
		required: true,
		options: positionOptions.value,
	},
	{ key: "face_photo_path", label: "Foto Wajah (Absensi)", type: "image" },
	{
		key: "password",
		label: "Password",
		type: "text",
		placeholder: "Isi jika ganti/buat password baru",
	},
	{ key: "salary", label: "Gaji Bulanan", type: "number", required: true },
	{
		key: "status",
		label: "Status Staf",
		type: "select",
		required: true,
		options: [
			{ value: "active", label: "Aktif" },
			{ value: "inactive", label: "Tidak Aktif" },
		],
	},
]);

const badgeMap = { active: "success", inactive: "danger" };

onMounted(async () => {
	try {
		const res = await fetch("/api/positions?all=true");
		if (res.ok) {
			const data = await res.json();
			positionOptions.value = data.map((p: any) => ({
				value: p.id,
				label: p.name,
			}));
		}
	} catch (e) {
		console.error("Gagal mengambil data jabatan:", e);
	}
});
</script>

<template>
    <div class="p-6">
        <CRUDTable
            ref="crudTableRef"
            resource-name="Karyawan"
            api-url="/api/employees"
            :columns="columns"
            :form-fields="fields"
            :badge-map="badgeMap"
        />
    </div>
</template>
