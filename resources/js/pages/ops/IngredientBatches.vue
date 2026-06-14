<script setup lang="ts">
import { onMounted, ref } from "vue";
import type { Column, FormField } from "@/components/CRUDTable.vue";
import CRUDTable from "@/components/CRUDTable.vue";

defineOptions({
	layout: {
		breadcrumbs: [
			{ title: "Dashboard", href: "/dashboard" },
			{ title: "Operasional", href: "#" },
			{ title: "Batch Bahan", href: route("ops.ingredient-batches") },
		],
	},
});

const refreshIngredients = async () => {
	const res = await fetch("/api/ingredients?all=true", {
		headers: { Accept: "application/json" },
	});

	if (res.ok) {
		const data = await res.json();
		const field = fields.find((f) => f.key === "ingredient_id");

		if (field) {
			field.options = data.map((i: any) => ({
				value: i.id,
				label: `${i.name} (${i.unit})`,
			}));
		}
	}
};

onMounted(refreshIngredients);

const columns: Column<any>[] = [
	{
		key: "ingredient_id",
		label: "Bahan Baku",
		render: (val, row) => row.ingredient?.name || "—",
	},
	{ key: "batch_number", label: "No. Batch" },
	{
		key: "qty",
		label: "Stok Batch",
		render: (val, row) => `${val} ${row.ingredient?.unit || ""}`,
	},
	{
		key: "price",
		label: "Total Harga Beli",
		render: (val) => val ? `Rp ${Math.round(val as number).toLocaleString("id-ID")}` : "Rp 0",
	},
	{
		key: "expiration_date",
		label: "Tanggal Kedaluwarsa",
		render: (val) =>
			val ? new Date(val as string).toLocaleDateString("id-ID") : "—",
	},
	{
		key: "created_by",
		label: "Dibuat Oleh",
		render: (val, row) => row.creator?.name || "—",
	},
];

const fields: FormField[] = [
	{
		key: "ingredient_id",
		label: "Bahan Baku",
		type: "select",
		required: true,
		options: [],
	},
	{ key: "batch_number", label: "Nomor Batch", type: "text", required: true },
	{ key: "qty", label: "Stok Batch", type: "number", required: true },
	{ key: "price", label: "Total Harga Beli", type: "number", required: true },
	{
		key: "expiration_date",
		label: "Tanggal Kedaluwarsa",
		type: "date",
		required: true,
	},
];
</script>

<template>
    <div class="p-6">
        <CRUDTable resource-name="Batch Bahan" api-url="/api/ingredient-batches" :columns="columns" :form-fields="fields" auditable-type="IngredientBatch" />
    </div>
</template>
