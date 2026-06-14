<script setup lang="ts">
import {
	ArrowLeft,
	Check,
	ChefHat,
	ListPlus,
	Loader2,
	Plus,
	UtensilsCrossed,
	X,
} from "lucide-vue-next";
import { onMounted, ref } from "vue";
import type { Column, FormField } from "@/components/CRUDTable.vue";
import CRUDTable from "@/components/CRUDTable.vue";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";

defineOptions({
	layout: {
		breadcrumbs: [
			{ title: "Dashboard", href: "/dashboard" },
			{ title: "Katalog Produk", href: "#" },
			{ title: "Menu & Varian", href: route("catalog.menu") },
		],
	},
});

const categories = ref<{ id: number; name: string }[]>([]);
const inventoryItems = ref<{ id: number; name: string; qty: any }[]>([]);

const refreshCategories = async () => {
	try {
		const res = await fetch("/api/categories?all=true", {
			headers: { Accept: "application/json" },
		});

		if (res.ok) {
			const data = await res.json();
			categories.value = data;
			const catField = menuItemFields.find((f) => f.key === "category_id");

			if (catField) {
				catField.options = data.map((c: any) => ({
					value: c.id,
					label: c.name,
				}));
			}
		}
	} catch (e) {
		console.error(e);
	}
};

const refreshInventoryItems = async () => {
	try {
		const res = await fetch("/api/inventory-items?all=true", {
			headers: { Accept: "application/json" },
		});

		if (res.ok) {
			const data = await res.json();
			inventoryItems.value = data;
			const invField = menuItemFields.find(
				(f) => f.key === "inventory_item_id",
			);

			if (invField) {
				invField.options = [
					{
						value: "",
						label: "— Tidak Terhubung (Bukan barang jadi) —",
					},
					...data.map((i: any) => ({
						value: i.id,
						label: `${i.name} (Stok: ${Number(i.qty)})`,
					})),
				];
			}
		}
	} catch (e) {
		console.error(e);
	}
};

onMounted(() => {
	refreshCategories();
	refreshInventoryItems();
	fetchAllIngredients();
});

// ─── Menu Customizations (Variants & Add-ons) Logic ──────────────────
const selectedMenuItem = ref<any | null>(null);
const allAddOns = ref<any[]>([]);
const selectedAddOnIds = ref<number[]>([]);
const menuOptions = ref<
	{
		name: string;
		is_required: boolean;
		type: "single" | "multiple";
		values: { name: string; additional_price: number }[];
	}[]
>([]);
const savingCustomizations = ref(false);

const manageMenuCustomizations = async (item: any) => {
	selectedMenuItem.value = item;

	try {
		const menuRes = await fetch(`/api/menu-items/${item.id}`, {
			headers: { Accept: "application/json" },
		});

		if (menuRes.ok) {
			const data = await menuRes.json();
			selectedAddOnIds.value = (data.add_ons || []).map((ao: any) => ao.id);
			menuOptions.value = (data.options || []).map((opt: any) => ({
				name: opt.name,
				is_required: Boolean(opt.is_required),
				type: opt.type,
				values: (opt.values || []).map((v: any) => ({
					name: v.name,
					additional_price: Number(v.additional_price),
				})),
			}));
		}

		const addOnRes = await fetch("/api/add-ons?all=true", {
			headers: { Accept: "application/json" },
		});

		if (addOnRes.ok) {
			allAddOns.value = await addOnRes.json();
		}
	} catch (e) {
		console.error(e);
	}
};

const toggleAddOn = (id: number) => {
	const idx = selectedAddOnIds.value.indexOf(id);

	if (idx > -1) {
		selectedAddOnIds.value.splice(idx, 1);
	} else {
		selectedAddOnIds.value.push(id);
	}
};

const saveMenuCustomizations = async () => {
	if (!selectedMenuItem.value) {
		return;
	}

	savingCustomizations.value = true;

	try {
		const res = await fetch(`/api/menu-items/${selectedMenuItem.value.id}`, {
			method: "PATCH",
			headers: {
				"Content-Type": "application/json",
				Accept: "application/json",
				"X-CSRF-TOKEN":
					(document.querySelector('meta[name="csrf-token"]') as any)?.content ||
					"",
			},
			body: JSON.stringify({
				add_on_ids: selectedAddOnIds.value,
				options: menuOptions.value.filter((opt) => opt.name.trim() !== ""),
			}),
		});

		if (res.ok) {
			selectedMenuItem.value = null;
		}
	} catch (e) {
		console.error(e);
	} finally {
		savingCustomizations.value = false;
	}
};

// ─── Menu Recipe Logic ────────────────────────────────────────────────
const activePanel = ref<'customizations' | 'recipe'>('customizations');
const allIngredients = ref<any[]>([]);
const recipeItemsList = ref<{ ingredient_id: number | ''; qty: number }[]>([]);
const savingRecipe = ref(false);

const fetchAllIngredients = async () => {
	try {
		const res = await fetch("/api/ingredients?all=true", {
			headers: { Accept: "application/json" },
		});
		if (res.ok) {
			allIngredients.value = await res.json();
		}
	} catch (e) {
		console.error(e);
	}
};

const manageMenuRecipe = async (item: any) => {
	selectedMenuItem.value = item;
	activePanel.value = "recipe";
	recipeItemsList.value = [];

	try {
		const menuRes = await fetch(`/api/menu-items/${item.id}`, {
			headers: { Accept: "application/json" },
		});

		if (menuRes.ok) {
			const data = await menuRes.json();
			recipeItemsList.value = (data.recipe_items || []).map((ri: any) => ({
				ingredient_id: ri.ingredient_id,
				qty: Number(ri.qty),
			}));
		}
	} catch (e) {
		console.error(e);
	}
};

const addRecipeRow = () => {
	recipeItemsList.value.push({
		ingredient_id: "",
		qty: 1,
	});
};

const removeRecipeRow = (index: number) => {
	recipeItemsList.value.splice(index, 1);
};

const getSmallUnit = (ingredientId: number | '') => {
	if (ingredientId === '') return 'unit';
	const ing = allIngredients.value.find((i) => i.id === ingredientId);
	return ing ? ing.small_unit : 'unit';
};

const saveMenuRecipe = async () => {
	if (!selectedMenuItem.value) {
		return;
	}

	savingRecipe.value = true;

	try {
		const recipePayload = recipeItemsList.value
			.filter((ri) => ri.ingredient_id !== "" && ri.qty > 0)
			.map((ri) => ({
				ingredient_id: Number(ri.ingredient_id),
				qty: Number(ri.qty),
			}));

		const res = await fetch(`/api/menu-items/${selectedMenuItem.value.id}`, {
			method: "PATCH",
			headers: {
				"Content-Type": "application/json",
				Accept: "application/json",
				"X-CSRF-TOKEN":
					(document.querySelector('meta[name="csrf-token"]') as any)?.content ||
					"",
			},
			body: JSON.stringify({
				recipe: recipePayload,
			}),
		});

		if (res.ok) {
			selectedMenuItem.value = null;
		}
	} catch (e) {
		console.error(e);
	} finally {
		savingRecipe.value = false;
	}
};

// ─── Table Config ───────────────────────────────────────────────────────────────
type MenuItem = {
	id: number;
	name: string;
	category: { id: number; name: string } | null;
	category_id: number | null;
	price: string;
	status: string;
	calories: number | null;
	preparation_time: number | null;
};
const menuItemColumns: Column<MenuItem>[] = [
	{ key: "name", label: "Nama" },
	{
		key: "category_id",
		label: "Kategori",
		render: (val, row) => (row.category ? row.category.name : "—"),
	},
	{
		key: "price",
		label: "Harga",
		render: (val) => `Rp ${Number(val).toLocaleString("id-ID")}`,
	},
	{ key: "status", label: "Status" },
	{ key: "calories", label: "Kalori (kcal)" },
	{ key: "preparation_time", label: "Waktu Siap (mnt)" },
];

const menuItemFields: FormField[] = [
	{
		key: "name",
		label: "Nama Menu",
		type: "text",
		placeholder: "Contoh: Nasi Goreng Spesial",
		required: true,
	},
	{ key: "description", label: "Deskripsi", type: "textarea" },
	{ key: "price", label: "Harga (Rp)", type: "number", required: true },
	{
		key: "category_id",
		label: "Kategori",
		type: "select",
		required: true,
		options: [],
	},
	{
		key: "status",
		label: "Status",
		type: "select",
		required: true,
		options: [
			{ value: "available", label: "Tersedia" },
			{ value: "sold_out", label: "Stok Habis" },
			{ value: "draft", label: "Draft" },
		],
	},
	{ key: "image_url", label: "Gambar Menu", type: "image" },
	{ key: "calories", label: "Kalori (kcal)", type: "number", advanced: true },
	{
		key: "preparation_time",
		label: "Waktu Persiapan (menit)",
		type: "number",
		advanced: true,
	},
	{ key: "allergens", label: "Alergen", type: "tags", advanced: true },
	{ key: "tags", label: "Tag Menu", type: "tags", advanced: true },
	{
		key: "inventory_item_id",
		label: "Hubungkan ke Barang Inventoris (Potong stok otomatis)",
		type: "select",
		options: [],
		advanced: true,
	},
];

const menuItemBadge = {
	available: "success",
	sold_out: "danger",
	draft: "warning",
};
</script>

<template>
    <div class="p-6">
        <div v-if="!selectedMenuItem" class="animate-in duration-200 fade-in-0">
            <CRUDTable
                resource-name="Menu"
                api-url="/api/menu-items"
                :columns="menuItemColumns"
                :form-fields="menuItemFields"
                :badge-map="menuItemBadge"
                auditable-type="MenuItem"
            >
                <template #actions="{ row }">
                    <div class="flex items-center gap-1">
                        <Button
                            size="icon-sm"
                            variant="ghost"
                            class="text-brand-600 hover:bg-brand-50 hover:text-brand-700"
                            @click="
                                selectedMenuItem = row;
                                activePanel = 'customizations';
                                manageMenuCustomizations(row);
                            "
                            title="Kelola Varian & Topping"
                        >
                            <ListPlus class="h-4.5 w-4.5" />
                        </Button>
                        <Button
                            size="icon-sm"
                            variant="ghost"
                            class="text-amber-600 hover:bg-amber-50 hover:text-amber-700"
                            @click="manageMenuRecipe(row)"
                            title="Kelola Resep Bahan Baku"
                        >
                            <ChefHat class="h-4.5 w-4.5" />
                        </Button>
                    </div>
                </template>
            </CRUDTable>
        </div>

        <div
            v-else
            class="animate-in rounded-xl border bg-card p-6 shadow-sm duration-300 fade-in"
        >
            <div class="mb-6 flex items-center gap-3 border-b pb-4">
                <Button
                    variant="ghost"
                    size="icon-sm"
                    @click="selectedMenuItem = null"
                    ><ArrowLeft class="h-4 w-4"
                /></Button>
                <div>
                    <h2 class="text-lg font-bold text-foreground">
                        <span v-if="activePanel === 'customizations'">
                            Kustomisasi Menu: {{ selectedMenuItem.name }}
                        </span>
                        <span v-else>
                            Resep Bahan Baku: {{ selectedMenuItem.name }}
                        </span>
                    </h2>
                    <p class="text-xs text-muted-foreground">
                        <span v-if="activePanel === 'customizations'">
                            Kelola extra topping dan varian.
                        </span>
                        <span v-else>
                            Kelola resep bahan baku untuk menu ini (pengurangan stok otomatis saat terjual).
                        </span>
                    </p>
                </div>
            </div>

            <!-- Customizations Panel -->
            <div v-if="activePanel === 'customizations'" class="grid gap-8 lg:grid-cols-2 animate-in duration-200 fade-in">
                <div class="flex flex-col gap-4">
                    <div class="flex items-center justify-between">
                        <h3
                            class="flex items-center gap-2 text-sm font-semibold text-foreground/80"
                        >
                            <UtensilsCrossed
                                class="h-4 w-4 text-brand-600"
                            />Varian & Pilihan
                        </h3>
                        <Button
                            size="sm"
                            variant="outline"
                            class="h-8 gap-1 text-xs"
                            @click="
                                menuOptions.push({
                                    name: '',
                                    is_required: false,
                                    type: 'single',
                                    values: [{ name: '', additional_price: 0 }],
                                })
                            "
                            ><Plus class="h-3 w-3" />Tambah Opsi</Button
                        >
                    </div>
                    <div
                        v-for="(opt, optIdx) in menuOptions"
                        :key="optIdx"
                        class="group relative rounded-xl border bg-background/50 p-4 shadow-sm"
                    >
                        <Button
                            variant="ghost"
                            size="icon-xs"
                            class="absolute top-2 right-2 text-muted-foreground opacity-0 transition-opacity group-hover:opacity-100 hover:text-destructive"
                            @click="menuOptions.splice(optIdx, 1)"
                            ><X class="h-3.5 w-3.5"
                        /></Button>
                        <div class="mb-4 grid gap-4 sm:grid-cols-2">
                            <div class="space-y-1.5">
                                <Label
                                    class="text-[11px] font-bold tracking-wider text-muted-foreground uppercase"
                                    >Nama Opsi</Label
                                ><Input
                                    v-model="opt.name"
                                    placeholder="Misal: Level Pedas"
                                    class="h-9"
                                />
                            </div>
                            <div class="space-y-1.5">
                                <Label
                                    class="text-[11px] font-bold tracking-wider text-muted-foreground uppercase"
                                    >Tipe Pilihan</Label
                                ><select
                                    v-model="opt.type"
                                    class="flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm shadow-sm transition-colors focus:ring-1 focus:ring-ring focus:outline-none"
                                >
                                    <option value="single">
                                        Pilih Satu (Radio)
                                    </option>
                                    <option value="multiple">
                                        Pilih Banyak (Checkbox)
                                    </option>
                                </select>
                            </div>
                            <div class="flex items-center gap-2 sm:col-span-2">
                                <input
                                    type="checkbox"
                                    v-model="opt.is_required"
                                    :id="`req-${optIdx}`"
                                    class="h-4 w-4 rounded border-gray-300 text-brand-600 focus:ring-brand-600"
                                /><Label
                                    :for="`req-${optIdx}`"
                                    class="cursor-pointer text-xs select-none"
                                    >Wajib diisi oleh pelanggan</Label
                                >
                            </div>
                        </div>
                        <div class="mt-2 space-y-2 border-t pt-4">
                            <div class="mb-2 flex items-center justify-between">
                                <Label
                                    class="text-[11px] font-bold tracking-wider text-muted-foreground uppercase"
                                    >Pilihan Nilai (Values)</Label
                                ><Button
                                    variant="ghost"
                                    size="xs"
                                    class="h-6 text-[10px] text-brand-600 hover:bg-brand-50"
                                    @click="
                                        opt.values.push({
                                            name: '',
                                            additional_price: 0,
                                        })
                                    "
                                    >+ Tambah Nilai</Button
                                >
                            </div>
                            <div class="space-y-2">
                                <div
                                    v-for="(val, valIdx) in opt.values"
                                    :key="valIdx"
                                    class="flex items-center gap-2"
                                >
                                    <Input
                                        v-model="val.name"
                                        placeholder="Nama nilai"
                                        class="h-8 flex-1 text-xs"
                                    />
                                    <div class="relative w-32">
                                        <span
                                            class="absolute top-1/2 left-2 -translate-y-1/2 text-[10px] text-muted-foreground"
                                            >Rp</span
                                        ><Input
                                            v-model="val.additional_price"
                                            type="number"
                                            class="h-8 pl-7 text-xs"
                                        />
                                    </div>
                                    <Button
                                        v-if="opt.values.length > 1"
                                        variant="ghost"
                                        size="icon-xs"
                                        class="h-8 w-8 text-muted-foreground hover:text-destructive"
                                        @click="opt.values.splice(valIdx, 1)"
                                        ><X class="h-3.5 w-3.5"
                                    /></Button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div
                    class="flex flex-col gap-4 border-t pt-6 lg:border-t-0 lg:border-l lg:pt-0 lg:pl-8"
                >
                    <h3
                        class="flex items-center gap-2 text-sm font-semibold text-foreground/80"
                    >
                        <Plus class="h-4 w-4 text-brand-600" />Extra Toppings
                        (Add-ons)
                    </h3>
                    <div
                        class="mt-2 grid max-h-[500px] grid-cols-1 gap-2 overflow-y-auto pr-2 sm:grid-cols-2"
                    >
                        <div
                            v-for="ao in allAddOns"
                            :key="ao.id"
                            @click="toggleAddOn(ao.id)"
                            class="flex cursor-pointer items-center justify-between rounded-xl border p-3 select-none"
                            :class="
                                selectedAddOnIds.includes(ao.id)
                                    ? 'border-brand-500 bg-brand-50/30 ring-1 ring-brand-500'
                                    : 'hover:bg-muted/30'
                            "
                        >
                            <div class="min-w-0">
                                <p class="truncate text-xs font-bold">
                                    {{ ao.name }}
                                </p>
                                <p class="text-[10px] text-muted-foreground">
                                    + Rp
                                    {{
                                        Number(ao.price).toLocaleString('id-ID')
                                    }}
                                </p>
                            </div>
                            <div
                                class="flex h-5 w-5 items-center justify-center rounded-full border"
                                :class="
                                    selectedAddOnIds.includes(ao.id)
                                        ? 'border-brand-600 bg-brand-600 text-white'
                                        : 'bg-white'
                                "
                            >
                                <Check
                                    v-if="selectedAddOnIds.includes(ao.id)"
                                    class="h-3 w-3"
                                />
                            </div>
                        </div>
                    </div>
                </div>
                <div class="mt-8 flex items-center justify-end gap-3 border-t pt-4 sm:col-span-2">
                    <Button variant="outline" @click="selectedMenuItem = null"
                        >Batal</Button
                    >
                    <Button
                        class="flex items-center gap-1.5 bg-brand-600 font-semibold text-white hover:bg-brand-700"
                        :disabled="savingCustomizations"
                        @click="saveMenuCustomizations"
                    >
                        <Loader2
                            v-if="savingCustomizations"
                            class="h-4 w-4 animate-spin"
                        />
                        <Check v-else class="h-4 w-4" />Simpan Kustomisasi
                    </Button>
                </div>
            </div>

            <!-- Recipe Panel -->
            <div v-else-if="activePanel === 'recipe'" class="space-y-6 animate-in duration-200 fade-in">
                <div class="flex items-center justify-between border-b pb-4">
                    <h3 class="flex items-center gap-2 text-sm font-semibold text-foreground/80">
                        <ChefHat class="h-4.5 w-4.5 text-amber-500" />
                        Bahan Baku Terdaftar
                    </h3>
                    <Button
                        size="sm"
                        variant="outline"
                        class="h-8 gap-1.5 text-xs"
                        @click="addRecipeRow"
                    >
                        <Plus class="h-3.5 w-3.5" />
                        Tambah Bahan Baku
                    </Button>
                </div>

                <div class="space-y-4">
                    <div v-if="recipeItemsList.length === 0" class="flex flex-col items-center justify-center py-12 text-center rounded-xl border-2 border-dashed border-muted/50 bg-background/30">
                        <ChefHat class="h-10 w-10 text-muted-foreground/40 mb-3" />
                        <p class="text-sm font-semibold text-muted-foreground">Belum ada bahan baku yang ditambahkan</p>
                        <p class="text-xs text-muted-foreground/70 mt-1 max-w-sm">Tambahkan bahan baku yang digunakan dalam resep menu ini untuk melacak dan mengurangi stok otomatis saat menu terjual.</p>
                        <Button
                            size="sm"
                            variant="secondary"
                            class="mt-4 h-8 gap-1 text-xs"
                            @click="addRecipeRow"
                        >
                            <Plus class="h-3 w-3" />
                            Tambah Pertama
                        </Button>
                    </div>

                    <div v-else class="rounded-xl border bg-background/30 overflow-hidden">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="border-b bg-muted/30">
                                    <th class="px-4 py-3 text-xs font-bold uppercase tracking-wider text-muted-foreground">Bahan Baku</th>
                                    <th class="px-4 py-3 text-xs font-bold uppercase tracking-wider text-muted-foreground w-40">Jumlah</th>
                                    <th class="px-4 py-3 text-xs font-bold uppercase tracking-wider text-muted-foreground w-24">Satuan</th>
                                    <th class="px-4 py-3 text-xs font-bold uppercase tracking-wider text-muted-foreground w-16 text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y">
                                <tr v-for="(ri, idx) in recipeItemsList" :key="idx" class="hover:bg-muted/10">
                                    <td class="p-3">
                                        <select
                                            v-model="ri.ingredient_id"
                                            class="flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-xs shadow-sm transition-colors focus:ring-1 focus:ring-ring focus:outline-none"
                                        >
                                            <option value="" disabled>— Pilih Bahan Baku —</option>
                                            <option v-for="ing in allIngredients" :key="ing.id" :value="ing.id">
                                                {{ ing.name }} (Stok: {{ Number(ing.qty) }} {{ ing.unit }})
                                            </option>
                                        </select>
                                    </td>
                                    <td class="p-3">
                                        <Input
                                            v-model="ri.qty"
                                            type="number"
                                            min="0.01"
                                            step="any"
                                            class="h-9 text-xs"
                                            placeholder="0.00"
                                        />
                                    </td>
                                    <td class="p-3 text-xs font-medium text-muted-foreground px-4">
                                        {{ getSmallUnit(ri.ingredient_id) }}
                                    </td>
                                    <td class="p-3 text-center">
                                        <Button
                                            variant="ghost"
                                            size="icon-xs"
                                            class="h-8 w-8 text-muted-foreground hover:text-destructive hover:bg-destructive/10"
                                            @click="removeRecipeRow(idx)"
                                        >
                                            <X class="h-4 w-4" />
                                        </Button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="mt-8 flex items-center justify-end gap-3 border-t pt-4">
                    <Button variant="outline" @click="selectedMenuItem = null">Batal</Button>
                    <Button
                        class="flex items-center gap-1.5 bg-brand-600 font-semibold text-white hover:bg-brand-700"
                        :disabled="savingRecipe"
                        @click="saveMenuRecipe"
                    >
                        <Loader2
                            v-if="savingRecipe"
                            class="h-4 w-4 animate-spin"
                        />
                        <Check v-else class="h-4 w-4" />Simpan Resep
                    </Button>
                </div>
            </div>
        </div>
    </div>
</template>
