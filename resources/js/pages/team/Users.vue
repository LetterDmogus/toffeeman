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
			{ title: "User / Pengguna", href: team.users().url },
		],
	},
});

const refreshRoles = async () => {
	const res = await fetch("/api/users/roles", {
		headers: { Accept: "application/json" },
	});

	if (res.ok) {
		const data = await res.json();
		const field = fields.find((f) => f.key === "role");

		if (field) {
			field.options = data.map((r: any) => ({
				value: r.name,
				label: r.name.charAt(0).toUpperCase() + r.name.slice(1),
			}));
		}
	}
};

onMounted(refreshRoles);

const columns: Column<any>[] = [
	{ key: "name", label: "Nama" },
	{ key: "email", label: "Email" },
	{
		key: "roles",
		label: "Role",
		render: (val) => val?.map((r: any) => r.name).join(", ") || "—",
	},
	{ key: "status", label: "Status" },
];

const fields: FormField[] = [
	{ key: "name", label: "Nama Lengkap", type: "text", required: true },
	{ key: "email", label: "Email", type: "text", required: true },
	{ key: "password", label: "Password", type: "text" },
	{
		key: "role",
		label: "Role Sistem",
		type: "select",
		required: true,
		options: [],
	},
	{
		key: "status",
		label: "Status Akun",
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
        <CRUDTable resource-name="User" api-url="/api/users" :columns="columns" :form-fields="fields" :badge-map="badgeMap" />
    </div>
</template>
