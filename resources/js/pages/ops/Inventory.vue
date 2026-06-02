<script setup lang="ts">
import { ref, onMounted } from 'vue';
import CRUDTable from '@/components/CRUDTable.vue';
import type { Column, FormField } from '@/components/CRUDTable.vue';
import ops from '@/routes/ops';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: '/dashboard' },
            { title: 'Operasional', href: '#' },
            { title: 'Inventaris', href: ops.inventory().url },
        ],
    },
});

const refreshCategories = async () => {
    const res = await fetch('/api/inventory-categories?all=true', { headers: { Accept: 'application/json' } });
    if (res.ok) {
        const data = await res.json();
        const field = fields.find(f => f.key === 'inventory_category_id');
        if (field) field.options = data.map((c: any) => ({ value: c.id, label: c.name }));
    }
};

onMounted(refreshCategories);

const columns: Column<any>[] = [
    { key: 'name', label: 'Nama Barang' },
    { key: 'sku', label: 'SKU' },
    { key: 'inventory_category_id', label: 'Kategori', render: (val, row) => row.category?.name || '—' },
    { key: 'qty', label: 'Stok', render: (val, row) => `${val} ${row.unit}` },
    { key: 'status', label: 'Status' },
];

const fields: FormField[] = [
    { key: 'name', label: 'Nama Barang', type: 'text', required: true },
    { key: 'sku', label: 'SKU', type: 'text' },
    { key: 'inventory_category_id', label: 'Kategori', type: 'select', required: true, options: [] },
    { key: 'price', label: 'Harga Beli (Rp)', type: 'number', required: true },
    { key: 'qty', label: 'Stok', type: 'number', required: true },
    { key: 'unit', label: 'Satuan', type: 'text', required: true },
    { key: 'min_qty', label: 'Min Stok', type: 'number', required: true },
];

const badgeMap = { in_stock: 'success', low_stock: 'warning', out_of_stock: 'danger' };
</script>

<template>
    <div class="p-6">
        <CRUDTable resource-name="Barang" api-url="/api/inventory-items" :columns="columns" :form-fields="fields" :badge-map="badgeMap" />
    </div>
</template>
