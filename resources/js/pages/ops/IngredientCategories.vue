<script setup lang="ts">
import CRUDTable from '@/components/CRUDTable.vue';
import type { Column, FormField } from '@/components/CRUDTable.vue';
import ops from '@/routes/ops';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: '/dashboard' },
            { title: 'Operasional', href: '#' },
            { title: 'Kategori Bahan', href: ops.ingredientCategories().url },
        ],
    },
});

const columns: Column<any>[] = [
    { key: 'name', label: 'Nama Kategori' },
    { key: 'slug', label: 'Slug' },
    { key: 'description', label: 'Deskripsi' },
];

const fields: FormField[] = [
    { key: 'name', label: 'Nama Kategori', type: 'text', required: true },
    { key: 'description', label: 'Deskripsi', type: 'textarea' },
];
</script>

<template>
    <div class="p-6">
        <CRUDTable resource-name="Kategori Bahan" api-url="/api/ingredient-categories" :columns="columns" :form-fields="fields" />
    </div>
</template>
