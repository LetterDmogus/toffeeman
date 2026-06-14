<script setup lang="ts">
import { onMounted, ref } from "vue";
import type { Column, FormField } from "@/components/CRUDTable.vue";
import CRUDTable from "@/components/CRUDTable.vue";

defineOptions({
	layout: {
		breadcrumbs: [
			{ title: "Dashboard", href: "/dashboard" },
			{ title: "Manajemen Tim", href: "#" },
			{ title: "User / Pengguna", href: route("team.users") },
		],
	},
});

const refreshRolesAndPositions = async () => {
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

	const posRes = await fetch("/api/positions?all=true", {
		headers: { Accept: "application/json" },
	});

	if (posRes.ok) {
		const data = await posRes.json();
		const field = fields.find((f) => f.key === "position_id");

		if (field) {
			field.options = data.map((p: any) => ({
				value: p.id,
				label: p.name,
			}));
		}
	}
};

onMounted(refreshRolesAndPositions);

const columns: Column<any>[] = [
	{ key: "name", label: "Nama" },
	{ key: "email", label: "Email" },
	{
		key: "roles",
		label: "Role",
		render: (val) => val?.map((r: any) => r.name).join(", ") || "—",
	},
	{
		key: "position_id",
		label: "Jabatan",
		render: (val, row) => row.position?.name || "—",
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
		key: "position_id",
		label: "Jabatan",
		type: "select",
		required: false,
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

const handleFormOpened = ({ mode, row }: { mode: "create" | "edit"; row: any }) => {
	const roleField = fields.find((f) => f.key === "role");
	if (!roleField) {
		return;
	}

	if (mode === "edit" && row) {
		const userRole = row.role || (row.roles && row.roles[0]?.name);
		if (userRole === "superadmin") {
			roleField.options = [{ value: "superadmin", label: "Super Admin" }];
			roleField.disabled = true;
		} else {
			roleField.disabled = false;
			refreshRolesAndPositions();
		}
	} else {
		roleField.disabled = false;
		refreshRolesAndPositions();
	}
};

const badgeMap = { active: "success", inactive: "danger" };
</script>

<template>
    <div class="p-6">
        <CRUDTable resource-name="User" api-url="/api/users" :columns="columns" :form-fields="fields" :badge-map="badgeMap" @form-opened="handleFormOpened" auditable-type="User" />
    </div>
</template>
