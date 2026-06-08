<script setup lang="ts">
import type { Column, FormField } from "@/components/CRUDTable.vue";
import CRUDTable from "@/components/CRUDTable.vue";
import catalog from "@/routes/catalog";

defineOptions({
	layout: {
		breadcrumbs: [
			{ title: "Dashboard", href: "/dashboard" },
			{ title: "Katalog Produk", href: "#" },
			{ title: "Kategori Menu", href: catalog.categories().url },
		],
	},
});

const columns: Column<any>[] = [
	{ key: "name", label: "Nama Kategori" },
	{ key: "slug", label: "Slug" },
	{ key: "description", label: "Deskripsi" },
];

const fields: FormField[] = [
	{
		key: "name",
		label: "Nama Kategori",
		type: "text",
		placeholder: "Contoh: Appetizer",
		required: true,
	},
	{ key: "description", label: "Deskripsi", type: "textarea" },
];
</script>

<template>
    <div class="p-6">
        <CRUDTable resource-name="Kategori" api-url="/api/categories" :columns="columns" :form-fields="fields" />
    </div>
</template>
