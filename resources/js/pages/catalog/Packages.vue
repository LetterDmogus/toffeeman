<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { Gift, ListPlus, ArrowLeft, Loader2, Check, Search, Trash2, X } from 'lucide-vue-next';
import CRUDTable from '@/components/CRUDTable.vue';
import type { Column, FormField } from '@/components/CRUDTable.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import catalog from '@/routes/catalog';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: '/dashboard' },
            { title: 'Katalog Produk', href: '#' },
            { title: 'Paket Menu', href: catalog.packages().url },
        ],
    },
});

const selectedPackage = ref<any | null>(null);
const allMenuItems = ref<any[]>([]);
const allInventoryItems = ref<any[]>([]);
const packageItems = ref<{ menu_item_id: number | null; inventory_item_id: number | null; type: 'menu_item' | 'inventory_item'; name: string; qty: number; notes: string }[]>([]);
const packageSearchQuery = ref('');
const savingPackageItems = ref(false);
const activeTab = ref<'menu' | 'inventory'>('menu');

const managePackageItems = async (pkg: any) => {
    selectedPackage.value = pkg;
    try {
        const res = await fetch(`/api/packages/${pkg.id}`, { headers: { Accept: 'application/json' } });
        if (res.ok) {
            const data = await res.json();
            packageItems.value = data.package_items?.map((item: any) => {
                if (item.menu_item_id) {
                    return {
                        menu_item_id: item.menu_item_id,
                        inventory_item_id: null,
                        type: 'menu_item',
                        name: item.menu_item?.name || '—',
                        qty: item.qty,
                        notes: item.notes || ''
                    };
                } else {
                    return {
                        menu_item_id: null,
                        inventory_item_id: item.inventory_item_id,
                        type: 'inventory_item',
                        name: item.inventory_item?.name || '—',
                        qty: item.qty,
                        notes: item.notes || ''
                    };
                }
            }) || [];
        }
        
        // Fetch menus
        const menuRes = await fetch('/api/menu-items?per_page=100', { headers: { Accept: 'application/json' } });
        if (menuRes.ok) allMenuItems.value = (await menuRes.json()).data || [];

        // Fetch inventory items (Barang)
        const invRes = await fetch('/api/inventory-items?per_page=100', { headers: { Accept: 'application/json' } });
        if (invRes.ok) allInventoryItems.value = (await invRes.json()).data || [];
    } catch (e) { console.error(e); }
};

const savePackageItems = async () => {
    if (!selectedPackage.value) return;
    savingPackageItems.value = true;
    try {
        const res = await fetch(`/api/packages/${selectedPackage.value.id}/items`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as any)?.content || '' },
            body: JSON.stringify({
                items: packageItems.value.map(i => ({
                    menu_item_id: i.menu_item_id,
                    inventory_item_id: i.inventory_item_id,
                    qty: i.qty,
                    notes: i.notes
                }))
            })
        });
        if (res.ok) selectedPackage.value = null;
    } catch (e) { console.error(e); } finally { savingPackageItems.value = false; }
};

const deletePackageItem = (item: any) => {
    if (item.type === 'menu_item') {
        packageItems.value = packageItems.value.filter(i => i.menu_item_id !== item.menu_item_id);
    } else {
        packageItems.value = packageItems.value.filter(i => i.inventory_item_id !== item.inventory_item_id);
    }
};

const isItemAlreadySelected = (item: any, type: 'menu' | 'inventory') => {
    if (type === 'menu') {
        return packageItems.value.some(i => i.menu_item_id === item.id);
    } else {
        return packageItems.value.some(i => i.inventory_item_id === item.id);
    }
};

const selectItem = (item: any, type: 'menu' | 'inventory') => {
    if (type === 'menu') {
        packageItems.value.push({
            menu_item_id: item.id,
            inventory_item_id: null,
            type: 'menu_item',
            name: item.name,
            qty: 1,
            notes: ''
        });
    } else {
        packageItems.value.push({
            menu_item_id: null,
            inventory_item_id: item.id,
            type: 'inventory_item',
            name: item.name,
            qty: 1,
            notes: ''
        });
    }
};

const columns: Column<any>[] = [
    { key: 'name', label: 'Nama Paket' },
    { key: 'price', label: 'Harga', render: (val) => `Rp ${Number(val).toLocaleString('id-ID')}` },
    { key: 'status', label: 'Status' },
];

const fields: FormField[] = [
    { key: 'name', label: 'Nama Paket', type: 'text', required: true },
    { key: 'description', label: 'Deskripsi', type: 'textarea' },
    { key: 'price', label: 'Harga (Rp)', type: 'number', required: true },
    { key: 'status', label: 'Status', type: 'select', required: true, options: [{ value: 'active', label: 'Aktif' }, { value: 'inactive', label: 'Tidak Aktif' }] },
    { key: 'image_url', label: 'Gambar Paket', type: 'image' },
];

const badgeMap = { active: 'success', inactive: 'warning' };
</script>

<template>
    <div class="p-6">
        <div v-if="!selectedPackage" class="animate-in fade-in-0 duration-200">
            <CRUDTable resource-name="Paket" api-url="/api/packages" :columns="columns" :form-fields="fields" :badge-map="badgeMap">
                <template #actions="{ row }">
                    <Button size="icon-sm" variant="ghost" class="text-emerald-600 hover:bg-emerald-50" @click="managePackageItems(row)"><ListPlus class="h-4.5 w-4.5" /></Button>
                </template>
            </CRUDTable>
        </div>

        <div v-else class="rounded-xl border bg-card p-6 shadow-sm animate-in fade-in duration-300">
            <div class="flex items-center justify-between border-b pb-4 mb-6">
                <div class="flex items-center gap-3">
                    <Button variant="ghost" size="icon-sm" @click="selectedPackage = null"><ArrowLeft class="h-4 w-4" /></Button>
                    <h2 class="text-lg font-bold">Isi Paket: {{ selectedPackage.name }}</h2>
                </div>
            </div>
            <div class="grid gap-6 lg:grid-cols-3">
                <div class="lg:col-span-2 flex flex-col gap-4">
                    <div v-for="item in packageItems" :key="item.menu_item_id || item.inventory_item_id" class="flex items-center justify-between p-4 border rounded-xl bg-background/50">
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-semibold">{{ item.name }}</p>
                            <span class="text-xxs uppercase px-2 py-0.5 rounded font-bold" :class="item.type === 'menu_item' ? 'bg-blue-100 text-blue-700' : 'bg-orange-100 text-orange-700'">
                                {{ item.type === 'menu_item' ? 'Menu' : 'Barang' }}
                            </span>
                        </div>
                        <div class="flex items-center gap-2">
                            <Button variant="outline" size="icon-xs" @click="item.qty > 1 ? item.qty-- : deletePackageItem(item)">-</Button>
                            <span class="w-8 text-center font-bold">{{ item.qty }}</span>
                            <Button variant="outline" size="icon-xs" @click="item.qty++">+</Button>
                        </div>
                        <Input v-model="item.notes" placeholder="Catatan" class="h-9 max-w-[150px] ml-4 text-xs" />
                        <Button variant="ghost" size="icon-sm" class="text-destructive ml-2" @click="deletePackageItem(item)"><Trash2 class="h-4 w-4" /></Button>
                    </div>
                </div>
                <div class="flex flex-col gap-4 border-l pl-6">
                    <div class="flex border-b mb-1">
                        <button @click="activeTab = 'menu'" :class="['flex-1 pb-2 text-xs font-bold border-b-2 transition-all', activeTab === 'menu' ? 'border-emerald-600 text-emerald-600' : 'border-transparent text-muted-foreground']">Menu</button>
                        <button @click="activeTab = 'inventory'" :class="['flex-1 pb-2 text-xs font-bold border-b-2 transition-all', activeTab === 'inventory' ? 'border-emerald-600 text-emerald-600' : 'border-transparent text-muted-foreground']">Barang</button>
                    </div>
                    <Input v-model="packageSearchQuery" :placeholder="activeTab === 'menu' ? 'Cari menu...' : 'Cari barang...'" class="h-10" />
                    
                    <!-- Search Results -->
                    <div class="overflow-y-auto max-h-[400px] divide-y border rounded-xl">
                        <!-- Menu Items -->
                        <template v-if="activeTab === 'menu'">
                            <div v-for="item in allMenuItems.filter(i => i.name.toLowerCase().includes(packageSearchQuery.toLowerCase()))" :key="item.id" class="flex items-center justify-between p-3 hover:bg-muted/40 transition-colors">
                                <p class="text-xs font-semibold">{{ item.name }}</p>
                                <Button size="sm" variant="outline" class="h-7 text-xs" :disabled="isItemAlreadySelected(item, 'menu')" @click="selectItem(item, 'menu')">Pilih</Button>
                            </div>
                        </template>

                        <!-- Inventory Items (Barang) -->
                        <template v-else>
                            <div v-for="item in allInventoryItems.filter(i => i.name.toLowerCase().includes(packageSearchQuery.toLowerCase()))" :key="item.id" class="flex items-center justify-between p-3 hover:bg-muted/40 transition-colors">
                                <div>
                                    <p class="text-xs font-semibold">{{ item.name }}</p>
                                    <span class="text-xxs text-muted-foreground">Stok: {{ item.qty }} {{ item.unit }}</span>
                                </div>
                                <Button size="sm" variant="outline" class="h-7 text-xs" :disabled="isItemAlreadySelected(item, 'inventory')" @click="selectItem(item, 'inventory')">Pilih</Button>
                            </div>
                        </template>
                    </div>
                </div>
            </div>
            <div class="flex items-center justify-end gap-3 border-t pt-4 mt-6">
                <Button variant="outline" @click="selectedPackage = null">Batal</Button>
                <Button class="bg-emerald-600 hover:bg-emerald-700 text-white font-semibold" :disabled="savingPackageItems" @click="savePackageItems">Simpan Isi Paket</Button>
            </div>
        </div>
    </div>
</template>
