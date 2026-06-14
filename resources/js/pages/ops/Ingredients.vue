<script setup lang="ts">
import { ArrowLeft, Check, Loader2, Layers, Trash2, Plus } from "lucide-vue-next";
import { onMounted, ref, watch } from "vue";
import type { Column } from "@/components/CRUDTable.vue";
import CRUDTable from "@/components/CRUDTable.vue";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Badge } from "@/components/ui/badge";

const isExpired = (dateStr: string) => {
	if (!dateStr) return false;
	const today = new Date();
	today.setHours(0, 0, 0, 0);
	const expDate = new Date(dateStr);
	expDate.setHours(0, 0, 0, 0);
	return expDate < today;
};

defineOptions({
	layout: {
		breadcrumbs: [
			{ title: "Dashboard", href: "/dashboard" },
			{ title: "Operasional", href: "#" },
			{ title: "Barang / Peralatan", href: route("ops.inventory") },
			{ title: "Bahan Baku", href: "#" },
		],
	},
});

const ingredientCategories = ref<any[]>([]);
const tableKey = ref(0);

const refreshCategories = async () => {
	const res = await fetch("/api/ingredient-categories?all=true", {
		headers: { Accept: "application/json" },
	});

	if (res.ok) {
		ingredientCategories.value = await res.json();
	}
};

onMounted(refreshCategories);

const columns: Column<any>[] = [
	{ key: "name", label: "Nama Bahan" },
	{ key: "sku", label: "SKU" },
	{
		key: "ingredient_category_id",
		label: "Kategori",
		render: (val, row) => row.category?.name || "—",
	},
	{
		key: "qty",
		label: "Total Stok",
		render: (val, row) => `${val} ${row.unit}`,
	},
	{
		key: "small_unit_qty",
		label: "Satuan Kecil",
		render: (val, row) => `${val} ${row.small_unit || 'g/ml'}`,
	},
	{
		key: "storage_temperature",
		label: "Suhu Penyimpanan",
		render: (val) => (val ? `${val}` : "—"),
	},
	{
		key: "created_by",
		label: "Dibuat Oleh",
		render: (val, row) => row.creator?.name || "—",
	},
	{ key: "status", label: "Status" },
];

const badgeMap = {
	in_stock: "success",
	low_stock: "warning",
	out_of_stock: "danger",
};

// Custom Form States
const formName = ref("");
const formSku = ref("");
const formCategoryId = ref("");
const formPrice = ref(0);
const formUnit = ref("");
const formSmallUnit = ref("");
const formConversionFactor = ref(1);
const formMinQty = ref(0);
const formStorageTemperature = ref("");
const formDescription = ref("");
const editingId = ref<number | null>(null);
const formErrors = ref<any>({});

// Auto fill and auto calculate conversion factor based on units
watch(formUnit, (newUnit) => {
	const u = newUnit?.toLowerCase().trim();
	if (!u) return;

	const su = formSmallUnit.value?.toLowerCase().trim();
	// Only auto-fill if small unit is empty or has a default we can overwrite
	if (!su || su === "gram" || su === "g" || su === "ml" || su === "mililiter" || su === "pcs") {
		if (u === "kg") {
			formSmallUnit.value = "gram";
			formConversionFactor.value = 1000;
		} else if (u === "l" || u === "liter") {
			formSmallUnit.value = "ml";
			formConversionFactor.value = 1000;
		} else if (u === "ons") {
			formSmallUnit.value = "gram";
			formConversionFactor.value = 100;
		} else if (["pcs", "pack", "box", "botol", "porsi", "butir", "ikat", "bungkus", "buah", "kaleng", "lembar"].includes(u)) {
			formSmallUnit.value = "pcs";
			formConversionFactor.value = 1;
		}
	}
});

watch([formUnit, formSmallUnit], ([newUnit, newSmallUnit]) => {
	const u = newUnit?.toLowerCase().trim();
	const su = newSmallUnit?.toLowerCase().trim();
	
	if (!u || !su) return;
	
	// kg -> gram / g: 1000
	if (u === "kg" && (su === "gram" || su === "g")) {
		formConversionFactor.value = 1000;
	}
	// kg -> ons: 10
	else if (u === "kg" && su === "ons") {
		formConversionFactor.value = 10;
	}
	// l -> ml / mililiter: 1000
	else if ((u === "l" || u === "liter") && (su === "ml" || su === "mililiter" || su === "mili liter")) {
		formConversionFactor.value = 1000;
	}
	// ons -> gram: 100
	else if (u === "ons" && (su === "gram" || su === "g")) {
		formConversionFactor.value = 100;
	}
	// Same unit: 1
	else if (u === su) {
		formConversionFactor.value = 1;
	}
});

const handleFormOpened = ({ mode, row }: { mode: "create" | "edit"; row: any }) => {
	formErrors.value = {};
	if (mode === "edit" && row) {
		editingId.value = row.id;
		formName.value = row.name || "";
		formSku.value = row.sku || "";
		formCategoryId.value = row.ingredient_category_id || "";
		formPrice.value = Number(row.price) || 0;
		formUnit.value = row.unit || "";
		formSmallUnit.value = row.small_unit || "";
		formConversionFactor.value = Number(row.conversion_factor) || 1;
		formMinQty.value = Number(row.min_qty) || 0;
		formStorageTemperature.value = row.storage_temperature || "";
		formDescription.value = row.description || "";
	} else {
		editingId.value = null;
		formName.value = "";
		formSku.value = "";
		formCategoryId.value = "";
		formPrice.value = 0;
		formUnit.value = "";
		formSmallUnit.value = "";
		formConversionFactor.value = 1;
		formMinQty.value = 0;
		formStorageTemperature.value = "";
		formDescription.value = "";
	}
};

const submitCustomForm = async (cancel: () => void, refresh: () => void) => {
	formErrors.value = {};
	
	const payload = {
		name: formName.value,
		sku: formSku.value || null,
		ingredient_category_id: formCategoryId.value,
		price: formPrice.value,
		unit: formUnit.value,
		small_unit: formSmallUnit.value,
		conversion_factor: formConversionFactor.value,
		min_qty: formMinQty.value,
		storage_temperature: formStorageTemperature.value || null,
		description: formDescription.value || null,
	};
	
	const url = editingId.value 
		? `/api/ingredients/${editingId.value}`
		: "/api/ingredients";
		
	const method = editingId.value ? "PUT" : "POST";
	
	try {
		const res = await fetch(url, {
			method,
			headers: {
				"Content-Type": "application/json",
				Accept: "application/json",
				"X-Requested-With": "XMLHttpRequest",
				"X-CSRF-TOKEN": (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || "",
			},
			body: JSON.stringify(payload),
		});

		if (res.ok) {
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

// ─── View Switching State ────────────────────────────────────────────────────
const viewMode = ref<"ingredients" | "batches">("ingredients");
const selectedIngredient = ref<any>(null);
const batches = ref<any[]>([]);
const addingBatch = ref(false);
const newBatch = ref({
	batch_number: "",
	qty: 0,
	price: 0,
	expiration_date: "",
});

const switchToBatches = async (ingredient: any) => {
	selectedIngredient.value = ingredient;
	newBatch.value = { batch_number: "", qty: 0, price: 0, expiration_date: "" };
	await fetchBatches();
	viewMode.value = "batches";
};

const goBack = () => {
	viewMode.value = "ingredients";
	tableKey.value++; // Force main table reload to update stock quantities
};

const fetchBatches = async () => {
	if (!selectedIngredient.value) {
		return;
	}

	const res = await fetch(
		`/api/ingredient-batches?ingredient_id=${selectedIngredient.value.id}`,
		{
			headers: { Accept: "application/json" },
		},
	);

	if (res.ok) {
		const json = await res.json();
		batches.value = json.data || [];
	}
};

const addBatch = async () => {
	if (!selectedIngredient.value) {
		return;
	}

	addingBatch.value = true;

	try {
		const res = await fetch("/api/ingredient-batches", {
			method: "POST",
			headers: {
				"Content-Type": "application/json",
				Accept: "application/json",
				"X-CSRF-TOKEN":
					(document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)
						?.content || "",
			},
			body: JSON.stringify({
				ingredient_id: selectedIngredient.value.id,
				...newBatch.value,
			}),
		});

		if (res.ok) {
			newBatch.value = { batch_number: "", qty: 0, price: 0, expiration_date: "" };
			await fetchBatches();
		}
	} finally {
		addingBatch.value = false;
	}
};

const deleteBatch = async (batchId: number) => {
	if (!confirm("Apakah Anda yakin ingin menghapus batch ini?")) {
		return;
	}

	const res = await fetch(`/api/ingredient-batches/${batchId}`, {
		method: "DELETE",
		headers: {
			Accept: "application/json",
			"X-CSRF-TOKEN":
				(document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)
					?.content || "",
		},
	});

	if (res.ok) {
		await fetchBatches();
	}
};

const formatDate = (val: string) => {
	if (!val) {
		return "—";
	}

	return new Date(val).toLocaleDateString("id-ID");
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
				:form-fields="[]"
				:badge-map="badgeMap"
				@form-opened="handleFormOpened"
				auditable-type="Ingredient"
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

				<template #form="{ mode, submitting, cancel, refresh }">
					<div class="rounded-xl border bg-card p-6 shadow-sm animate-in fade-in duration-300">
						<div class="flex items-center gap-3 border-b pb-4 mb-6">
							<Button variant="ghost" size="icon-sm" @click="cancel">
								<ArrowLeft class="h-4 w-4" />
							</Button>
							<div>
								<h2 class="text-lg font-bold text-foreground">
									{{ mode === 'edit' ? 'Ubah Bahan Baku' : 'Tambah Bahan Baku Baru' }}
								</h2>
								<p class="text-xs text-muted-foreground">Isi detail data bahan baku untuk operasional restoran.</p>
							</div>
						</div>

						<form @submit.prevent="submitCustomForm(cancel, refresh)" class="grid gap-6 w-full">
							<div class="grid gap-4 md:grid-cols-2">
								<div class="flex flex-col gap-1.5">
									<Label for="form-name">Nama Bahan <span class="text-destructive">*</span></Label>
									<Input id="form-name" v-model="formName" required placeholder="Contoh: Tepung Terigu Segitiga Biru" />
									<p v-if="formErrors.name" class="text-xs text-destructive">{{ formErrors.name[0] }}</p>
								</div>
								<div class="flex flex-col gap-1.5">
									<Label for="form-sku">SKU / Barcode</Label>
									<Input id="form-sku" v-model="formSku" placeholder="Contoh: ING-FLOUR-01" />
									<p v-if="formErrors.sku" class="text-xs text-destructive">{{ formErrors.sku[0] }}</p>
								</div>
							</div>

							<div class="grid gap-4 md:grid-cols-2">
								<div class="flex flex-col gap-1.5">
									<Label for="form-category">Kategori <span class="text-destructive">*</span></Label>
									<select
										id="form-category"
										v-model="formCategoryId"
										required
										class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-sm focus:outline-none focus:ring-1 focus:ring-brand-500"
									>
										<option value="" disabled>Pilih Kategori...</option>
										<option v-for="cat in ingredientCategories" :key="cat.id" :value="cat.id">
											{{ cat.name }}
										</option>
									</select>
									<p v-if="formErrors.ingredient_category_id" class="text-xs text-destructive">{{ formErrors.ingredient_category_id[0] }}</p>
								</div>
								<div class="flex flex-col gap-1.5">
									<Label for="form-price">Harga Beli Rata-Rata (Rp) <span class="text-destructive">*</span></Label>
									<Input id="form-price" type="number" min="0" v-model.number="formPrice" required />
									<p v-if="formErrors.price" class="text-xs text-destructive">{{ formErrors.price[0] }}</p>
								</div>
							</div>

							<div class="grid gap-4 md:grid-cols-3">
								<div class="flex flex-col gap-1.5">
									<Label for="form-unit">Satuan Utama <span class="text-destructive">*</span></Label>
									<Input id="form-unit" v-model="formUnit" required placeholder="Contoh: kg, l, pack" />
									<p v-if="formErrors.unit" class="text-xs text-destructive">{{ formErrors.unit[0] }}</p>
								</div>
								<div class="flex flex-col gap-1.5">
									<Label for="form-small-unit">Satuan Kecil (Dapur) <span class="text-destructive">*</span></Label>
									<Input id="form-small-unit" v-model="formSmallUnit" required placeholder="Contoh: gram, ml, pcs" />
									<p v-if="formErrors.small_unit" class="text-xs text-destructive">{{ formErrors.small_unit[0] }}</p>
								</div>
								<div class="flex flex-col gap-1.5">
									<Label for="form-conversion">Faktor Konversi <span class="text-destructive">*</span></Label>
									<Input id="form-conversion" type="number" step="0.01" min="0.01" v-model.number="formConversionFactor" required />
									<p v-if="formErrors.conversion_factor" class="text-xs text-destructive">{{ formErrors.conversion_factor[0] }}</p>
								</div>
								<div v-if="formUnit && formSmallUnit && formConversionFactor" class="md:col-span-3 -mt-2">
									<p class="text-xs text-brand-600 dark:text-brand-400 bg-brand-50 dark:bg-brand-950/30 px-3 py-1.5 rounded border border-brand-100 dark:border-brand-900 inline-flex items-center gap-1.5">
										<span class="w-1.5 h-1.5 rounded-full bg-brand-500 animate-pulse"></span>
										Kalkulasi Otomatis Terdeteksi: 1 {{ formUnit }} = {{ formConversionFactor }} {{ formSmallUnit }}
									</p>
								</div>
							</div>

							<div class="grid gap-4 md:grid-cols-2">
								<div class="flex flex-col gap-1.5">
									<Label for="form-min-qty">Min Stok Utama (Alarm Peringatan) <span class="text-destructive">*</span></Label>
									<Input id="form-min-qty" type="number" step="0.01" min="0" v-model.number="formMinQty" required />
									<p v-if="formErrors.min_qty" class="text-xs text-destructive">{{ formErrors.min_qty[0] }}</p>
								</div>
								<div class="flex flex-col gap-1.5">
									<Label for="form-temp">Suhu Penyimpanan (°C / Teks)</Label>
									<Input id="form-temp" v-model="formStorageTemperature" placeholder="Contoh: Dingin (4°C)" />
									<p v-if="formErrors.storage_temperature" class="text-xs text-destructive">{{ formErrors.storage_temperature[0] }}</p>
								</div>
							</div>

							<div class="flex flex-col gap-1.5">
								<Label for="form-description">Keterangan / Deskripsi</Label>
								<textarea
									id="form-description"
									v-model="formDescription"
									rows="3"
									placeholder="Keterangan tambahan mengenai bahan baku ini..."
									class="flex w-full rounded-lg border border-input bg-background px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 focus-visible:ring-offset-2"
								/>
								<p v-if="formErrors.description" class="text-xs text-destructive">{{ formErrors.description[0] }}</p>
							</div>

							<div class="flex justify-end gap-3 border-t pt-4">
								<Button type="button" variant="outline" @click="cancel" :disabled="submitting">Batal</Button>
								<Button type="submit" :disabled="submitting" class="bg-brand-600 hover:bg-brand-700 text-white">
									<Loader2 class="mr-2 h-4 w-4 animate-spin" v-if="submitting" />
									<Check class="mr-2 h-4 w-4" v-else />
									{{ mode === 'edit' ? 'Perbarui Data' : 'Simpan Bahan' }}
								</Button>
							</div>
						</form>
					</div>
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
							<Label for="batch_price">Total Harga Beli (Rp)</Label>
							<Input id="batch_price" type="number" step="0.01" v-model.number="newBatch.price" required />
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
								<th class="p-4">Harga Beli</th>
								<th class="p-4">Kedaluwarsa</th>
								<th class="p-4">Dibuat Oleh</th>
								<th class="p-4 text-right">Aksi</th>
							</tr>
						</thead>
						<tbody class="divide-y">
							<tr v-if="batches.length === 0">
								<td colspan="5" class="p-6 text-center text-muted-foreground italic">Belum ada batch untuk bahan ini. Silakan tambahkan di formulir kiri.</td>
							</tr>
							<tr 
								v-for="batch in batches" 
								:key="batch.id" 
								class="hover:bg-muted/10 transition-colors"
								:class="batch.expiration_date && isExpired(batch.expiration_date) ? 'bg-destructive/5 text-destructive/80 opacity-90' : ''"
							>
								<td class="p-4 font-medium">
									<div class="flex items-center gap-1.5">
										<span>{{ batch.batch_number }}</span>
										<Badge v-if="batch.expiration_date && isExpired(batch.expiration_date)" variant="destructive" class="text-[10px] px-1.5 py-0">
											Kedaluwarsa
										</Badge>
									</div>
								</td>
								<td class="p-4">{{ Number(batch.qty) }} {{ selectedIngredient?.unit }}</td>
								<td class="p-4">Rp {{ Math.round(batch.price || 0).toLocaleString('id-ID') }}</td>
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
