<script setup lang="ts">
import {
	AlertCircle,
	ArrowLeft,
	ArrowUpRight,
	BookOpen,
	ChefHat,
	Clock,
	CookingPot,
	Flame,
	Search,
	UtensilsCrossed,
} from "lucide-vue-next";
import { computed, onMounted, ref } from "vue";
import { Link } from "@inertiajs/vue3";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";

defineOptions({
	layout: {
		breadcrumbs: [
			{ title: "Dashboard", href: "/dashboard" },
			{ title: "Katalog Produk", href: "#" },
			{ title: "Almanak Resep", href: route("catalog.recipe-book") },
		],
	},
});

const menuItems = ref<any[]>([]);
const categories = ref<any[]>([]);
const selectedCategoryId = ref<number | "all">("all");
const searchQuery = ref("");
const loading = ref(true);
const selectedMenuItem = ref<any | null>(null);

const fetchCategories = async () => {
	try {
		const res = await fetch("/api/categories?all=true", {
			headers: { Accept: "application/json" },
		});
		if (res.ok) {
			categories.value = await res.json();
		}
	} catch (e) {
		console.error(e);
	}
};

const fetchMenuItems = async () => {
	loading.value = true;
	try {
		const res = await fetch("/api/menu-items?per_page=100", {
			headers: { Accept: "application/json" },
		});
		if (res.ok) {
			const data = await res.json();
			menuItems.value = data.data || [];
		}
	} catch (e) {
		console.error(e);
	} finally {
		loading.value = false;
	}
};

onMounted(() => {
	fetchCategories();
	fetchMenuItems();
});

const filteredMenuItems = computed(() => {
	let items = menuItems.value;

	if (selectedCategoryId.value !== "all") {
		items = items.filter(
			(item) => item.category_id === selectedCategoryId.value,
		);
	}

	if (searchQuery.value.trim() !== "") {
		const query = searchQuery.value.toLowerCase();
		items = items.filter(
			(item) =>
				item.name.toLowerCase().includes(query) ||
				(item.category && item.category.name.toLowerCase().includes(query)) ||
				(item.recipe_items &&
					item.recipe_items.some((ri: any) =>
						ri.ingredient?.name.toLowerCase().includes(query),
					)),
		);
	}

	return items;
});
</script>

<template>
    <div class="p-6 space-y-6">
        <!-- Header Section -->
        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between border-b pb-6">
            <div>
                <h1 class="text-2xl font-bold tracking-tight text-foreground flex items-center gap-2">
                    <BookOpen class="h-6 w-6 text-brand-500" />
                    Almanak Resep & Bahan Baku
                </h1>
                <p class="text-xs text-muted-foreground mt-1">
                    Buku panduan resep lengkap untuk semua menu restoran Anda.
                </p>
            </div>
            <Button as-child class="bg-brand-600 hover:bg-brand-700 text-white font-semibold">
                <Link :href="route('catalog.menu')">
                    Kelola Resep & Menu
                    <ArrowUpRight class="ml-2 h-4 w-4" />
                </Link>
            </Button>
        </div>

        <!-- Filters Section (Hidden when a menu item is selected) -->
        <div v-if="!selectedMenuItem" class="flex flex-col gap-4 bg-card p-4 rounded-xl border animate-in duration-200 fade-in-0">
            <!-- Row 1: Kategori -->
            <div class="flex flex-wrap items-center gap-2">
                <Button
                    variant="ghost"
                    size="sm"
                    class="h-8 text-xs rounded-lg font-semibold"
                    :class="selectedCategoryId === 'all' ? 'bg-brand-500/10 text-brand-600 hover:bg-brand-500/20' : 'text-muted-foreground hover:bg-muted'"
                    @click="selectedCategoryId = 'all'"
                >
                    Semua Kategori
                </Button>
                <Button
                    v-for="cat in categories"
                    :key="cat.id"
                    variant="ghost"
                    size="sm"
                    class="h-8 text-xs rounded-lg font-semibold"
                    :class="selectedCategoryId === cat.id ? 'bg-brand-500/10 text-brand-600 hover:bg-brand-500/20' : 'text-muted-foreground hover:bg-muted'"
                    @click="selectedCategoryId = cat.id"
                >
                    {{ cat.name }}
                </Button>
            </div>

            <!-- Row 2: Search Bar -->
            <div class="relative w-full">
                <Search class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
                <Input
                    v-model="searchQuery"
                    placeholder="Cari menu makanan atau bahan resep..."
                    class="pl-9 h-9 text-xs w-full"
                />
            </div>
        </div>

        <!-- Grid Cards / Detail Section -->
        <div v-if="loading" class="flex flex-col items-center justify-center py-24">
            <div class="h-8 w-8 animate-spin rounded-full border-4 border-brand-500 border-t-transparent"></div>
            <p class="text-sm text-muted-foreground mt-4 font-medium">Memuat resep menu...</p>
        </div>

        <!-- Detailed Menu View -->
        <div v-else-if="selectedMenuItem" class="animate-in rounded-xl border bg-card p-6 shadow-sm duration-300 fade-in space-y-6">
            <!-- Back Button and Title -->
            <div class="flex items-center gap-3 border-b pb-4">
                <Button
                    variant="ghost"
                    size="icon-sm"
                    @click="selectedMenuItem = null"
                >
                    <ArrowLeft class="h-4 w-4" />
                </Button>
                <div>
                    <h2 class="text-lg font-bold text-foreground">
                        Detail Menu: {{ selectedMenuItem.name }}
                    </h2>
                    <p class="text-xs text-muted-foreground">
                        Informasi lengkap, komposisi resep, dan bahan baku menu ini.
                    </p>
                </div>
            </div>

            <!-- Details Content Grid -->
            <div class="grid gap-8 lg:grid-cols-5">
                <!-- Left: Info & Specs (2 cols) -->
                <div class="lg:col-span-2 space-y-6">
                    <div class="aspect-video w-full rounded-xl overflow-hidden border bg-muted flex items-center justify-center relative">
                        <img
                            v-if="selectedMenuItem.image_url"
                            :src="selectedMenuItem.image_url"
                            :alt="selectedMenuItem.name"
                            class="object-cover w-full h-full"
                        />
                        <div v-else class="flex flex-col items-center justify-center text-muted-foreground/40">
                            <UtensilsCrossed class="h-12 w-12 mb-2" />
                            <span class="text-xs">Tidak ada foto menu</span>
                        </div>
                    </div>

                    <div class="space-y-4">
                        <div class="flex items-center justify-between border-b pb-2 text-sm">
                            <span class="text-muted-foreground">Kategori</span>
                            <span class="font-semibold text-foreground">
                                {{ selectedMenuItem.category ? selectedMenuItem.category.name : '—' }}
                            </span>
                        </div>
                        <div class="flex items-center justify-between border-b pb-2 text-sm">
                            <span class="text-muted-foreground">Harga Jual</span>
                            <span class="font-bold text-foreground">
                                Rp {{ Number(selectedMenuItem.price).toLocaleString("id-ID") }}
                            </span>
                        </div>
                        <div class="flex items-center justify-between border-b pb-2 text-sm">
                            <span class="text-muted-foreground">Status Menu</span>
                            <span
                                class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium border"
                                :class="{
                                    'bg-emerald-500/10 text-emerald-600 border-emerald-500/20': selectedMenuItem.status === 'available',
                                    'bg-rose-500/10 text-rose-600 border-rose-500/20': selectedMenuItem.status === 'sold_out',
                                    'bg-amber-500/10 text-amber-600 border-amber-500/20': selectedMenuItem.status === 'draft',
                                }"
                            >
                                {{ selectedMenuItem.status === 'available' ? 'Tersedia' : selectedMenuItem.status === 'sold_out' ? 'Habis' : 'Draft' }}
                            </span>
                        </div>
                    </div>

                    <div v-if="selectedMenuItem.description" class="space-y-2">
                        <h4 class="text-xs font-bold text-muted-foreground uppercase tracking-wider">Deskripsi</h4>
                        <p class="text-xs text-foreground/80 leading-relaxed bg-background/50 p-3 rounded-lg border">
                            {{ selectedMenuItem.description }}
                        </p>
                    </div>

                    <!-- Prep Time & Calories -->
                    <div class="grid grid-cols-2 gap-4">
                        <div class="bg-background/40 border p-3 rounded-xl flex items-center gap-3">
                            <Clock class="h-5 w-5 text-brand-600 shrink-0" />
                            <div>
                                <p class="text-[10px] font-medium text-muted-foreground">Waktu Persiapan</p>
                                <p class="text-xs font-bold text-foreground">{{ selectedMenuItem.preparation_time || '—' }} menit</p>
                            </div>
                        </div>
                        <div class="bg-background/40 border p-3 rounded-xl flex items-center gap-3">
                            <Flame class="h-5 w-5 text-rose-500 shrink-0" />
                            <div>
                                <p class="text-[10px] font-medium text-muted-foreground">Kalori</p>
                                <p class="text-xs font-bold text-foreground">{{ selectedMenuItem.calories || '—' }} kcal</p>
                            </div>
                        </div>
                    </div>

                    <!-- Allergens & Tags -->
                    <div class="space-y-4">
                        <div v-if="selectedMenuItem.allergens && selectedMenuItem.allergens.length > 0" class="space-y-2">
                            <h4 class="text-xs font-bold text-muted-foreground uppercase tracking-wider">Alergen</h4>
                            <div class="flex flex-wrap gap-1.5">
                                <span
                                    v-for="a in selectedMenuItem.allergens"
                                    :key="a"
                                    class="inline-flex items-center rounded-md bg-rose-500/5 px-2 py-1 text-[10px] font-medium text-rose-600 border border-rose-500/10"
                                >
                                    {{ a }}
                                </span>
                            </div>
                        </div>
                        <div v-if="selectedMenuItem.tags && selectedMenuItem.tags.length > 0" class="space-y-2">
                            <h4 class="text-xs font-bold text-muted-foreground uppercase tracking-wider">Tag Menu</h4>
                            <div class="flex flex-wrap gap-1.5">
                                <span
                                    v-for="t in selectedMenuItem.tags"
                                    :key="t"
                                    class="inline-flex items-center rounded-md bg-brand-500/5 px-2 py-1 text-[10px] font-medium text-brand-600 border border-brand-500/10"
                                >
                                    #{{ t }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right: Recipe & Ingredients (3 cols) -->
                <div class="lg:col-span-3 space-y-6 border-t pt-6 lg:border-t-0 lg:border-l lg:pt-0 lg:pl-8">
                    <div class="flex items-center justify-between border-b pb-3">
                        <h3 class="flex items-center gap-2 text-sm font-semibold text-foreground/80">
                            <ChefHat class="h-4.5 w-4.5 text-amber-500" />
                            Formulasi Resep (Bahan Baku)
                        </h3>
                        <Button as-child variant="outline" size="sm" class="h-8 text-xs">
                            <Link :href="route('catalog.menu')">
                                Edit Resep
                                <ArrowUpRight class="ml-1 h-3.5 w-3.5" />
                            </Link>
                        </Button>
                    </div>

                    <div class="space-y-4">
                        <div v-if="!selectedMenuItem.recipe_items || selectedMenuItem.recipe_items.length === 0" class="flex flex-col items-center justify-center py-16 text-center rounded-xl border border-dashed bg-background/30 p-6">
                            <ChefHat class="h-10 w-10 text-muted-foreground/30 mb-3" />
                            <p class="text-xs font-semibold text-muted-foreground">Belum ada resep terdaftar</p>
                            <p class="text-[10px] text-muted-foreground/70 mt-1 max-w-xs">
                                Menu ini belum memiliki resep. Daftarkan bahan bakunya agar stok terpotong otomatis saat penjualan.
                            </p>
                        </div>

                        <div v-else class="rounded-xl border bg-background/30 overflow-hidden">
                            <table class="w-full text-left border-collapse">
                                <thead>
                                    <tr class="border-b bg-muted/40">
                                        <th class="px-4 py-3 text-xs font-bold uppercase tracking-wider text-muted-foreground">Bahan Baku</th>
                                        <th class="px-4 py-3 text-xs font-bold uppercase tracking-wider text-muted-foreground text-right">Kebutuhan (Porsi)</th>
                                        <th class="px-4 py-3 text-xs font-bold uppercase tracking-wider text-muted-foreground text-right w-24">Satuan</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y">
                                    <tr v-for="ri in selectedMenuItem.recipe_items" :key="ri.id" class="hover:bg-muted/10">
                                        <td class="p-3 px-4">
                                            <div class="font-semibold text-xs text-foreground">
                                                {{ ri.ingredient ? ri.ingredient.name : 'Unknown Ingredient' }}
                                            </div>
                                            <div class="text-[10px] text-muted-foreground">
                                                SKU: {{ ri.ingredient ? ri.ingredient.sku || '—' : '—' }}
                                            </div>
                                        </td>
                                        <td class="p-3 text-right font-bold text-xs text-foreground px-4">
                                            {{ Number(ri.qty) }}
                                        </td>
                                        <td class="p-3 text-right text-xs font-semibold text-muted-foreground px-4">
                                            {{ ri.ingredient ? ri.ingredient.small_unit : 'unit' }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="bg-amber-500/5 border border-amber-500/10 rounded-xl p-4 flex gap-3 text-amber-600">
                            <AlertCircle class="h-5 w-5 shrink-0 mt-0.5" />
                            <div class="text-xs space-y-1">
                                <p class="font-semibold leading-none">Sinkronisasi Stok Otomatis</p>
                                <p class="text-[10.5px] leading-relaxed text-muted-foreground">
                                    Setiap porsi menu ini terjual, sistem akan otomatis memotong stok bahan baku terdaftar di atas sebesar jumlah kebutuhan yang ditentukan. Pastikan batch bahan baku Anda ter-update agar HPP terhitung akurat.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div v-else-if="filteredMenuItems.length === 0" class="flex flex-col items-center justify-center py-24 text-center rounded-xl border-2 border-dashed bg-card/30">
            <UtensilsCrossed class="h-12 w-12 text-muted-foreground/40 mb-4" />
            <h3 class="text-base font-semibold text-foreground">Menu tidak ditemukan</h3>
            <p class="text-xs text-muted-foreground mt-1 max-w-sm">
                Coba sesuaikan kata kunci pencarian Anda atau pilih kategori menu lainnya.
            </p>
        </div>

        <div v-else class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
            <div
                v-for="item in filteredMenuItems"
                :key="item.id"
                @click="selectedMenuItem = item"
                class="group flex flex-col justify-between rounded-xl border bg-card p-5 shadow-sm transition-all hover:shadow-md hover:border-brand-500/30 cursor-pointer"
            >
                <div class="space-y-4">
                    <!-- Card Header -->
                    <div class="flex items-start justify-between">
                        <div>
                            <span class="inline-flex items-center rounded-md bg-brand-500/5 px-2 py-1 text-[10px] font-bold text-brand-600 border border-brand-500/10 mb-2">
                                {{ item.category ? item.category.name : 'Uncategorized' }}
                            </span>
                            <h3 class="font-bold text-foreground text-sm group-hover:text-brand-600 transition-colors">
                                {{ item.name }}
                            </h3>
                        </div>
                        <span class="text-xs font-bold text-foreground">
                            Rp {{ Number(item.price).toLocaleString("id-ID") }}
                        </span>
                    </div>

                    <!-- Recipe Ingredients -->
                    <div class="space-y-2 border-t pt-4">
                        <div class="flex items-center gap-1.5 text-xs font-bold text-muted-foreground tracking-wider uppercase">
                            <ChefHat class="h-3.5 w-3.5 text-amber-500" />
                            Bahan Baku Resep:
                        </div>

                        <!-- Check if recipe exists -->
                        <ul v-if="item.recipe_items && item.recipe_items.length > 0" class="space-y-2">
                            <li
                                v-for="ri in item.recipe_items.slice(0, 3)"
                                :key="ri.id"
                                class="flex items-center justify-between text-xs rounded-lg bg-background/50 p-2 border border-muted/50"
                            >
                                <span class="font-medium text-foreground">
                                    {{ ri.ingredient ? ri.ingredient.name : 'Unknown Ingredient' }}
                                </span>
                                <span class="font-bold text-muted-foreground">
                                    {{ Number(ri.qty) }} {{ ri.ingredient ? ri.ingredient.small_unit : 'unit' }}
                                </span>
                            </li>
                            <li v-if="item.recipe_items.length > 3" class="text-center text-[10px] text-muted-foreground font-semibold pt-1">
                                + {{ item.recipe_items.length - 3 }} bahan baku lainnya
                            </li>
                        </ul>

                        <!-- Empty recipe indicator -->
                        <div
                            v-else
                            class="flex items-center gap-2 rounded-lg border border-amber-500/10 bg-amber-500/5 p-3 text-amber-600"
                        >
                            <AlertCircle class="h-4 w-4 shrink-0" />
                            <div class="text-[10px] leading-snug">
                                Belum ada resep bahan baku yang diatur untuk menu ini.
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Card Footer Actions -->
                <div class="mt-6 flex items-center justify-between border-t pt-4 text-xs">
                    <span class="text-muted-foreground flex items-center gap-1">
                        <CookingPot class="h-3.5 w-3.5" />
                        {{ item.recipe_items ? item.recipe_items.length : 0 }} bahan
                    </span>
                    <span
                        class="font-semibold text-brand-600 hover:text-brand-700 flex items-center gap-0.5"
                    >
                        Lihat Detail
                        <ArrowUpRight class="h-3 w-3" />
                    </span>
                </div>
            </div>
        </div>
    </div>
</template>
