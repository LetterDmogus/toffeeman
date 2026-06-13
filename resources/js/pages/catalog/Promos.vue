<script setup lang="ts">
import { ArrowLeft, Check, Loader2 } from "lucide-vue-next";
import { computed, onMounted, ref } from "vue";
import type { Column } from "@/components/CRUDTable.vue";
import CRUDTable from "@/components/CRUDTable.vue";
import ImageUploader from "@/components/ImageUploader.vue";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Separator } from "@/components/ui/separator";
import catalog from "@/routes/catalog";

defineOptions({
	layout: {
		breadcrumbs: [
			{ title: "Dashboard", href: "/dashboard" },
			{ title: "Katalog", href: "#" },
			{ title: "Sistem Promo", href: catalog.promos().url },
		],
	},
});

const getPromoImage = (img: string | null) => {
	if (!img) {
		return null;
	}

	if (img.startsWith("http") || img.startsWith("/")) {
		return img;
	}

	if (img.startsWith("promo_")) {
		return `/${img}`;
	}

	return `/storage/${img}`;
};

// ─── Types ───────────────────────────────────────────────────────────────────

type ConditionType = "min_amount" | "min_qty" | "specific_item_qty";
type RewardType = "discount_percent" | "discount_nominal" | "free_item";
type RewardScope = "all" | "specific";

interface MenuItem {
	id: number;
	name: string;
}

interface Promo {
	id: number;
	name: string;
	code: string | null;
	description: string | null;
	image: string | null;
	condition_type: ConditionType;
	condition_value: string | null;
	condition_menu_item_id: number | null;
	reward_type: RewardType;
	reward_value: string | null;
	reward_menu_item_id: number | null;
	reward_scope: RewardScope;
	menu_items: MenuItem[];
	start_date: string | null;
	end_date: string | null;
	start_time: string | null;
	end_time: string | null;
	schedule_days: number[] | null;
	is_active: boolean;
	deleted_at: string | null;
}

// ─── State ───────────────────────────────────────────────────────────────────

const menuItems = ref<MenuItem[]>([]);
const editingPromoId = ref<number | null>(null);
const isSubmitting = ref(false);

const emptyForm = () => ({
	name: "",
	code: "",
	description: "",
	image: null as string | null,
	condition_type: "min_amount" as ConditionType,
	condition_value: "",
	condition_menu_item_id: null as number | null,
	reward_type: "discount_percent" as RewardType,
	reward_value: "",
	reward_menu_item_id: null as number | null,
	reward_scope: "all" as RewardScope,
	menu_item_ids: [] as number[],
	start_date: "",
	end_date: "",
	start_time: "",
	end_time: "",
	schedule_days: [] as number[],
	is_active: true,
});

const form = ref(emptyForm());
const errors = ref<Record<string, string>>({});

// ─── Helpers ─────────────────────────────────────────────────────────────────

const DAY_LABELS = ["Min", "Sen", "Sel", "Rab", "Kam", "Jum", "Sab"];

const conditionOptions = [
	{
		value: "min_amount",
		label: "Pembelian minimal Rp",
		placeholder: "Contoh: 50000",
		isAmount: true,
	},
	{
		value: "min_qty",
		label: "Pembelian minimal jumlah item",
		placeholder: "Contoh: 3",
		isAmount: false,
	},
	{
		value: "specific_item_qty",
		label: "Pembelian item tertentu sejumlah",
		placeholder: "Contoh: 1",
		isAmount: false,
	},
];

const rewardOptions = [
	{ value: "discount_percent", label: "Diskon sebesar (%)", suffix: "%" },
	{ value: "discount_nominal", label: "Diskon sebesar (Rp)", suffix: "" },
	{ value: "free_item", label: "Gratis item tertentu", suffix: "" },
];

const currentCondition = computed(() =>
	conditionOptions.find((c) => c.value === form.value.condition_type),
);

function conditionLabel(p: Promo) {
	if (p.condition_type === "min_amount") {
		return `Min. Rp ${new Intl.NumberFormat("id-ID").format(Number(p.condition_value))}`;
	}

	if (p.condition_type === "min_qty") {
		return `Min. ${p.condition_value} item`;
	}

	const item = menuItems.value.find((m) => m.id === p.condition_menu_item_id);

	return `${p.condition_value}x ${item?.name ?? "—"}`;
}

function rewardLabel(p: Promo) {
	if (p.reward_type === "discount_percent") {
		return `Diskon ${p.reward_value}%`;
	}

	if (p.reward_type === "discount_nominal") {
		return `Diskon Rp ${new Intl.NumberFormat("id-ID").format(Number(p.reward_value))}`;
	}

	const item = menuItems.value.find((m) => m.id === p.reward_menu_item_id);

	return `Gratis ${item?.name ?? "—"}`;
}

// ─── Table Configuration ──────────────────────────────────────────────────────

const columns: Column<Promo>[] = [
	{
		key: "name",
		label: "Promo",
		render: (_val, row) => `${row.name} ${row.code ? `(${row.code})` : ""}`,
	},
	{
		key: "is_active",
		label: "Status",
		render: (_val, row) => (row.is_active ? "Aktif" : "Nonaktif"),
	},
	{
		key: "condition_type",
		label: "Syarat",
		render: (_val, row) => conditionLabel(row),
	},
	{
		key: "reward_type",
		label: "Hadiah",
		render: (_val, row) => rewardLabel(row),
	},
	{
		key: "start_date",
		label: "Masa Berlaku",
		render: (_val, row) =>
			row.start_date || row.end_date
				? `${row.start_date || "?"} s/d ${row.end_date || "?"}`
				: "Selamanya",
	},
];

const badgeMap: Record<string, string> = {
	true: "success",
	false: "secondary",
	"1": "success",
	"0": "secondary",
};

// ─── Data Fetching ────────────────────────────────────────────────────────────

async function loadMenuItems() {
	const res = await fetch("/api/menu-items?all=true", {
		headers: { Accept: "application/json" },
	});

	if (res.ok) {
		const data = await res.json();
		menuItems.value = Array.isArray(data) ? data : (data.data ?? []);
	}
}

onMounted(() => {
	loadMenuItems();
});

// ─── Form Lifecycle ──────────────────────────────────────────────────────────

function handleFormOpened({
	mode,
	row,
}: {
	mode: "create" | "edit";
	row: Promo | null;
}) {
	errors.value = {};

	if (mode === "create" || !row) {
		editingPromoId.value = null;
		form.value = emptyForm();
	} else {
		editingPromoId.value = row.id;
		form.value = {
			name: row.name,
			code: row.code ?? "",
			description: row.description ?? "",
			image: getPromoImage(row.image),
			condition_type: row.condition_type,
			condition_value: row.condition_value ?? "",
			condition_menu_item_id: row.condition_menu_item_id,
			reward_type: row.reward_type,
			reward_value: row.reward_value ?? "",
			reward_menu_item_id: row.reward_menu_item_id,
			reward_scope: row.reward_scope,
			menu_item_ids: row.menu_items ? row.menu_items.map((m) => m.id) : [],
			start_date: row.start_date?.split("T")[0] ?? "",
			end_date: row.end_date?.split("T")[0] ?? "",
			start_time: row.start_time ?? "",
			end_time: row.end_time ?? "",
			schedule_days: row.schedule_days ?? [],
			is_active: row.is_active,
		};
	}
}

function toggleDay(day: number) {
	const idx = form.value.schedule_days.indexOf(day);

	if (idx === -1) {
		form.value.schedule_days.push(day);
	} else {
		form.value.schedule_days.splice(idx, 1);
	}
}

function toggleMenuItem(id: number) {
	const idx = form.value.menu_item_ids.indexOf(id);

	if (idx === -1) {
		form.value.menu_item_ids.push(id);
	} else {
		form.value.menu_item_ids.splice(idx, 1);
	}
}

// ─── Submission ───────────────────────────────────────────────────────────────

function getCsrfToken(): string {
	return (
		(document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)
			?.content ?? ""
	);
}

async function submit(
	mode: "create" | "edit",
	cancel: () => void,
	refresh: () => void,
) {
	isSubmitting.value = true;
	errors.value = {};

	const payload: Record<string, unknown> = {
		...form.value,
		schedule_days: form.value.schedule_days.length
			? form.value.schedule_days
			: null,
		menu_item_ids:
			form.value.reward_scope === "specific" ? form.value.menu_item_ids : [],
	};

	// Strip empty strings to null
	[
		"code",
		"description",
		"condition_value",
		"reward_value",
		"start_date",
		"end_date",
		"start_time",
		"end_time",
	].forEach((k) => {
		if (payload[k] === "") {
			payload[k] = null;
		}
	});

	try {
		const url =
			mode === "edit" ? `/api/promos/${editingPromoId.value}` : "/api/promos";
		const method = mode === "edit" ? "PUT" : "POST";

		const res = await fetch(url, {
			method,
			headers: {
				"Content-Type": "application/json",
				Accept: "application/json",
				"X-CSRF-TOKEN": getCsrfToken(),
			},
			body: JSON.stringify(payload),
		});

		const data = await res.json();

		if (!res.ok) {
			errors.value = data.errors ?? {};

			return;
		}

		refresh();
		cancel();
	} finally {
		isSubmitting.value = false;
	}
}
</script>

<template>
    <div class="p-6 space-y-6">
        <div>
            <h1 class="text-2xl font-bold">Sistem Promo</h1>
            <p class="text-muted-foreground mt-1 text-sm">Kelola aturan diskon, penawaran spesial, dan hadiah otomatis.</p>
        </div>

        <CRUDTable
            resourceName="Promo"
            apiUrl="/api/promos"
            :columns="columns"
            :formFields="[]"
            :badgeMap="badgeMap"
            auditable-type="Promo"
            @formOpened="handleFormOpened"
        >
            <template #form="{ mode, cancel, refresh }">
                <div class="rounded-xl border bg-card p-6 shadow-sm animate-in fade-in slide-in-from-bottom-4 duration-300">
                    <div class="flex items-center gap-3 border-b pb-4 mb-6">
                        <Button variant="ghost" size="icon-sm" @click="cancel">
                            <ArrowLeft class="h-4 w-4" />
                        </Button>
                        <div>
                            <h2 class="text-lg font-bold text-foreground">
                                {{ mode === 'create' ? 'Tambah Promo Baru' : 'Edit Promo' }}
                            </h2>
                            <p class="text-xs text-muted-foreground">
                                Konfigurasi syarat (trigger) dan hadiah (reward) untuk promo ini.
                            </p>
                        </div>
                    </div>

                    <form @submit.prevent="submit(mode, cancel, refresh)" class="space-y-8">
                        <!-- ── Basic Info ─────────────────────────────── -->
                        <section class="space-y-4">
                            <h3 class="text-sm font-semibold text-muted-foreground uppercase tracking-wide">Informasi Dasar</h3>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div class="space-y-1.5">
                                    <Label for="promo-name">Nama Promo <span class="text-destructive">*</span></Label>
                                    <Input id="promo-name" v-model="form.name" placeholder="Contoh: Diskon Grand Opening" />
                                    <p v-if="errors.name" class="text-xs text-destructive">{{ errors.name }}</p>
                                </div>
                                <div class="space-y-1.5">
                                    <Label for="promo-code">Kode Promo (Opsional)</Label>
                                    <Input id="promo-code" v-model="form.code" placeholder="Contoh: DISKON10" class="font-mono uppercase" />
                                    <p v-if="errors.code" class="text-xs text-destructive">{{ errors.code }}</p>
                                </div>
                            </div>

                            <div class="space-y-1.5">
                                <Label for="promo-description">Deskripsi</Label>
                                <textarea
                                    id="promo-description"
                                    v-model="form.description"
                                    placeholder="Deskripsi singkat promo..."
                                    rows="2"
                                    class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring resize-none"
                                />
                                <p v-if="errors.description" class="text-xs text-destructive">{{ errors.description }}</p>
                            </div>

                            <div class="space-y-1.5 max-w-sm">
                                <ImageUploader
                                    v-model="form.image"
                                    label="Banner Promo"
                                />
                                <p v-if="errors.image" class="text-xs text-destructive">{{ errors.image }}</p>
                            </div>
                        </section>

                        <Separator />

                        <!-- ── Trigger & Reward Builder ───────────────── -->
                        <section class="space-y-4">
                            <h3 class="text-sm font-semibold text-muted-foreground uppercase tracking-wide">Aturan & Kondisi Promo</h3>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <!-- LEFT: Condition (Trigger) -->
                                <div class="rounded-xl border bg-blue-50/40 dark:bg-blue-950/20 p-5 space-y-4">
                                    <div>
                                        <p class="text-sm font-bold text-blue-700 dark:text-blue-400 flex items-center gap-1.5">
                                            <span class="text-base">🔵</span> Kondisi (Trigger)
                                        </p>
                                        <p class="text-xs text-muted-foreground mt-0.5">Syarat agar promo ini berlaku</p>
                                    </div>

                                    <div class="space-y-1.5">
                                        <Label class="text-xs text-blue-800 dark:text-blue-300">Jenis Kondisi</Label>
                                        <select
                                            v-model="form.condition_type"
                                            class="flex h-10 w-full rounded-lg border border-blue-200 dark:border-blue-800 bg-white dark:bg-blue-950 px-3 py-1 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                                        >
                                            <option v-for="opt in conditionOptions" :key="opt.value" :value="opt.value">
                                                {{ opt.label }}
                                            </option>
                                        </select>
                                    </div>

                                    <!-- Value input -->
                                    <div v-if="form.condition_type !== 'specific_item_qty'" class="space-y-1.5">
                                        <Label class="text-xs text-blue-800 dark:text-blue-300">
                                            {{ currentCondition?.isAmount ? 'Nilai Minimal (Rp)' : 'Jumlah Minimal (item)' }}
                                        </Label>
                                        <Input
                                            v-model="form.condition_value"
                                            type="number"
                                            :placeholder="currentCondition?.placeholder"
                                            min="0"
                                            class="bg-white dark:bg-background"
                                        />
                                        <p v-if="errors.condition_value" class="text-xs text-destructive">{{ errors.condition_value }}</p>
                                    </div>

                                    <!-- specific_item_qty: item + qty -->
                                    <div v-if="form.condition_type === 'specific_item_qty'" class="space-y-3">
                                        <div class="space-y-1.5">
                                            <Label class="text-xs text-blue-800 dark:text-blue-300">Item yang diwajibkan</Label>
                                            <select
                                                v-model="form.condition_menu_item_id"
                                                class="flex h-10 w-full rounded-lg border border-blue-200 dark:border-blue-800 bg-white dark:bg-blue-950 px-3 py-1 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                                            >
                                                <option :value="null" disabled>— Pilih item —</option>
                                                <option v-for="item in menuItems" :key="item.id" :value="item.id">{{ item.name }}</option>
                                            </select>
                                            <p v-if="errors.condition_menu_item_id" class="text-xs text-destructive">{{ errors.condition_menu_item_id }}</p>
                                        </div>
                                        <div class="space-y-1.5">
                                            <Label class="text-xs text-blue-800 dark:text-blue-300">Jumlah item</Label>
                                            <Input v-model="form.condition_value" type="number" placeholder="Contoh: 1" min="1" class="bg-white dark:bg-background" />
                                            <p v-if="errors.condition_value" class="text-xs text-destructive">{{ errors.condition_value }}</p>
                                        </div>
                                    </div>
                                </div>

                                <!-- RIGHT: Reward -->
                                <div class="rounded-xl border bg-green-50/40 dark:bg-green-950/20 p-5 space-y-4">
                                    <div>
                                        <p class="text-sm font-bold text-green-700 dark:text-green-400 flex items-center gap-1.5">
                                            <span class="text-base">🟢</span> Hadiah (Reward)
                                        </p>
                                        <p class="text-xs text-muted-foreground mt-0.5">Bonus yang didapatkan pelanggan</p>
                                    </div>

                                    <div class="space-y-1.5">
                                        <Label class="text-xs text-green-800 dark:text-green-300">Jenis Hadiah</Label>
                                        <select
                                            v-model="form.reward_type"
                                            class="flex h-10 w-full rounded-lg border border-green-200 dark:border-green-800 bg-white dark:bg-green-950 px-3 py-1 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-green-500"
                                        >
                                            <option v-for="opt in rewardOptions" :key="opt.value" :value="opt.value">
                                                {{ opt.label }}
                                            </option>
                                        </select>
                                    </div>

                                    <!-- discount value -->
                                    <div v-if="form.reward_type !== 'free_item'" class="space-y-1.5">
                                        <Label class="text-xs text-green-800 dark:text-green-300">
                                            {{ form.reward_type === 'discount_percent' ? 'Besar diskon (%)' : 'Besar diskon (Rp)' }}
                                        </Label>
                                        <div class="relative">
                                            <Input
                                                v-model="form.reward_value"
                                                type="number"
                                                :placeholder="form.reward_type === 'discount_percent' ? 'Contoh: 10' : 'Contoh: 15000'"
                                                min="0"
                                                class="pr-8 bg-white dark:bg-background"
                                            />
                                            <span class="absolute right-3 top-1/2 -translate-y-1/2 text-xs text-muted-foreground font-semibold">
                                                {{ form.reward_type === 'discount_percent' ? '%' : 'Rp' }}
                                            </span>
                                        </div>
                                        <p v-if="errors.reward_value" class="text-xs text-destructive">{{ errors.reward_value }}</p>
                                    </div>

                                    <!-- free item -->
                                    <div v-if="form.reward_type === 'free_item'" class="space-y-1.5">
                                        <Label class="text-xs text-green-800 dark:text-green-300">Item gratis yang diberikan</Label>
                                        <select
                                            v-model="form.reward_menu_item_id"
                                            class="flex h-10 w-full rounded-lg border border-green-200 dark:border-green-800 bg-white dark:bg-green-950 px-3 py-1 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-green-500"
                                        >
                                            <option :value="null" disabled>— Pilih item —</option>
                                            <option v-for="item in menuItems" :key="item.id" :value="item.id">{{ item.name }}</option>
                                        </select>
                                        <p v-if="errors.reward_menu_item_id" class="text-xs text-destructive">{{ errors.reward_menu_item_id }}</p>
                                    </div>

                                    <!-- Reward scope -->
                                    <div class="pt-3 border-t border-green-200 dark:border-green-800/30 space-y-2.5">
                                        <Label class="text-xs text-green-800 dark:text-green-300">Berlaku untuk Menu</Label>
                                        <select
                                            v-model="form.reward_scope"
                                            class="flex h-10 w-full rounded-lg border border-green-200 dark:border-green-800 bg-white dark:bg-green-950 px-3 py-1 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-green-500"
                                        >
                                            <option value="all">Semua Menu</option>
                                            <option value="specific">Menu Tertentu (Pilih di bawah)</option>
                                        </select>

                                        <!-- Multi-select menu items -->
                                        <div v-if="form.reward_scope === 'specific'" class="mt-2 rounded-lg border bg-white dark:bg-background p-2 max-h-36 overflow-y-auto space-y-1 shadow-inner">
                                            <label
                                                v-for="item in menuItems"
                                                :key="item.id"
                                                class="flex items-center gap-2 cursor-pointer px-2 py-1.5 rounded hover:bg-muted/50 text-sm"
                                            >
                                                <input
                                                    type="checkbox"
                                                    :value="item.id"
                                                    :checked="form.menu_item_ids.includes(item.id)"
                                                    @change="toggleMenuItem(item.id)"
                                                    class="accent-green-600 w-4 h-4 rounded border-gray-300"
                                                />
                                                {{ item.name }}
                                            </label>
                                            <p v-if="menuItems.length === 0" class="text-xs text-muted-foreground py-2 text-center">Tidak ada menu</p>
                                        </div>
                                        <p v-if="errors.menu_item_ids" class="text-xs text-destructive">{{ errors.menu_item_ids }}</p>
                                    </div>
                                </div>
                            </div>
                        </section>

                        <Separator />

                        <!-- ── Schedule ────────────────────────────────── -->
                        <section class="space-y-4">
                            <h3 class="text-sm font-semibold text-muted-foreground uppercase tracking-wide">Jadwal & Ketersediaan</h3>

                            <!-- Active status -->
                            <div class="flex items-center gap-2">
                                <label class="relative inline-flex items-center cursor-pointer">
                                    <input type="checkbox" v-model="form.is_active" class="sr-only peer" />
                                    <div class="w-11 h-6 bg-muted peer-focus:outline-none peer-focus:ring-2 peer-focus:ring-brand-500 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-brand-600"></div>
                                    <span class="ml-3 text-sm font-medium text-foreground">Promo Aktif</span>
                                </label>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 pt-2">
                                <div class="space-y-1.5">
                                    <Label for="start-date">Tanggal Mulai (Opsional)</Label>
                                    <Input id="start-date" v-model="form.start_date" type="date" />
                                    <p v-if="errors.start_date" class="text-xs text-destructive">{{ errors.start_date }}</p>
                                </div>
                                <div class="space-y-1.5">
                                    <Label for="end-date">Tanggal Selesai (Opsional)</Label>
                                    <Input id="end-date" v-model="form.end_date" type="date" />
                                    <p v-if="errors.end_date" class="text-xs text-destructive">{{ errors.end_date }}</p>
                                </div>
                                <div class="space-y-1.5">
                                    <Label for="start-time">Jam Mulai (Opsional)</Label>
                                    <Input id="start-time" v-model="form.start_time" type="time" />
                                </div>
                                <div class="space-y-1.5">
                                    <Label for="end-time">Jam Selesai (Opsional)</Label>
                                    <Input id="end-time" v-model="form.end_time" type="time" />
                                </div>
                            </div>

                            <!-- Days -->
                            <div class="space-y-2 pt-2">
                                <Label class="text-sm">Hari Berlaku <span class="text-muted-foreground font-normal text-xs ml-1">(kosongkan = berlaku setiap hari)</span></Label>
                                <div class="flex gap-2 flex-wrap">
                                    <button
                                        v-for="(day, i) in DAY_LABELS"
                                        :key="i"
                                        type="button"
                                        @click="toggleDay(i)"
                                        :class="[
                                            'px-3.5 py-1.5 rounded-full text-sm font-medium border transition-colors shadow-sm',
                                            form.schedule_days.includes(i)
                                                ? 'bg-brand-600 text-white border-brand-600'
                                                : 'bg-background hover:bg-muted border-input text-foreground/80'
                                        ]"
                                    >
                                        {{ day }}
                                    </button>
                                </div>
                            </div>
                        </section>

                        <div class="flex items-center justify-end gap-3 border-t pt-6 mt-4">
                            <Button type="button" variant="outline" @click="cancel" :disabled="isSubmitting">
                                Batal
                            </Button>
                            <Button type="submit" class="bg-brand-600 hover:bg-brand-700 text-white" :disabled="isSubmitting">
                                <Loader2 v-if="isSubmitting" class="mr-2 h-4 w-4 animate-spin" />
                                <Check v-else class="mr-2 h-4 w-4" />
                                {{ isSubmitting ? 'Menyimpan...' : (mode === 'create' ? 'Simpan Promo Baru' : 'Simpan Perubahan') }}
                            </Button>
                        </div>
                    </form>
                </div>
            </template>
        </CRUDTable>
    </div>
</template>
