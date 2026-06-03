<script setup lang="ts">
import { ref, onMounted } from 'vue';
import CRUDTable from '@/components/CRUDTable.vue';
import type { Column, FormField } from '@/components/CRUDTable.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Layers, Trash2, Loader2, ArrowLeft } from 'lucide-vue-next';
import ops from '@/routes/ops';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: '/dashboard' },
            { title: 'Operasional', href: '#' },
            { title: 'Bahan Baku', href: ops.ingredients().url },
        ],
    },
});

const tableKey = ref(0);
const refreshCategories = async () => {
    const res = await fetch('/api/ingredient-categories?all=true', { headers: { Accept: 'application/json' } });
    if (res.ok) {
        const data = await res.json();
        const field = fields.find(f => f.key === 'ingredient_category_id');
        if (field) field.options = data.map((c: any) => ({ value: c.id, label: c.name }));
    }
};

onMounted(refreshCategories);

const columns: Column<any>[] = [
    { key: 'name', label: 'Nama Bahan' },
    { key: 'sku', label: 'SKU' },
    { key: 'ingredient_category_id', label: 'Kategori', render: (val, row) => row.category?.name || '—' },
    { key: 'qty', label: 'Total Stok', render: (val, row) => `${val} ${row.unit}` },
    { key: 'small_unit_qty', label: 'Satuan Kecil', render: (val, row) => `${val} g/ml` },
    { key: 'storage_temperature', label: 'Suhu Penyimpanan', render: (val) => val ? `${val}` : '—' },
    { key: 'created_by', label: 'Dibuat Oleh', render: (val, row) => row.creator?.name || '—' },
    { key: 'status', label: 'Status' },
];

const fields: FormField[] = [
    { key: 'name', label: 'Nama Bahan', type: 'text', required: true },
    { key: 'sku', label: 'SKU', type: 'text' },
    { key: 'ingredient_category_id', label: 'Kategori', type: 'select', required: true, options: [] },
    { key: 'price', label: 'Harga Beli (Rp)', type: 'number', required: true },
    { key: 'qty', label: 'Stok Awal', type: 'number', required: true },
    { key: 'unit', label: 'Satuan Utama (misal: Kg, Box)', type: 'text', required: true },
    { key: 'small_unit_qty', label: 'Jumlah Unit Kecil (gram/ml)', type: 'number', required: true },
    { key: 'min_qty', label: 'Min Stok Utama', type: 'number', required: true },
    { key: 'storage_temperature', label: 'Suhu Penyimpanan (°C / teks)', type: 'text' },
    { key: 'description', label: 'Keterangan', type: 'textarea' },
];

const badgeMap = { in_stock: 'success', low_stock: 'warning', out_of_stock: 'danger' };

// ─── View Switching State ────────────────────────────────────────────────────
const viewMode = ref<'ingredients' | 'batches'>('ingredients');
const selectedIngredient = ref<any>(null);
const batches = ref<any[]>([]);
const addingBatch = ref(false);
const newBatch = ref({
    batch_number: '',
    qty: 0,
    expiration_date: '',
});

const switchToBatches = async (ingredient: any) => {
    selectedIngredient.value = ingredient;
    newBatch.value = { batch_number: '', qty: 0, expiration_date: '' };
    await fetchBatches();
    viewMode.value = 'batches';
};

const goBack = () => {
    viewMode.value = 'ingredients';
    tableKey.value++; // Force main table reload to update stock quantities
};

const fetchBatches = async () => {
    if (!selectedIngredient.value) return;
    const res = await fetch(`/api/ingredient-batches?ingredient_id=${selectedIngredient.value.id}`, {
        headers: { Accept: 'application/json' },
    });
    if (res.ok) {
        const json = await res.json();
        batches.value = json.data || [];
    }
};

const addBatch = async () => {
    if (!selectedIngredient.value) return;
    addingBatch.value = true;
    try {
        const res = await fetch('/api/ingredient-batches', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '',
            },
            body: JSON.stringify({
                ingredient_id: selectedIngredient.value.id,
                ...newBatch.value,
            }),
        });
        if (res.ok) {
            newBatch.value = { batch_number: '', qty: 0, expiration_date: '' };
            await fetchBatches();
        }
    } finally {
        addingBatch.value = false;
    }
};

const deleteBatch = async (batchId: number) => {
    if (!confirm('Apakah Anda yakin ingin menghapus batch ini?')) return;
    const res = await fetch(`/api/ingredient-batches/${batchId}`, {
        method: 'DELETE',
        headers: {
            Accept: 'application/json',
            'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '',
        },
    });
    if (res.ok) {
        await fetchBatches();
    }
};

const formatDate = (val: string) => {
    if (!val) return '—';
    return new Date(val).toLocaleDateString('id-ID');
};
</script>

<template>
    <div class="p-6">
        <!-- Main Ingredients Table View -->
        <div v-if="viewMode === 'ingredients'" class="animate-in fade-in-0 duration-200">
            <CRUDTable
                :key="tableKey"
                resource-name="Bahan Baku"
                api-url="/api/ingredients"
                :columns="columns"
                :form-fields="fields"
                :badge-map="badgeMap"
            >
                <template #actions="{ row }">
                    <Button
                        size="icon-sm"
                        variant="ghost"
                        @click="switchToBatches(row)"
                        title="Lihat Batch"
                        class="text-primary hover:bg-primary/10"
                    >
                        <Layers class="h-4 w-4" />
                    </Button>
                </template>
            </CRUDTable>
        </div>

        <!-- Inline Batches Detail View (Transitions on Page) -->
        <div v-else class="rounded-xl border bg-card p-6 shadow-sm animate-in fade-in-0 slide-in-from-left duration-300">
            <div class="flex items-center justify-between border-b pb-4 mb-6">
                <div class="flex items-center gap-3">
                    <Button variant="ghost" size="icon-sm" @click="goBack">
                        <ArrowLeft class="h-4 w-4" />
                    </Button>
                    <div>
                        <h2 class="text-lg font-bold">Detail Batch: {{ selectedIngredient?.name }}</h2>
                        <p class="text-xs text-muted-foreground">Kategori: {{ selectedIngredient?.category?.name || '—' }} | Satuan: {{ selectedIngredient?.unit }}</p>
                    </div>
                </div>
            </div>

            <div class="grid gap-6 md:grid-cols-3">
                <!-- Add Batch Form -->
                <div class="p-5 bg-muted/40 rounded-xl border flex flex-col gap-4">
                    <h3 class="text-sm font-semibold">Tambah Batch Baru</h3>
                    <form @submit.prevent="addBatch" class="flex flex-col gap-3">
                        <div class="space-y-1">
                            <Label for="batch_number">Nomor Batch</Label>
                            <Input id="batch_number" v-model="newBatch.batch_number" required placeholder="Contoh: BCH-001" />
                        </div>
                        <div class="space-y-1">
                            <Label for="batch_qty">Jumlah Stok ({{ selectedIngredient?.unit }})</Label>
                            <Input id="batch_qty" type="number" step="0.01" v-model.number="newBatch.qty" required />
                        </div>
                        <div class="space-y-1">
                            <Label for="expiration_date">Tanggal Kedaluwarsa</Label>
                            <Input id="expiration_date" type="date" v-model="newBatch.expiration_date" required />
                        </div>
                        <Button type="submit" class="mt-2" :disabled="addingBatch">
                            <Loader2 v-if="addingBatch" class="mr-2 h-4 w-4 animate-spin" />
                            Tambah Batch
                        </Button>
                    </form>
                </div>

                <!-- Batches Table List -->
                <div class="md:col-span-2 border rounded-xl overflow-hidden bg-card">
                    <table class="w-full text-sm text-left">
                        <thead class="bg-muted text-muted-foreground uppercase text-xs font-semibold">
                            <tr>
                                <th class="p-4">No. Batch</th>
                                <th class="p-4">Stok</th>
                                <th class="p-4">Kedaluwarsa</th>
                                <th class="p-4">Dibuat Oleh</th>
                                <th class="p-4 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr v-if="batches.length === 0">
                                <td colspan="5" class="p-6 text-center text-muted-foreground italic">Belum ada batch untuk bahan ini. Silakan tambahkan di formulir kiri.</td>
                            </tr>
                            <tr v-for="batch in batches" :key="batch.id" class="hover:bg-muted/10 transition-colors">
                                <td class="p-4 font-medium">{{ batch.batch_number }}</td>
                                <td class="p-4">{{ Number(batch.qty) }} {{ selectedIngredient?.unit }}</td>
                                <td class="p-4">{{ formatDate(batch.expiration_date) }}</td>
                                <td class="p-4 text-xs text-muted-foreground">{{ batch.creator?.name || '—' }}</td>
                                <td class="p-4 text-right">
                                    <Button size="icon-sm" variant="ghost" class="text-destructive hover:bg-destructive/10" @click="deleteBatch(batch.id)">
                                        <Trash2 class="h-4 w-4" />
                                    </Button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="flex items-center justify-end gap-3 border-t pt-4 mt-6">
                <Button variant="outline" @click="goBack">Kembali</Button>
            </div>
        </div>
    </div>
</template>
