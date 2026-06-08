<script setup lang="ts">
import { onMounted, ref } from "vue";
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

const refreshPositions = async () => {
	const res = await fetch("/api/positions?all=true", {
		headers: { Accept: "application/json" },
	});

	if (res.ok) {
		const data = await res.json();
		const field = fields.find((f) => f.key === "position_id");

		if (field) {
			field.options = data.map((p: any) => ({ value: p.id, label: p.name }));
		}
	}
};

onMounted(refreshPositions);

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

const fields: FormField[] = [
	{ key: "name", label: "Nama Lengkap", type: "text", required: true },
	{ key: "email", label: "Email Staf", type: "text", required: true },
	{ key: "phone", label: "No. Telpon", type: "text" },
	{
		key: "password",
		label: "Password",
		type: "text",
		placeholder: "Isi jika ganti password",
	},
	{ key: "salary", label: "Gaji Bulanan", type: "number", required: true },
	{
		key: "position_id",
		label: "Jabatan",
		type: "select",
		required: true,
		options: [],
	},
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
];

const badgeMap = { active: "success", inactive: "danger" };
</script>

<template>
    <div class="p-6">
        <CRUDTable resource-name="Karyawan" api-url="/api/employees" :columns="columns" :form-fields="fields" :badge-map="badgeMap" />
    </div>
</template>
