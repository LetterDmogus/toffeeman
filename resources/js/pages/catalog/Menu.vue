<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { UtensilsCrossed, Plus, ArrowLeft, Loader2, Check, ListPlus, X } from 'lucide-vue-next';
import CRUDTable from '@/components/CRUDTable.vue';
import type { Column, FormField } from '@/components/CRUDTable.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import catalog from '@/routes/catalog';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: '/dashboard' },
            { title: 'Katalog Produk', href: '#' },
            { title: 'Menu & Varian', href: catalog.menu().url },
        ],
    },
});

const categories = ref<{ id: number; name: string }[]>([]);

const refreshCategories = async () => {
    try {
        const res = await fetch('/api/categories?all=true', { headers: { Accept: 'application/json' } });
        if (res.ok) {
            const data = await res.json();
            categories.value = data;
            const catField = menuItemFields.find(f => f.key === 'category_id');
            if (catField) {
                catField.options = data.map((c: any) => ({ value: c.id, label: c.name }));
            }
        }
    } catch (e) { console.error(e); }
};

onMounted(() => refreshCategories());

// ─── Menu Customizations (Variants & Add-ons) Logic ──────────────────
const selectedMenuItem = ref<any | null>(null);
const allAddOns = ref<any[]>([]);
const selectedAddOnIds = ref<number[]>([]);
const menuOptions = ref<{ name: string; is_required: boolean; type: 'single' | 'multiple'; values: { name: string; additional_price: number }[] }[]>([]);
const savingCustomizations = ref(false);

const manageMenuCustomizations = async (item: any) => {
    selectedMenuItem.value = item;
    try {
        const menuRes = await fetch(`/api/menu-items/${item.id}`, { headers: { Accept: 'application/json' } });
        if (menuRes.ok) {
            const data = await menuRes.json();
            selectedAddOnIds.value = (data.add_ons || []).map((ao: any) => ao.id);
            menuOptions.value = (data.options || []).map((opt: any) => ({
                name: opt.name,
                is_required: Boolean(opt.is_required),
                type: opt.type,
                values: (opt.values || []).map((v: any) => ({ name: v.name, additional_price: Number(v.additional_price) }))
            }));
        }
        const addOnRes = await fetch('/api/add-ons?all=true', { headers: { Accept: 'application/json' } });
        if (addOnRes.ok) allAddOns.value = await addOnRes.json();
    } catch (e) { console.error(e); }
};

const toggleAddOn = (id: number) => {
    const idx = selectedAddOnIds.value.indexOf(id);
    if (idx > -1) selectedAddOnIds.value.splice(idx, 1);
    else selectedAddOnIds.value.push(id);
};

const saveMenuCustomizations = async () => {
    if (!selectedMenuItem.value) return;
    savingCustomizations.value = true;
    try {
        const res = await fetch(`/api/menu-items/${selectedMenuItem.value.id}`, {
            method: 'PATCH',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as any)?.content || '' },
            body: JSON.stringify({ add_on_ids: selectedAddOnIds.value, options: menuOptions.value.filter(opt => opt.name.trim() !== '') })
        });
        if (res.ok) selectedMenuItem.value = null;
    } catch (e) { console.error(e); } finally { savingCustomizations.value = false; }
};

// ─── Table Config ───────────────────────────────────────────────────────────────
type MenuItem = { id: number; name: string; category: { id: number; name: string } | null; category_id: number | null; price: string; status: string; calories: number | null; preparation_time: number | null; };
const menuItemColumns: Column<MenuItem>[] = [
    { key: 'name', label: 'Nama' },
    { key: 'category_id', label: 'Kategori', render: (val, row) => row.category ? row.category.name : '—' },
    { key: 'price', label: 'Harga', render: (val) => `Rp ${Number(val).toLocaleString('id-ID')}` },
    { key: 'status', label: 'Status' },
    { key: 'calories', label: 'Kalori (kcal)' },
    { key: 'preparation_time', label: 'Waktu Siap (mnt)' },
];

const menuItemFields: FormField[] = [
    { key: 'name', label: 'Nama Menu', type: 'text', placeholder: 'Contoh: Nasi Goreng Spesial', required: true },
    { key: 'description', label: 'Deskripsi', type: 'textarea' },
    { key: 'price', label: 'Harga (Rp)', type: 'number', required: true },
    { key: 'category_id', label: 'Kategori', type: 'select', required: true, options: [] },
    { key: 'status', label: 'Status', type: 'select', required: true, options: [{ value: 'available', label: 'Tersedia' }, { value: 'sold_out', label: 'Stok Habis' }, { value: 'draft', label: 'Draft' }] },
    { key: 'image_url', label: 'Gambar Menu', type: 'image' },
    { key: 'calories', label: 'Kalori (kcal)', type: 'number', advanced: true },
    { key: 'preparation_time', label: 'Waktu Persiapan (menit)', type: 'number', advanced: true },
    { key: 'allergens', label: 'Alergen', type: 'tags', advanced: true },
    { key: 'tags', label: 'Tag Menu', type: 'tags', advanced: true },
];

const menuItemBadge = { available: 'success', sold_out: 'danger', draft: 'warning' };
</script>

<template>
    <div class="p-6">
        <div v-if="!selectedMenuItem" class="animate-in fade-in-0 duration-200">
            <CRUDTable resource-name="Menu" api-url="/api/menu-items" :columns="menuItemColumns" :form-fields="menuItemFields" :badge-map="menuItemBadge">
                <template #actions="{ row }">
                    <Button size="icon-sm" variant="ghost" class="text-brand-600 hover:text-brand-700 hover:bg-brand-50" @click="manageMenuCustomizations(row)" title="Kelola Varian & Topping">
                        <ListPlus class="h-4.5 w-4.5" />
                    </Button>
                </template>
            </CRUDTable>
        </div>

        <div v-else class="rounded-xl border bg-card p-6 shadow-sm animate-in fade-in duration-300">
            <div class="flex items-center gap-3 border-b pb-4 mb-6">
                <Button variant="ghost" size="icon-sm" @click="selectedMenuItem = null"><ArrowLeft class="h-4 w-4" /></Button>
                <div>
                    <h2 class="text-lg font-bold text-foreground">Kustomisasi Menu: {{ selectedMenuItem.name }}</h2>
                    <p class="text-xs text-muted-foreground">Kelola extra topping dan varian.</p>
                </div>
            </div>
            <div class="grid gap-8 lg:grid-cols-2">
                <div class="flex flex-col gap-4">
                    <div class="flex items-center justify-between">
                        <h3 class="text-sm font-semibold text-foreground/80 flex items-center gap-2"><UtensilsCrossed class="h-4 w-4 text-brand-600" />Varian & Pilihan</h3>
                        <Button size="sm" variant="outline" class="h-8 text-xs gap-1" @click="menuOptions.push({ name: '', is_required: false, type: 'single', values: [{ name: '', additional_price: 0 }] })"><Plus class="h-3 w-3" />Tambah Opsi</Button>
                    </div>
                    <div v-for="(opt, optIdx) in menuOptions" :key="optIdx" class="border rounded-xl p-4 bg-background/50 relative group shadow-sm">
                        <Button variant="ghost" size="icon-xs" class="absolute top-2 right-2 text-muted-foreground hover:text-destructive opacity-0 group-hover:opacity-100 transition-opacity" @click="menuOptions.splice(optIdx, 1)"><X class="h-3.5 w-3.5" /></Button>
                        <div class="grid gap-4 sm:grid-cols-2 mb-4">
                            <div class="space-y-1.5"><Label class="text-[11px] font-bold uppercase tracking-wider text-muted-foreground">Nama Opsi</Label><Input v-model="opt.name" placeholder="Misal: Level Pedas" class="h-9" /></div>
                            <div class="space-y-1.5"><Label class="text-[11px] font-bold uppercase tracking-wider text-muted-foreground">Tipe Pilihan</Label><select v-model="opt.type" class="flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm shadow-sm transition-colors focus:outline-none focus:ring-1 focus:ring-ring"><option value="single">Pilih Satu (Radio)</option><option value="multiple">Pilih Banyak (Checkbox)</option></select></div>
                            <div class="flex items-center gap-2 sm:col-span-2"><input type="checkbox" v-model="opt.is_required" :id="`req-${optIdx}`" class="h-4 w-4 rounded border-gray-300 text-brand-600 focus:ring-brand-600" /><Label :for="`req-${optIdx}`" class="text-xs cursor-pointer select-none">Wajib diisi oleh pelanggan</Label></div>
                        </div>
                        <div class="space-y-2 border-t pt-4 mt-2">
                            <div class="flex items-center justify-between mb-2"><Label class="text-[11px] font-bold uppercase tracking-wider text-muted-foreground">Pilihan Nilai (Values)</Label><Button variant="ghost" size="xs" class="h-6 text-[10px] text-brand-600 hover:bg-brand-50" @click="opt.values.push({ name: '', additional_price: 0 })">+ Tambah Nilai</Button></div>
                            <div class="space-y-2">
                                <div v-for="(val, valIdx) in opt.values" :key="valIdx" class="flex items-center gap-2">
                                    <Input v-model="val.name" placeholder="Nama nilai" class="h-8 text-xs flex-1" />
                                    <div class="relative w-32"><span class="absolute left-2 top-1/2 -translate-y-1/2 text-[10px] text-muted-foreground">Rp</span><Input v-model="val.additional_price" type="number" class="h-8 text-xs pl-7" /></div>
                                    <Button v-if="opt.values.length > 1" variant="ghost" size="icon-xs" class="h-8 w-8 text-muted-foreground hover:text-destructive" @click="opt.values.splice(valIdx, 1)"><X class="h-3.5 w-3.5" /></Button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="flex flex-col gap-4 border-t lg:border-t-0 lg:border-l lg:pl-8 pt-6 lg:pt-0">
                    <h3 class="text-sm font-semibold text-foreground/80 flex items-center gap-2"><Plus class="h-4 w-4 text-brand-600" />Extra Toppings (Add-ons)</h3>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 mt-2 max-h-[500px] overflow-y-auto pr-2">
                        <div v-for="ao in allAddOns" :key="ao.id" @click="toggleAddOn(ao.id)" class="flex items-center justify-between p-3 rounded-xl border cursor-pointer select-none" :class="selectedAddOnIds.includes(ao.id) ? 'border-brand-500 bg-brand-50/30 ring-1 ring-brand-500' : 'hover:bg-muted/30'">
                            <div class="min-w-0"><p class="text-xs font-bold truncate">{{ ao.name }}</p><p class="text-[10px] text-muted-foreground">+ Rp {{ Number(ao.price).toLocaleString('id-ID') }}</p></div>
                            <div class="h-5 w-5 rounded-full border flex items-center justify-center" :class="selectedAddOnIds.includes(ao.id) ? 'bg-brand-600 border-brand-600 text-white' : 'bg-white'"><Check v-if="selectedAddOnIds.includes(ao.id)" class="h-3 w-3" /></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="flex items-center justify-end gap-3 border-t pt-4 mt-8">
                <Button variant="outline" @click="selectedMenuItem = null">Batal</Button>
                <Button class="bg-brand-600 hover:bg-brand-700 text-white font-semibold flex items-center gap-1.5" :disabled="savingCustomizations" @click="saveMenuCustomizations">
                    <Loader2 v-if="savingCustomizations" class="h-4 w-4 animate-spin" />
                    <Check v-else class="h-4 w-4" />Simpan Kustomisasi
                </Button>
            </div>
        </div>
    </div>
</template>
