<script setup lang="ts">
import { ArrowLeft, Check, Loader2, ArrowRightLeft, Eye } from "lucide-vue-next";
import { onMounted, ref, watch } from "vue";
import type { Column } from "@/components/CRUDTable.vue";
import CRUDTable from "@/components/CRUDTable.vue";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import {
	Dialog,
	DialogContent,
	DialogDescription,
	DialogHeader,
	DialogTitle,
} from "@/components/ui/dialog";
import ops from "@/routes/ops";

defineOptions({
	layout: {
		breadcrumbs: [
			{ title: "Dashboard", href: "/dashboard" },
			{ title: "Operasional", href: "#" },
			{ title: "Barang / Peralatan", href: ops.inventory().url },
			{ title: "Mutasi Stok", href: "#" },
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
		render: (val) => val ? new Date(val as string).toLocaleDateString("id-ID") : "—",
	},
	{
		key: "reference_number",
		label: "No. Mutasi",
		render: (val) => (val as string) || "—",
	},
	{
		key: "item_name",
		label: "Nama Barang",
		render: (val) => (val as string) || "—",
	},
	{
		key: "qty",
		label: "Jumlah",
		render: (val) => (val as string) || "—",
	},
	{
		key: "from_status",
		label: "Status Asal",
		render: (val) => (val as string) || "—",
	},
	{
		key: "to_status",
		label: "Status Tujuan",
		render: (val) => (val as string) || "—",
	},
	{
		key: "creator",
		label: "Petugas",
		render: (val) => (val as string) || "—",
	},
	{
		key: "notes",
		label: "Catatan/Keterangan",
		render: (val) => (val as string) || "—",
	},
];

const detailItem = ref<any>(null);
const detailDialogOpen = ref(false);

const viewDetails = (row: any) => {
	detailItem.value = row;
	detailDialogOpen.value = true;
};

// Form state for creating mutation
const selectedItemId = ref("");
const fromStatus = ref("qty_good");
const toStatus = ref("qty_fair");
const mutationQty = ref(1);
const formNotes = ref("");
const formErrors = ref<any>({});
const activeItemInfo = ref<any>(null);

watch(selectedItemId, (newId) => {
	if (newId) {
		activeItemInfo.value = inventoryItems.value.find((item) => item.id === Number(newId)) || null;
	} else {
		activeItemInfo.value = null;
	}
});

const resetForm = () => {
	selectedItemId.value = "";
	fromStatus.value = "qty_good";
	toStatus.value = "qty_fair";
	mutationQty.value = 1;
	formNotes.value = "";
	formErrors.value = {};
	activeItemInfo.value = null;
};

const submitMutationForm = async (cancel: () => void, refresh: () => void) => {
	formErrors.value = {};
	
	try {
		const res = await fetch("/api/inventory-mutations", {
			method: "POST",
			headers: {
				"Content-Type": "application/json",
				Accept: "application/json",
				"X-Requested-With": "XMLHttpRequest",
			},
			body: JSON.stringify({
				inventory_item_id: selectedItemId.value,
				from_status: fromStatus.value,
				to_status: toStatus.value,
				qty: mutationQty.value,
				notes: formNotes.value,
			}),
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
			resource-name="Mutasi Stok"
			api-url="/api/inventory-mutations"
			:columns="columns"
			:form-fields="[]"
			disable-edit
			disable-delete
			:default-visible-columns="['date', 'reference_number', 'item_name', 'qty', 'from_status', 'to_status', 'creator', 'notes']"
			auditable-type="InventoryOut"
		>
			<template #actions="{ row }">
				<Button
					size="icon-sm"
					variant="ghost"
					class="text-blue-600 hover:text-blue-700 hover:bg-blue-50"
					@click="viewDetails(row)"
					title="Cek Detail Mutasi"
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
							<h2 class="text-lg font-bold text-foreground">Proses Mutasi Status Stok</h2>
							<p class="text-xs text-muted-foreground">Pindahkan barang antar status kualitas secara internal.</p>
						</div>
					</div>

					<form @submit.prevent="submitMutationForm(cancel, refresh)" class="grid gap-6 w-full">
						<div class="flex flex-col gap-1.5">
							<Label for="form-item">Pilih Barang <span class="text-destructive">*</span></Label>
							<select
								id="form-item"
								v-model="selectedItemId"
								required
								class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-sm focus:outline-none focus:ring-1 focus:ring-brand-500"
							>
								<option value="" disabled>Pilih Barang...</option>
								<option v-for="item in inventoryItems" :key="item.id" :value="item.id">
									{{ item.name }} ({{ item.sku || 'No SKU' }})
								</option>
							</select>
							<p v-if="formErrors.inventory_item_id" class="text-xs text-destructive">{{ formErrors.inventory_item_id[0] }}</p>
						</div>

						<!-- Stock Status Info -->
						<div v-if="activeItemInfo" class="p-4 rounded-xl border bg-muted/30 grid grid-cols-3 gap-4 text-center">
							<div>
								<span class="text-xs text-muted-foreground block font-medium">Stok Baik</span>
								<span class="text-sm font-bold text-emerald-600">
									{{ activeItemInfo.qty_good }} {{ activeItemInfo.unit }}
								</span>
							</div>
							<div>
								<span class="text-xs text-muted-foreground block font-medium">Stok Kurang Baik</span>
								<span class="text-sm font-bold text-amber-600">
									{{ activeItemInfo.qty_fair }} {{ activeItemInfo.unit }}
								</span>
							</div>
							<div>
								<span class="text-xs text-muted-foreground block font-medium">Stok Rusak</span>
								<span class="text-sm font-bold text-destructive">
									{{ activeItemInfo.qty_damaged }} {{ activeItemInfo.unit }}
								</span>
							</div>
						</div>

						<div class="grid gap-4 md:grid-cols-2">
							<div class="flex flex-col gap-1.5">
								<Label for="form-from-status">Status Asal <span class="text-destructive">*</span></Label>
								<select
									id="form-from-status"
									v-model="fromStatus"
									required
									class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-sm focus:outline-none focus:ring-1 focus:ring-brand-500"
								>
									<option value="qty_good">Baik</option>
									<option value="qty_fair">Kurang Baik</option>
									<option value="qty_damaged">Rusak</option>
								</select>
								<p v-if="formErrors.from_status" class="text-xs text-destructive">{{ formErrors.from_status[0] }}</p>
							</div>

							<div class="flex flex-col gap-1.5">
								<Label for="form-to-status">Status Tujuan <span class="text-destructive">*</span></Label>
								<select
									id="form-to-status"
									v-model="toStatus"
									required
									class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-sm focus:outline-none focus:ring-1 focus:ring-brand-500"
								>
									<option value="qty_good">Baik</option>
									<option value="qty_fair">Kurang Baik</option>
									<option value="qty_damaged">Rusak</option>
								</select>
								<p v-if="formErrors.to_status" class="text-xs text-destructive">{{ formErrors.to_status[0] }}</p>
							</div>
						</div>

						<div class="flex flex-col gap-1.5">
							<Label for="form-qty">Jumlah Mutasi <span class="text-destructive">*</span></Label>
							<div class="flex items-center gap-2">
								<Input
									id="form-qty"
									type="number"
									step="0.01"
									min="0.01"
									v-model="mutationQty"
									required
									class="max-w-[200px]"
								/>
								<span class="text-sm font-semibold text-muted-foreground" v-if="activeItemInfo">
									{{ activeItemInfo.unit }}
								</span>
							</div>
							<p v-if="formErrors.qty" class="text-xs text-destructive">{{ formErrors.qty[0] }}</p>
						</div>

						<div class="flex flex-col gap-1.5">
							<Label for="form-notes">Catatan / Alasan Mutasi</Label>
							<textarea
								id="form-notes"
								v-model="formNotes"
								rows="3"
								placeholder="Tulis alasan mutasi status (Contoh: Beras lembab dipindahkan dari Baik ke Kurang Baik)..."
								class="flex w-full rounded-lg border border-input bg-background px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 focus-visible:ring-offset-2"
							/>
							<p v-if="formErrors.notes" class="text-xs text-destructive">{{ formErrors.notes[0] }}</p>
						</div>

						<div class="flex justify-end gap-3 border-t pt-4">
							<Button type="button" variant="outline" @click="cancel">Batal</Button>
							<Button type="submit" :disabled="submitting" class="bg-brand-600 hover:bg-brand-700 text-white">
								<Loader2 class="mr-2 h-4 w-4 animate-spin" v-if="submitting" />
								<ArrowRightLeft class="mr-2 h-4 w-4" v-else />
								Kirim Mutasi
							</Button>
						</div>
					</form>
				</div>
			</template>
		</CRUDTable>

		<!-- 🔍 DETAIL DIALOG -->
		<Dialog v-model:open="detailDialogOpen">
			<DialogContent class="sm:max-w-md max-h-[85vh] overflow-y-auto">
				<DialogHeader>
					<DialogTitle>Detail Mutasi Status Stok</DialogTitle>
					<DialogDescription>
						Informasi perpindahan status kualitas stok barang.
					</DialogDescription>
				</DialogHeader>

				<div v-if="detailItem" class="grid gap-4 py-2 text-sm">
					<div class="grid grid-cols-2 gap-2 border-b pb-3 text-xs">
						<div>
							<span class="font-bold text-muted-foreground block">Tanggal:</span>
							<span class="text-foreground font-medium">
								{{ detailItem.date ? new Date(detailItem.date).toLocaleDateString('id-ID', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' }) : '—' }}
							</span>
						</div>
						<div>
							<span class="font-bold text-muted-foreground block">No. Mutasi:</span>
							<span class="text-foreground font-mono font-medium">{{ detailItem.reference_number }}</span>
						</div>
					</div>

					<div class="space-y-3">
						<div>
							<span class="font-bold text-xs text-muted-foreground block">Nama Barang:</span>
							<span class="text-foreground font-semibold text-base">{{ detailItem.item_name }}</span>
						</div>

						<div class="grid grid-cols-3 gap-2 p-3 rounded-lg border bg-muted/20 items-center text-center">
							<div>
								<span class="text-xs text-muted-foreground block font-medium">Status Asal</span>
								<span class="text-xs font-bold text-amber-600 uppercase">{{ detailItem.from_status }}</span>
							</div>
							<div class="flex justify-center text-muted-foreground">
								<ArrowRightLeft class="h-4 w-4" />
							</div>
							<div>
								<span class="text-xs text-muted-foreground block font-medium">Status Tujuan</span>
								<span class="text-xs font-bold text-emerald-600 uppercase">{{ detailItem.to_status }}</span>
							</div>
						</div>

						<div class="grid grid-cols-2 gap-2 pt-2">
							<div>
								<span class="font-bold text-xs text-muted-foreground block">Jumlah Kuantitas:</span>
								<span class="text-foreground font-bold text-lg">{{ detailItem.qty }}</span>
							</div>
							<div>
								<span class="font-bold text-xs text-muted-foreground block">Petugas:</span>
								<span class="text-foreground font-medium">{{ detailItem.creator }}</span>
							</div>
						</div>

						<div class="border-t pt-2 mt-2">
							<span class="font-bold text-xs text-muted-foreground block">Catatan / Keterangan:</span>
							<p class="text-foreground mt-1 text-xs italic bg-secondary/50 p-2.5 rounded-lg border border-dashed">
								"{{ detailItem.notes || '—' }}"
							</p>
						</div>
					</div>
				</div>
			</DialogContent>
		</Dialog>
	</div>
</template>
