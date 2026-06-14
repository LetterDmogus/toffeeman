<script setup lang="ts">
import { ArrowLeft, Check, Eye, Loader2, Plus, Trash } from "lucide-vue-next";
import { onMounted, ref } from "vue";
import type { Column } from "@/components/CRUDTable.vue";
import CRUDTable from "@/components/CRUDTable.vue";
import { Button } from "@/components/ui/button";
import {
	Dialog,
	DialogContent,
	DialogDescription,
	DialogHeader,
	DialogTitle,
} from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";

defineOptions({
	layout: {
		breadcrumbs: [
			{ title: "Dashboard", href: "/dashboard" },
			{ title: "Operasional", href: "#" },
			{ title: "Barang / Peralatan", href: route("ops.inventory") },
			{ title: "Stok Opname", href: "#" },
		],
	},
});

const inventoryItems = ref<any[]>([]);

onMounted(async () => {
	const res = await fetch("/api/inventory-items?all=true", {
		headers: { Accept: "application/json" },
	});

	if (res.ok) {
		inventoryItems.value = await res.json();
	}
});

const columns: Column<any>[] = [
	{
		key: "date",
		label: "Tanggal",
		render: (val) => new Date(val as string).toLocaleDateString("id-ID"),
	},
	{
		key: "opname_number",
		label: "Nomor Opname",
		render: (val) => (val as string) || "—",
	},
	{
		key: "details_count",
		label: "Jumlah Item",
		render: (val) => `${val} Item`,
	},
	{
		key: "creator",
		label: "Petugas Opname",
		render: (val, row) => row.creator?.name || "—",
	},
	{
		key: "notes",
		label: "Catatan/Keterangan",
		render: (val) => (val as string) || "—",
	},
];

// Detail dialog state
const detailItem = ref<any>(null);
const detailDialogOpen = ref(false);

const viewDetails = async (row: any) => {
	const res = await fetch(`/api/inventory-opnames/${row.id}`, {
		headers: { Accept: "application/json" },
	});

	if (res.ok) {
		detailItem.value = await res.json();
		detailDialogOpen.value = true;
	}
};

// Form state for creating record
const formDate = ref(new Date().toISOString().split("T")[0]);
const formNotes = ref("");
const formDetails = ref<any[]>([
	{
		inventory_item_id: "",
		qty_good_physical: 0,
		qty_fair_physical: 0,
		qty_damaged_physical: 0,
	},
]);
const selectedFiles = ref<File[]>([]);
const formErrors = ref<any>({});

const addRow = () => {
	formDetails.value.push({
		inventory_item_id: "",
		qty_good_physical: 0,
		qty_fair_physical: 0,
		qty_damaged_physical: 0,
	});
};

const removeRow = (index: number) => {
	if (formDetails.value.length > 1) {
		formDetails.value.splice(index, 1);
	}
};

const handleFileChange = (e: Event) => {
	const files = (e.target as HTMLInputElement).files;

	if (files) {
		selectedFiles.value = Array.from(files);
	}
};

const resetForm = () => {
	formDate.value = new Date().toISOString().split("T")[0];
	formNotes.value = "";
	formDetails.value = [
		{
			inventory_item_id: "",
			qty_good_physical: 0,
			qty_fair_physical: 0,
			qty_damaged_physical: 0,
		},
	];
	selectedFiles.value = [];
	formErrors.value = {};
};

const submitCustomForm = async (cancel: () => void, refresh: () => void) => {
	formErrors.value = {};
	const formData = new FormData();
	formData.append("date", formDate.value);
	formData.append("notes", formNotes.value);

	// Append details
	formDetails.value.forEach((detail, index) => {
		formData.append(
			`details[${index}][inventory_item_id]`,
			detail.inventory_item_id,
		);
		formData.append(
			`details[${index}][qty_good_physical]`,
			String(detail.qty_good_physical),
		);
		formData.append(
			`details[${index}][qty_fair_physical]`,
			String(detail.qty_fair_physical),
		);
		formData.append(
			`details[${index}][qty_damaged_physical]`,
			String(detail.qty_damaged_physical),
		);
	});

	// Append photos
	selectedFiles.value.forEach((file) => {
		formData.append("photos[]", file);
	});

	try {
		const res = await fetch("/api/inventory-opnames", {
			method: "POST",
			body: formData,
			headers: {
				Accept: "application/json",
				"X-Requested-With": "XMLHttpRequest",
			},
		});

		if (res.ok) {
			resetForm();
			refresh();
			cancel();
		} else {
			const data = await res.json();

			if (data.errors) {
				formErrors.value = data.errors;
			} else {
				alert(data.message || "Terjadi kesalahan saat menyimpan data.");
			}
		}
	} catch (err) {
		console.error(err);
		alert("Terjadi kesalahan jaringan.");
	}
};
</script>

<template>
    <div class="p-6">
        <CRUDTable
            resource-name="Stok Opname"
            api-url="/api/inventory-opnames"
            :columns="columns"
            :form-fields="[]"
            disable-edit
            disable-delete
            :default-visible-columns="['date', 'opname_number', 'details_count', 'creator', 'notes']"
            auditable-type="InventoryOpname"
        >
            <template #actions="{ row }">
                <Button 
                    size="icon-sm" 
                    variant="ghost" 
                    class="text-blue-600 hover:text-blue-700 hover:bg-blue-50" 
                    @click="viewDetails(row)" 
                    title="Cek Detail & Bukti"
                >
                    <Eye class="h-4 w-4" />
                </Button>
            </template>

            <template #form="{ mode, submitting, cancel, refresh }">
                <div class="rounded-xl border bg-card p-6 shadow-sm animate-in fade-in duration-300">
                    <div class="flex items-center gap-3 border-b pb-4 mb-6">
                        <Button variant="ghost" size="icon-sm" @click="cancel">
                            <ArrowLeft class="h-4 w-4" />
                        </Button>
                        <div>
                            <h2 class="text-lg font-bold text-foreground">Catat Stok Opname</h2>
                            <p class="text-xs text-muted-foreground">Input jumlah fisik aktual untuk dicocokkan dengan sistem.</p>
                        </div>
                    </div>

                    <form @submit.prevent="submitCustomForm(cancel, refresh)" class="grid gap-6">
                        <div class="grid gap-4 md:grid-cols-2">
                            <div class="flex flex-col gap-1.5">
                                <Label for="form-date">Tanggal Opname <span class="text-destructive">*</span></Label>
                                <Input id="form-date" type="date" v-model="formDate" required />
                                <p v-if="formErrors.date" class="text-xs text-destructive">{{ formErrors.date[0] }}</p>
                            </div>
                        </div>

                        <div class="flex flex-col gap-1.5">
                            <Label for="form-notes">Catatan Stok Opname</Label>
                            <textarea
                                id="form-notes"
                                v-model="formNotes"
                                rows="3"
                                placeholder="Keterangan opname (Contoh: Opname akhir bulan Juni 2026)..."
                                class="flex w-full rounded-lg border border-input bg-background px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 focus-visible:ring-offset-2"
                            />
                        </div>

                        <!-- Details Section -->
                        <div class="flex flex-col gap-3">
                            <div class="flex items-center justify-between border-b pb-2">
                                <h3 class="text-sm font-semibold text-foreground">Hasil Perhitungan Fisik</h3>
                                <Button type="button" size="sm" variant="outline" @click="addRow" class="h-8 text-brand-600 border-brand-200">
                                    <Plus class="mr-1 h-3.5 w-3.5" /> Tambah Baris
                                </Button>
                            </div>

                            <div class="flex flex-col gap-4">
                                <div
                                    v-for="(detail, index) in formDetails"
                                    :key="index"
                                    class="grid gap-3 p-4 rounded-lg border bg-muted/20 relative"
                                >
                                    <div class="grid gap-3 sm:grid-cols-2 md:grid-cols-5">
                                        <div class="flex flex-col gap-1 md:col-span-2">
                                            <Label class="text-xs text-muted-foreground">Pilih Barang <span class="text-destructive">*</span></Label>
                                            <select
                                                v-model="detail.inventory_item_id"
                                                required
                                                class="flex h-9 w-full rounded-md border border-input bg-background px-3 text-xs shadow-sm focus:outline-none focus:ring-1 focus:ring-brand-500"
                                            >
                                                <option value="" disabled>Pilih Barang...</option>
                                                <option v-for="item in inventoryItems" :key="item.id" :value="item.id">
                                                    {{ item.name }} ({{ item.sku || 'No SKU' }})
                                                </option>
                                            </select>
                                        </div>
                                        <div class="flex flex-col gap-1">
                                            <Label class="text-xs text-muted-foreground">Fisik Baik <span class="text-destructive">*</span></Label>
                                            <Input type="number" step="0.01" min="0" v-model="detail.qty_good_physical" required class="h-9 text-xs" />
                                        </div>
                                        <div class="flex flex-col gap-1">
                                            <Label class="text-xs text-muted-foreground">Fisik Kurang Baik <span class="text-destructive">*</span></Label>
                                            <Input type="number" step="0.01" min="0" v-model="detail.qty_fair_physical" required class="h-9 text-xs" />
                                        </div>
                                        <div class="flex flex-col gap-1">
                                            <Label class="text-xs text-muted-foreground">Fisik Rusak <span class="text-destructive">*</span></Label>
                                            <Input type="number" step="0.01" min="0" v-model="detail.qty_damaged_physical" required class="h-9 text-xs" />
                                        </div>
                                    </div>

                                    <div class="flex justify-end mt-2">
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="sm"
                                            class="text-destructive hover:bg-destructive/10 text-xs h-9"
                                            :disabled="formDetails.length === 1"
                                            @click="removeRow(index)"
                                        >
                                            <Trash class="mr-1 h-3.5 w-3.5" /> Hapus Baris
                                        </Button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Photos Upload Section -->
                        <div class="flex flex-col gap-1.5 border-t pt-4">
                            <Label for="form-photos">Unggah Foto Bukti Fisik / Lembar Opname (Bisa pilih banyak sekaligus)</Label>
                            <input
                                id="form-photos"
                                type="file"
                                multiple
                                accept="image/*"
                                @change="handleFileChange"
                                class="text-xs file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-xs file:font-semibold file:bg-brand-50 file:text-brand-700 hover:file:bg-brand-100 cursor-pointer"
                            />
                            <p v-if="selectedFiles.length > 0" class="text-xs text-muted-foreground mt-1">
                                {{ selectedFiles.length }} foto terpilih.
                            </p>
                        </div>

                        <div class="flex items-center justify-end gap-3 border-t pt-4">
                            <Button type="button" variant="outline" @click="cancel" :disabled="submitting">
                                Batal
                            </Button>
                            <Button type="submit" class="bg-brand-600 hover:bg-brand-700 text-white" :disabled="submitting">
                                <Loader2 v-if="submitting" class="mr-2 h-4 w-4 animate-spin" />
                                <Check v-else class="mr-2 h-4 w-4" />
                                Simpan Data
                            </Button>
                        </div>
                    </form>
                </div>
            </template>
        </CRUDTable>

        <!-- 🔍 DETAIL DIALOG -->
        <Dialog v-model:open="detailDialogOpen">
            <DialogContent class="sm:max-w-4xl max-h-[85vh] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle>Detail Hasil Stok Opname</DialogTitle>
                    <DialogDescription>
                        Perbandingan stok sistem vs perhitungan fisik aktual.
                    </DialogDescription>
                </DialogHeader>

                <div v-if="detailItem" class="grid gap-6 py-2">
                    <div class="grid gap-3 sm:grid-cols-2 text-xs border-b pb-4">
                        <div>
                            <span class="font-bold text-muted-foreground">Tanggal Opname:</span>
                            <p class="text-foreground mt-0.5">{{ new Date(detailItem.date).toLocaleDateString('id-ID', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' }) }}</p>
                        </div>
                        <div>
                            <span class="font-bold text-muted-foreground">Nomor Dokumen:</span>
                            <p class="text-foreground mt-0.5 font-mono">{{ detailItem.opname_number || '—' }}</p>
                        </div>
                        <div>
                            <span class="font-bold text-muted-foreground">Petugas Opname:</span>
                            <p class="text-foreground mt-0.5">{{ detailItem.creator?.name || '—' }}</p>
                        </div>
                        <div>
                            <span class="font-bold text-muted-foreground">Catatan/Keterangan:</span>
                            <p class="text-foreground mt-0.5">{{ detailItem.notes || '—' }}</p>
                        </div>
                    </div>

                    <!-- Items list -->
                    <div>
                        <h4 class="text-xs font-bold text-muted-foreground uppercase mb-2">Perbandingan Stok</h4>
                        <div class="rounded-lg border overflow-hidden">
                            <table class="w-full text-xs text-left">
                                <thead class="bg-muted">
                                    <tr class="border-b">
                                        <th class="px-3 py-2" rowspan="2">Nama Barang</th>
                                        <th class="px-3 py-2 text-center border-r" colspan="2">Kondisi Baik</th>
                                        <th class="px-3 py-2 text-center border-r" colspan="2">Kurang Baik</th>
                                        <th class="px-3 py-2 text-center border-r" colspan="2">Kondisi Rusak</th>
                                        <th class="px-3 py-2 text-center" rowspan="2">Selisih Total</th>
                                    </tr>
                                    <tr class="border-b">
                                        <th class="px-2 py-1 text-center font-normal text-muted-foreground">Sistem</th>
                                        <th class="px-2 py-1 text-center font-normal border-r">Fisik</th>
                                        <th class="px-2 py-1 text-center font-normal text-muted-foreground">Sistem</th>
                                        <th class="px-2 py-1 text-center font-normal border-r">Fisik</th>
                                        <th class="px-2 py-1 text-center font-normal text-muted-foreground">Sistem</th>
                                        <th class="px-2 py-1 text-center font-normal border-r">Fisik</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="d in detailItem.details" :key="d.id" class="border-b last:border-0 hover:bg-muted/20">
                                        <td class="px-3 py-2 font-medium">{{ d.item?.name || '—' }}</td>
                                        <td class="px-2 py-2 text-center text-muted-foreground font-mono">{{ d.qty_good_system }}</td>
                                        <td class="px-2 py-2 text-center font-bold font-mono border-r">{{ d.qty_good_physical }}</td>
                                        <td class="px-2 py-2 text-center text-muted-foreground font-mono">{{ d.qty_fair_system }}</td>
                                        <td class="px-2 py-2 text-center font-bold font-mono border-r">{{ d.qty_fair_physical }}</td>
                                        <td class="px-2 py-2 text-center text-muted-foreground font-mono">{{ d.qty_damaged_system }}</td>
                                        <td class="px-2 py-2 text-center font-bold font-mono border-r">{{ d.qty_damaged_physical }}</td>
                                        <td class="px-3 py-2 text-center font-mono">
                                            <span 
                                                :class="{
                                                    'text-emerald-600 font-bold': (Number(d.qty_good_physical) + Number(d.qty_fair_physical) + Number(d.qty_damaged_physical)) - (Number(d.qty_good_system) + Number(d.qty_fair_system) + Number(d.qty_damaged_system)) > 0,
                                                    'text-rose-600 font-bold': (Number(d.qty_good_physical) + Number(d.qty_fair_physical) + Number(d.qty_damaged_physical)) - (Number(d.qty_good_system) + Number(d.qty_fair_system) + Number(d.qty_damaged_system)) < 0,
                                                    'text-muted-foreground': (Number(d.qty_good_physical) + Number(d.qty_fair_physical) + Number(d.qty_damaged_physical)) - (Number(d.qty_good_system) + Number(d.qty_fair_system) + Number(d.qty_damaged_system)) === 0
                                                }"
                                            >
                                                {{ 
                                                    ((Number(d.qty_good_physical) + Number(d.qty_fair_physical) + Number(d.qty_damaged_physical)) - 
                                                    (Number(d.qty_good_system) + Number(d.qty_fair_system) + Number(d.qty_damaged_system))).toFixed(2)
                                                }}
                                            </span>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Attachments -->
                    <div v-if="detailItem.attachments && detailItem.attachments.length > 0">
                        <h4 class="text-xs font-bold text-muted-foreground uppercase mb-2">Foto Bukti Fisik / Lembar Opname</h4>
                        <div class="grid gap-4 grid-cols-2 sm:grid-cols-3">
                            <div v-for="img in detailItem.attachments" :key="img.id" class="relative group rounded-lg overflow-hidden border aspect-video bg-muted">
                                <a :href="img.url" target="_blank" title="Buka Gambar">
                                    <img :src="img.url" alt="Foto Bukti" class="w-full h-full object-cover transition-transform group-hover:scale-105" />
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </DialogContent>
        </Dialog>
    </div>
</template>
