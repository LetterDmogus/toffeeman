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
		render: (val, row) => row.position?.name || "—",
	},
	{
		key: "salary",
		label: "Gaji",
		render: (val) => `Rp ${Number(val).toLocaleString("id-ID")}`,
	},
	{ key: "status", label: "Status" },
];

const users = ref<any[]>([]);
const userOptions = ref<{ value: any; label: string }[]>([]);
const crudTableRef = ref<any>(null);

const fields = computed<FormField[]>(() => [
	{
		key: "user_id",
		label: "Hubungkan ke User (Opsional)",
		type: "select",
		options: userOptions.value,
		disableOnEdit: true,
	},
	{ key: "name", label: "Nama Lengkap", type: "text", required: true },
	{ key: "email", label: "Email Staf", type: "text", required: true },
	{ key: "phone", label: "No. Telpon", type: "text" },
	{
		key: "password",
		label: "Password",
		type: "text",
		placeholder: "Isi jika ganti/buat password",
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
		const res = await fetch("/api/users?all=true");
		if (res.ok) {
			const data = await res.json();
			users.value = data;
			userOptions.value = data.map((u: any) => ({
				value: u.id,
				label: `${u.name} (${u.email})`,
			}));
		}
	} catch (e) {
		console.error("Gagal mengambil data user:", e);
	}
});

watch(
	() => crudTableRef.value?.formData?.user_id,
	(newUserId) => {
		if (crudTableRef.value?.dialogMode === "create") {
			if (newUserId) {
				const selectedUser = users.value.find(
					(u) => u.id === Number(newUserId) || u.id === newUserId
				);
				if (selectedUser) {
					crudTableRef.value.formData.name = selectedUser.name || "";
					crudTableRef.value.formData.email = selectedUser.email || "";
					crudTableRef.value.formData.phone = selectedUser.phone || "";
				}
			} else {
				crudTableRef.value.formData.name = "";
				crudTableRef.value.formData.email = "";
				crudTableRef.value.formData.phone = "";
			}
		}
	}
);
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
