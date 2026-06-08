<script setup lang="ts">
import { Barcode } from "lucide-vue-next";
import { onMounted, ref } from "vue";
import type { Column, FormField } from "@/components/CRUDTable.vue";
import CRUDTable from "@/components/CRUDTable.vue";
import { Button } from "@/components/ui/button";
import ops from "@/routes/ops";

defineOptions({
	layout: {
		breadcrumbs: [
			{ title: "Dashboard", href: "/dashboard" },
			{ title: "Operasional", href: "#" },
			{ title: "Inventaris", href: ops.inventory().url },
		],
	},
});

const refreshCategories = async () => {
	const res = await fetch("/api/inventory-categories?all=true", {
		headers: { Accept: "application/json" },
	});

	if (res.ok) {
		const data = await res.json();
		const field = fields.find((f) => f.key === "inventory_category_id");

		if (field) {
			field.options = data.map((c: any) => ({ value: c.id, label: c.name }));
		}
	}
};

onMounted(refreshCategories);

const downloadBarcode = (row: any) => {
	if (!row.sku) {
		alert("Barang ini tidak memiliki SKU/Barcode.");

		return;
	}

	window.open(`/api/inventory-items/${row.id}/barcode`, "_blank");
};

const columns: Column<any>[] = [
	{ key: "name", label: "Nama Barang" },
	{ key: "sku", label: "SKU" },
	{
		key: "inventory_category_id",
		label: "Kategori",
		render: (val, row) => row.category?.name || "—",
	},
	{
		key: "qty_good",
		label: "Baik",
		render: (val, row) => `${val} ${row.unit}`,
	},
	{
		key: "qty_fair",
		label: "Kurang Baik",
		render: (val, row) => `${val} ${row.unit}`,
	},
	{
		key: "qty_damaged",
		label: "Rusak",
		render: (val, row) => `${val} ${row.unit}`,
	},
	{
		key: "qty",
		label: "Total Stok",
		render: (val, row) => `${val} ${row.unit}`,
	},
	{
		key: "purchase_date",
		label: "Tgl Pembelian",
		render: (val) =>
			val ? new Date(val as string).toLocaleDateString("id-ID") : "—",
	},
	{
		key: "storage_location",
		label: "Lokasi",
		render: (val) => (val ? `${val}` : "—"),
	},
	{ key: "status", label: "Status" },
];

const fields: FormField[] = [
	{ key: "name", label: "Nama Barang", type: "text", required: true },
	{ key: "sku", label: "SKU", type: "text" },
	{
		key: "inventory_category_id",
		label: "Kategori",
		type: "select",
		required: true,
		options: [],
	},
	{ key: "price", label: "Harga Beli (Rp)", type: "number", required: true },
	{
		key: "qty_good",
		label: "Stok Kondisi Baik",
		type: "number",
		required: true,
		disableOnEdit: true,
	},
	{
		key: "qty_fair",
		label: "Stok Kondisi Kurang Baik",
		type: "number",
		required: true,
		disableOnEdit: true,
	},
	{
		key: "qty_damaged",
		label: "Stok Kondisi Rusak",
		type: "number",
		required: true,
		disableOnEdit: true,
	},
	{
		key: "qty",
		label: "Total Stok Keseluruhan",
		type: "number",
		required: true,
	},
	{ key: "unit", label: "Satuan", type: "text", required: true },
	{ key: "min_qty", label: "Min Stok", type: "number", required: true },
	{ key: "purchase_date", label: "Tanggal Pembelian", type: "date" },
	{ key: "storage_location", label: "Lokasi Penyimpanan", type: "text" },
];

const badgeMap = {
	in_stock: "success",
	low_stock: "warning",
	out_of_stock: "danger",
};
</script>

<template>
    <div class="p-6">
        <CRUDTable
            resource-name="Barang / Peralatan"
            api-url="/api/inventory-items"
            :columns="columns"
            :form-fields="fields"
            :badge-map="badgeMap"
            :default-visible-columns="['name', 'sku', 'inventory_category_id', 'qty', 'status']"
        >
            <template #actions="{ row }">
                <Button 
                    v-if="row.sku"
                    size="icon-sm" 
                    variant="ghost" 
                    class="text-emerald-600 hover:text-emerald-700 hover:bg-emerald-50" 
                    @click="downloadBarcode(row)" 
                    title="Generate & Download Barcode SVG"
                >
                    <Barcode class="h-4.5 w-4.5" />
                </Button>
            </template>
        </CRUDTable>
    </div>
</template>
