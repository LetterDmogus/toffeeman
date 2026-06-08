<script setup lang="ts">
import type { Column, FormField } from "@/components/CRUDTable.vue";
import CRUDTable from "@/components/CRUDTable.vue";
import team from "@/routes/team";

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
];

const fields: FormField[] = [
	{ key: "name", label: "Nama Jabatan", type: "text", required: true },
	{ key: "description", label: "Deskripsi", type: "textarea" },
];
</script>

<template>
    <div class="p-6">
        <CRUDTable resource-name="Jabatan" api-url="/api/positions" :columns="columns" :form-fields="fields" />
    </div>
</template>
