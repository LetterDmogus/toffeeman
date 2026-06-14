<script setup lang="ts">
import { ArrowLeft, ArrowRightLeft, Check, Loader2 } from "lucide-vue-next";
import { onMounted, ref, watch } from "vue";
import type { Column } from "@/components/CRUDTable.vue";
import CRUDTable from "@/components/CRUDTable.vue";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";

defineOptions({
	layout: {
		breadcrumbs: [
			{ title: "Dashboard", href: "/dashboard" },
			{ title: "Operasional", href: "#" },
			{ title: "Bahan Baku", href: route("ops.ingredients") },
			{ title: "Mutasi Bahan", href: "#" },
		],
	},
});

const columns: Column<any>[] = [
	{
		key: "created_at",
		label: "Tanggal & Waktu",
		render: (val) => val ? new Date(val as string).toLocaleDateString("id-ID", {
			year: "numeric",
			month: "short",
			day: "numeric",
			hour: "2-digit",
			minute: "2-digit"
		}) : "—",
	},
	{
		key: "ingredient_id",
		label: "Bahan Baku",
		render: (val, row) => row.ingredient?.name || "—",
	},
	{
		key: "ingredient_batch_id",
		label: "Nomor Batch",
		render: (val, row) => row.batch?.batch_number || "—",
	},
	{
		key: "type",
		label: "Tipe",
		render: (val) => val === "in" ? "Masuk" : "Keluar",
	},
	{
		key: "qty",
		label: "Jumlah Mutasi",
		render: (val, row) => {
			const rawQty = Number(val);
			const factor = Number(row.ingredient?.conversion_factor || 1);
			const mainQty = rawQty / factor;
			const unit = row.ingredient?.unit || "";
			const smallUnit = row.ingredient?.small_unit || "";
			
			const sign = row.type === "in" ? "+" : "-";

			if (factor > 1 && smallUnit && unit !== smallUnit) {
				return `${sign}${mainQty} ${unit} (${rawQty} ${smallUnit})`;
			}
			return `${sign}${mainQty} ${unit}`;
		},
	},
	{
		key: "created_by",
		label: "Petugas",
		render: (val, row) => row.creator?.name || "Sistem",
	},
	{
		key: "notes",
		label: "Keterangan",
		render: (val) => (val as string) || "—",
	},
];

const badgeMap = {
	in: "success",
	out: "danger",
};

// Form States
const ingredients = ref<any[]>([]);
const batches = ref<any[]>([]);

const selectedIngredientId = ref("");
const selectedBatchId = ref("");
const mutationType = ref("in");
const mutationQty = ref(1);
const mutationNotes = ref("");
const formErrors = ref<any>({});
const activeIngredientInfo = ref<any>(null);

onMounted(async () => {
	const res = await fetch("/api/ingredients?all=true", {
		headers: { Accept: "application/json" },
	});
	if (res.ok) {
		ingredients.value = await res.json();
	}
});

// Fetch batches when ingredient is selected
watch(selectedIngredientId, async (newId) => {
	selectedBatchId.value = "";
	batches.value = [];
	if (newId) {
		activeIngredientInfo.value = ingredients.value.find((item) => item.id === Number(newId)) || null;
		
		const res = await fetch(`/api/ingredient-batches?ingredient_id=${newId}`, {
			headers: { Accept: "application/json" },
		});
		if (res.ok) {
			const data = await res.json();
			batches.value = data.data || [];
		}
	} else {
		activeIngredientInfo.value = null;
	}
});

const submitMutationForm = async (cancel: () => void, refresh: () => void) => {
	formErrors.value = {};
	const payload = {
		ingredient_id: selectedIngredientId.value,
		ingredient_batch_id: selectedBatchId.value,
		type: mutationType.value,
		qty: mutationQty.value,
		notes: mutationNotes.value,
	};

	try {
		const res = await fetch("/api/ingredient-mutations", {
			method: "POST",
			headers: {
				"Content-Type": "application/json",
				Accept: "application/json",
				"X-Requested-With": "XMLHttpRequest",
				"X-CSRF-TOKEN": (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || "",
			},
			body: JSON.stringify(payload),
		});

		if (res.ok) {
			selectedIngredientId.value = "";
			selectedBatchId.value = "";
			mutationType.value = "in";
			mutationQty.value = 1;
			mutationNotes.value = "";
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
			resource-name="Mutasi Bahan"
			api-url="/api/ingredient-mutations"
			:columns="columns"
			:form-fields="[]"
			disable-edit
			disable-delete
			:badge-map="badgeMap"
			:default-visible-columns="['created_at', 'ingredient_id', 'ingredient_batch_id', 'type', 'qty', 'created_by', 'notes']"
			auditable-type="IngredientMutation"
		>
			<template #form="{ mode, submitting, cancel, refresh }">
				<div class="rounded-xl border bg-card p-6 shadow-sm animate-in fade-in duration-300">
					<div class="flex items-center gap-3 border-b pb-4 mb-6">
						<Button variant="ghost" size="icon-sm" @click="cancel">
							<ArrowLeft class="h-4 w-4" />
						</Button>
						<div>
							<h2 class="text-lg font-bold text-foreground">Catat Mutasi Bahan Manual</h2>
							<p class="text-xs text-muted-foreground">Sesuaikan stok fisik bahan baku untuk operasional restoran secara manual.</p>
						</div>
					</div>

					<form @submit.prevent="submitMutationForm(cancel, refresh)" class="grid gap-6 w-full">
						<div class="grid gap-4 md:grid-cols-2">
							<div class="flex flex-col gap-1.5">
								<Label for="form-ingredient">Pilih Bahan Baku <span class="text-destructive">*</span></Label>
								<select
									id="form-ingredient"
									v-model="selectedIngredientId"
									required
									class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-sm focus:outline-none focus:ring-1 focus:ring-brand-500"
								>
									<option value="" disabled>Pilih Bahan Baku...</option>
									<option v-for="ing in ingredients" :key="ing.id" :value="ing.id">
										{{ ing.name }} ({{ ing.sku || 'No SKU' }})
									</option>
								</select>
								<p v-if="formErrors.ingredient_id" class="text-xs text-destructive">{{ formErrors.ingredient_id[0] }}</p>
							</div>

							<div class="flex flex-col gap-1.5">
								<Label for="form-batch">Pilih Batch <span class="text-destructive">*</span></Label>
								<select
									id="form-batch"
									v-model="selectedBatchId"
									required
									:disabled="!selectedIngredientId"
									class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-sm focus:outline-none focus:ring-1 focus:ring-brand-500 disabled:opacity-50"
								>
									<option value="" disabled>Pilih Batch...</option>
									<option v-for="b in batches" :key="b.id" :value="b.id">
										{{ b.batch_number }} (Stok: {{ b.qty }} {{ activeIngredientInfo?.unit }})
									</option>
								</select>
								<p v-if="formErrors.ingredient_batch_id" class="text-xs text-destructive">{{ formErrors.ingredient_batch_id[0] }}</p>
							</div>
						</div>

						<div class="grid gap-4 md:grid-cols-2">
							<div class="flex flex-col gap-1.5">
								<Label for="form-type">Tipe Mutasi <span class="text-destructive">*</span></Label>
								<select
									id="form-type"
									v-model="mutationType"
									required
									class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-sm focus:outline-none focus:ring-1 focus:ring-brand-500"
								>
									<option value="in">Masuk (Tambah Stok)</option>
									<option value="out">Keluar (Potong Stok)</option>
								</select>
								<p v-if="formErrors.type" class="text-xs text-destructive">{{ formErrors.type[0] }}</p>
							</div>

							<div class="flex flex-col gap-1.5">
								<Label for="form-qty">Jumlah Mutasi ({{ activeIngredientInfo?.unit || 'Unit' }}) <span class="text-destructive">*</span></Label>
								<Input id="form-qty" type="number" step="0.01" min="0.01" v-model.number="mutationQty" required />
								<p v-if="formErrors.qty" class="text-xs text-destructive">{{ formErrors.qty[0] }}</p>
							</div>
						</div>

						<div class="flex flex-col gap-1.5">
							<Label for="form-notes">Keterangan / Alasan Mutasi <span class="text-destructive">*</span></Label>
							<textarea
								id="form-notes"
								v-model="mutationNotes"
								required
								rows="3"
								placeholder="Contoh: Stok tumpah, penyesuaian opname bulanan, rusak, dll."
								class="flex w-full rounded-lg border border-input bg-background px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-500"
							/>
							<p v-if="formErrors.notes" class="text-xs text-destructive">{{ formErrors.notes[0] }}</p>
						</div>

						<div class="flex justify-end gap-3 border-t pt-4">
							<Button type="button" variant="outline" @click="cancel" :disabled="submitting">Batal</Button>
							<Button type="submit" :disabled="submitting" class="bg-brand-600 hover:bg-brand-700 text-white">
								<Loader2 class="mr-2 h-4 w-4 animate-spin" v-if="submitting" />
								<Check class="mr-2 h-4 w-4" v-else />
								Simpan Mutasi
							</Button>
						</div>
					</form>
				</div>
			</template>
		</CRUDTable>
	</div>
</template>
