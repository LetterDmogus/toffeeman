<script setup lang="ts">
import type { Column, FormField } from "@/components/CRUDTable.vue";
import CRUDTable from "@/components/CRUDTable.vue";
import catalog from "@/routes/catalog";

defineOptions({
	layout: {
		breadcrumbs: [
			{ title: "Dashboard", href: "/dashboard" },
			{ title: "Katalog Produk", href: "#" },
			{ title: "Extra Topping", href: catalog.addOns().url },
		],
	},
});

const columns: Column<any>[] = [
	{ key: "name", label: "Nama Topping" },
	{
		key: "price",
		label: "Harga",
		render: (val) => `Rp ${Number(val).toLocaleString("id-ID")}`,
	},
	{ key: "is_active", label: "Aktif", render: (val) => (val ? "Ya" : "Tidak") },
];

const fields: FormField[] = [
	{
		key: "name",
		label: "Nama Topping",
		type: "text",
		placeholder: "Contoh: Ekstra Keju",
		required: true,
	},
	{ key: "price", label: "Harga (Rp)", type: "number", required: true },
	{
		key: "is_active",
		label: "Status Aktif",
		type: "select",
		required: true,
		options: [
			{ value: true, label: "Ya" },
			{ value: false, label: "Tidak" },
		],
	},
];
</script>

<template>
    <div class="p-6">
        <CRUDTable resource-name="Add-on" api-url="/api/add-ons" :columns="columns" :form-fields="fields" />
    </div>
</template>
