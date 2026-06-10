<script setup lang="ts">
import { Head } from "@inertiajs/vue3";
import { ref, computed, watch, onMounted } from "vue";
import {
    Calendar,
    ChevronLeft,
    ChevronRight,
    ClipboardCheck,
    Clock,
    Download,
    Filter,
    RefreshCw,
    Search,
    ShoppingBag,
    Utensils,
    Flame,
    CheckCircle2,
    XCircle,
} from "lucide-vue-next";

defineOptions({
    layout: {
        breadcrumbs: [
            { title: "Dashboard", href: "/dashboard" },
            { title: "Laporan Pesanan", href: "/reports/orders" },
        ],
    },
});

// ── Types ──────────────────────────────────────────────────────────────
interface OrderRecord {
    id: number;
    order_number: string;
    order_type: "dine_in" | "take_away";
    total_amount: number;
    discount_amount: number;
    tax_amount: number;
    final_amount: number;
    payment_method: string;
    payment_status: "paid" | "unpaid";
    status: "pending" | "processing" | "ready" | "served" | "cancelled";
    created_at: string;
    customer: { id: number; name: string } | null;
    table: { id: number; number: string } | null;
    items: Array<{
        id: number;
        name: string;
        qty: number;
        price: number;
        subtotal: number;
    }>;
}

// ── Filters ─────────────────────────────────────────────────────────────
const today = new Date().toISOString().slice(0, 10);
const filterType = ref<"day" | "month" | "year" | "range">("day");
const filterDate = ref(today);
const filterMonth = ref(new Date().getMonth() + 1);
const filterYear = ref(new Date().getFullYear());
const filterStartDate = ref("");
const filterEndDate = ref("");
const filterStatus = ref("");
const filterPaymentStatus = ref("");
const filterOrderType = ref("");
const filterSearch = ref("");

const months = [
    { value: 1, label: "Januari" },
    { value: 2, label: "Februari" },
    { value: 3, label: "Maret" },
    { value: 4, label: "April" },
    { value: 5, label: "Mei" },
    { value: 6, label: "Juni" },
    { value: 7, label: "Juli" },
    { value: 8, label: "Agustus" },
    { value: 9, label: "September" },
    { value: 10, label: "Oktober" },
    { value: 11, label: "November" },
    { value: 12, label: "Desember" },
];

const currentYear = new Date().getFullYear();
const years = Array.from({ length: 6 }, (_, i) => currentYear - i);

// ── Data state ────────────────────────────────────────────────────────
const records = ref<OrderRecord[]>([]);
const topItems = ref<Array<{ name: string; total_qty: number; total_revenue: number }>>([]);
const showTopItemsModal = ref(false);
const kpi = ref({
    total_orders: 0,
    total_items_sold: 0,
    status_pending: 0,
    status_processing: 0,
    status_ready: 0,
    status_served: 0,
    status_cancelled: 0,
    type_dine_in: 0,
    type_take_away: 0,
});
const pagination = ref({ current_page: 1, last_page: 1, total: 0, per_page: 15 });
const isLoading = ref(false);

// Format Rupiah Helper
function formatIDR(value: number): string {
    return new Intl.NumberFormat("id-ID", {
        style: "currency",
        currency: "IDR",
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
    }).format(value);
}

// ── Fetch helpers ─────────────────────────────────────────────────────
function buildParams(isExport = false): URLSearchParams {
    const params = new URLSearchParams();
    if (isExport) {
        params.set("export", "1");
    }

    if (filterType.value === "day" && filterDate.value) {
        params.set("date", filterDate.value);
    } else if (filterType.value === "month") {
        params.set("month", String(filterMonth.value));
        params.set("year", String(filterYear.value));
    } else if (filterType.value === "year") {
        params.set("year", String(filterYear.value));
    } else if (filterType.value === "range" && filterStartDate.value && filterEndDate.value) {
        params.set("start_date", filterStartDate.value);
        params.set("end_date", filterEndDate.value);
    }

    if (filterStatus.value) params.set("status", filterStatus.value);
    if (filterPaymentStatus.value) params.set("payment_status", filterPaymentStatus.value);
    if (filterOrderType.value) params.set("order_type", filterOrderType.value);
    if (filterSearch.value) params.set("search", filterSearch.value);

    return params;
}

async function fetchRecords(page = 1) {
    isLoading.value = true;
    try {
        const params = buildParams(false);
        params.set("page", String(page));
        params.set("per_page", "15");

        const res = await fetch(`/api/reports/orders?${params.toString()}`);
        if (!res.ok) throw new Error("Gagal memuat data laporan");
        const data = await res.json();

        records.value = data.orders?.data ?? [];
        topItems.value = data.top_items ?? [];
        kpi.value = data.kpi ?? {
            total_orders: 0,
            total_items_sold: 0,
            status_pending: 0,
            status_processing: 0,
            status_ready: 0,
            status_served: 0,
            status_cancelled: 0,
            type_dine_in: 0,
            type_take_away: 0,
        };
        pagination.value = {
            current_page: data.orders?.current_page ?? 1,
            last_page: data.orders?.last_page ?? 1,
            total: data.orders?.total ?? 0,
            per_page: data.orders?.per_page ?? 15,
        };
    } catch (e) {
        console.error(e);
    } finally {
        isLoading.value = false;
    }
}

function handleExport() {
    const params = buildParams(true);
    window.open(`/api/reports/orders?${params.toString()}`, "_blank");
}

// ── Watchers ──────────────────────────────────────────────────────────
let debounceTimer: ReturnType<typeof setTimeout>;
watch(
    [
        filterType,
        filterDate,
        filterMonth,
        filterYear,
        filterStartDate,
        filterEndDate,
        filterStatus,
        filterPaymentStatus,
        filterOrderType,
    ],
    () => fetchRecords(1)
);
watch(filterSearch, () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => fetchRecords(1), 350);
});

onMounted(() => {
    fetchRecords();
});

// ── UI Configs ────────────────────────────────────────────────────────
const statusBadgeMap = {
    pending: { label: "Menunggu", color: "bg-amber-100 text-amber-700 border-amber-200", icon: Clock },
    processing: { label: "Dimasak", color: "bg-blue-100 text-blue-700 border-blue-200", icon: Flame },
    ready: { label: "Siap Saji", color: "bg-emerald-100 text-emerald-700 border-emerald-200", icon: Utensils },
    served: { label: "Selesai", color: "bg-brand-100 text-brand-700 border-brand-200", icon: CheckCircle2 },
    cancelled: { label: "Batal", color: "bg-rose-100 text-rose-700 border-rose-200", icon: XCircle },
};

const paymentBadgeMap = {
    paid: { label: "Lunas", color: "bg-emerald-100 text-emerald-700 border-emerald-200" },
    unpaid: { label: "Belum Bayar", color: "bg-amber-100 text-amber-700 border-amber-200" },
};
</script>

<template>
    <Head title="Laporan Pesanan" />

    <div class="p-6 space-y-6">
        <!-- Header -->
        <div class="flex items-center justify-between flex-wrap gap-3">
            <div>
                <h1 class="text-2xl font-bold text-slate-900 tracking-tight">Laporan Pesanan</h1>
                <p class="text-sm text-slate-500 mt-0.5">Analisis histori pesanan, tipe saji, dan performa menu hidangan</p>
            </div>
            <button
                @click="showTopItemsModal = true"
                class="flex items-center gap-2 px-4 py-2.5 bg-brand-600 hover:bg-brand-700 text-white text-sm font-semibold rounded-xl shadow-sm transition-all duration-200 hover:-translate-y-0.5 cursor-pointer"
            >
                <ClipboardCheck class="h-4 w-4" />
                Lihat Menu Terlaris
            </button>
        </div>

        <!-- Stats Overview Cards -->
        <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-8 gap-3">
            <div class="bg-white border border-slate-200 rounded-xl p-4 text-center shadow-xs lg:col-span-2">
                <p class="text-2xl font-bold text-brand-600">{{ kpi.total_orders }}</p>
                <p class="text-xs text-slate-500 font-medium mt-0.5 flex items-center justify-center gap-1">
                    <ShoppingBag class="h-3 w-3 text-brand-500" /> Total Pesanan
                </p>
            </div>
            <div class="bg-white border border-slate-200 rounded-xl p-4 text-center shadow-xs lg:col-span-2">
                <p class="text-2xl font-bold text-emerald-600">{{ kpi.total_items_sold }}</p>
                <p class="text-xs text-slate-500 font-medium mt-0.5">Total Porsi Terjual</p>
            </div>
            <div class="bg-white border border-slate-200 rounded-xl p-4 text-center shadow-xs">
                <p class="text-lg font-bold text-amber-600">{{ kpi.status_pending }}</p>
                <p class="text-[10px] text-slate-400 font-semibold uppercase mt-0.5">Menunggu</p>
            </div>
            <div class="bg-white border border-slate-200 rounded-xl p-4 text-center shadow-xs">
                <p class="text-lg font-bold text-blue-600">{{ kpi.status_processing }}</p>
                <p class="text-[10px] text-slate-400 font-semibold uppercase mt-0.5">Dimasak</p>
            </div>
            <div class="bg-white border border-slate-200 rounded-xl p-4 text-center shadow-xs">
                <p class="text-lg font-bold text-emerald-600">{{ kpi.status_ready }}</p>
                <p class="text-[10px] text-slate-400 font-semibold uppercase mt-0.5">Siap Saji</p>
            </div>
            <div class="bg-white border border-slate-200 rounded-xl p-4 text-center shadow-xs">
                <p class="text-lg font-bold text-brand-700">{{ kpi.status_served }}</p>
                <p class="text-[10px] text-slate-400 font-semibold uppercase mt-0.5">Selesai</p>
            </div>
        </div>

        <div class="space-y-6">
            <!-- Flexible Period Filters -->
            <div class="bg-white border border-slate-200 rounded-2xl p-4 shadow-xs flex flex-col gap-4">
                <div class="flex flex-wrap gap-4 items-end">
                    <!-- Period Type -->
                    <div class="flex flex-col gap-1">
                        <label class="text-xs font-semibold text-slate-600 flex items-center gap-1">
                            <Calendar class="h-3 w-3" /> Periode
                        </label>
                        <select
                            v-model="filterType"
                            class="border border-slate-200 rounded-lg px-3 py-1.5 text-sm text-slate-700 bg-white shadow-xs focus:ring-2 focus:ring-brand-400 focus:outline-none"
                        >
                            <option value="day">Harian</option>
                            <option value="month">Bulanan</option>
                            <option value="year">Tahunan</option>
                            <option value="range">Rentang Tanggal</option>
                        </select>
                    </div>

                    <!-- Date selection -->
                    <div v-if="filterType === 'day'" class="flex flex-col gap-1">
                        <label class="text-xs font-semibold text-slate-600">Pilih Tanggal</label>
                        <input
                            v-model="filterDate"
                            type="date"
                            class="border border-slate-200 rounded-lg px-3 py-1.5 text-sm text-slate-700 bg-white shadow-xs focus:ring-2 focus:ring-brand-400 focus:outline-none"
                        />
                    </div>

                    <!-- Month selection -->
                    <div v-if="filterType === 'month'" class="flex flex-col gap-1">
                        <label class="text-xs font-semibold text-slate-600">Pilih Bulan</label>
                        <select
                            v-model="filterMonth"
                            class="border border-slate-200 rounded-lg px-3 py-1.5 text-sm text-slate-700 bg-white shadow-xs focus:ring-2 focus:ring-brand-400 focus:outline-none"
                        >
                            <option v-for="m in months" :key="m.value" :value="m.value">{{ m.label }}</option>
                        </select>
                    </div>

                    <!-- Year selection -->
                    <div v-if="filterType === 'month' || filterType === 'year'" class="flex flex-col gap-1">
                        <label class="text-xs font-semibold text-slate-600">Pilih Tahun</label>
                        <select
                            v-model="filterYear"
                            class="border border-slate-200 rounded-lg px-3 py-1.5 text-sm text-slate-700 bg-white shadow-xs focus:ring-2 focus:ring-brand-400 focus:outline-none"
                        >
                            <option v-for="y in years" :key="y" :value="y">{{ y }}</option>
                        </select>
                    </div>

                    <!-- Custom Date Range -->
                    <div v-if="filterType === 'range'" class="flex gap-2 items-end">
                        <div class="flex flex-col gap-1">
                            <label class="text-xs font-semibold text-slate-600">Mulai</label>
                            <input
                                v-model="filterStartDate"
                                type="date"
                                class="border border-slate-200 rounded-lg px-3 py-1.5 text-sm text-slate-700 bg-white shadow-xs focus:ring-2 focus:ring-brand-400 focus:outline-none"
                            />
                        </div>
                        <div class="flex flex-col gap-1">
                            <label class="text-xs font-semibold text-slate-600">Selesai</label>
                            <input
                                v-model="filterEndDate"
                                type="date"
                                class="border border-slate-200 rounded-lg px-3 py-1.5 text-sm text-slate-700 bg-white shadow-xs focus:ring-2 focus:ring-brand-400 focus:outline-none"
                            />
                        </div>
                    </div>

                    <!-- Dish Status -->
                    <div class="flex flex-col gap-1">
                        <label class="text-xs font-semibold text-slate-600 flex items-center gap-1">
                            <Filter class="h-3 w-3" /> Status Sajian
                        </label>
                        <select
                            v-model="filterStatus"
                            class="border border-slate-200 rounded-lg px-3 py-1.5 text-sm text-slate-700 bg-white shadow-xs focus:ring-2 focus:ring-brand-400 focus:outline-none"
                        >
                            <option value="">Semua</option>
                            <option value="pending">Menunggu</option>
                            <option value="processing">Dimasak</option>
                            <option value="ready">Siap Saji</option>
                            <option value="served">Selesai</option>
                            <option value="cancelled">Batal</option>
                        </select>
                    </div>

                    <!-- Type (Dine In / Take Away) -->
                    <div class="flex flex-col gap-1">
                        <label class="text-xs font-semibold text-slate-600">Tipe</label>
                        <select
                            v-model="filterOrderType"
                            class="border border-slate-200 rounded-lg px-3 py-1.5 text-sm text-slate-700 bg-white shadow-xs focus:ring-2 focus:ring-brand-400 focus:outline-none"
                        >
                            <option value="">Semua</option>
                            <option value="dine_in">Dine In (Makan di Sini)</option>
                            <option value="take_away">Take Away (Bawa Pulang)</option>
                        </select>
                    </div>

                    <!-- Search -->
                    <div class="flex flex-col gap-1 flex-1 min-w-40">
                        <label class="text-xs font-semibold text-slate-600 flex items-center gap-1">
                            <Search class="h-3 w-3" /> Cari Nomor Pesanan
                        </label>
                        <div class="relative">
                            <Search class="absolute left-2.5 top-1/2 -translate-y-1/2 h-3.5 w-3.5 text-slate-400" />
                            <input
                                v-model="filterSearch"
                                type="text"
                                placeholder="Cari ORD-..."
                                class="w-full border border-slate-200 rounded-lg pl-8 pr-3 py-1.5 text-sm text-slate-700 bg-white shadow-xs focus:ring-2 focus:ring-brand-400 focus:outline-none"
                            />
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="flex items-center gap-2 pb-0.5">
                        <button
                            @click="fetchRecords(pagination.current_page)"
                            class="flex items-center gap-1.5 px-3 py-1.5 text-sm text-slate-600 bg-slate-100 hover:bg-slate-200 rounded-lg transition cursor-pointer"
                        >
                            <RefreshCw class="h-3.5 w-3.5" :class="isLoading ? 'animate-spin' : ''" />
                            Refresh
                        </button>
                        <button
                            @click="handleExport"
                            class="flex items-center gap-1.5 px-3 py-1.5 text-sm text-emerald-700 bg-emerald-50 hover:bg-emerald-100 border border-emerald-200 rounded-lg transition cursor-pointer"
                            title="Ekspor laporan pesanan ke CSV"
                        >
                            <Download class="h-3.5 w-3.5 text-emerald-600" />
                            Ekspor CSV
                        </button>
                    </div>
                </div>
            </div>

            <!-- Orders Table -->
            <div class="bg-white border border-slate-200 rounded-2xl shadow-xs overflow-hidden">
                <div v-if="isLoading" class="flex items-center justify-center py-20 text-slate-400 gap-2">
                    <RefreshCw class="h-5 w-5 animate-spin" />
                    <span class="text-sm">Memuat data...</span>
                </div>

                <div v-else-if="records.length === 0" class="flex flex-col items-center justify-center py-20 text-slate-400 gap-3">
                    <ShoppingBag class="h-12 w-12 opacity-30" />
                    <p class="text-sm font-medium">Tidak ada data pesanan untuk periode ini</p>
                </div>

                <div v-else class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-slate-100 bg-slate-50 text-xs font-semibold text-slate-500 uppercase tracking-wide">
                                <th class="px-4 py-3 text-left">No. Pesanan</th>
                                <th class="px-4 py-3 text-left">Pelanggan</th>
                                <th class="px-4 py-3 text-left">Meja</th>
                                <th class="px-4 py-3 text-left">Tipe</th>
                                <th class="px-4 py-3 text-right">Total (Net)</th>
                                <th class="px-4 py-3 text-center">Status Saji</th>
                                <th class="px-4 py-3 text-center">Status Bayar</th>
                                <th class="px-4 py-3 text-left">Waktu</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-50">
                            <tr v-for="order in records" :key="order.id" class="hover:bg-slate-50/80 transition">
                                <td class="px-4 py-3 font-semibold text-slate-800">{{ order.order_number }}</td>
                                <td class="px-4 py-3 text-slate-600 text-xs">{{ order.customer?.name ?? "Pelanggan Umum / Guest" }}</td>
                                <td class="px-4 py-3 text-slate-600 text-xs">{{ order.table ? 'Meja ' + order.table.number : '—' }}</td>
                                <td class="px-4 py-3 text-xs">
                                    <span :class="order.order_type === 'dine_in' ? 'text-blue-600' : 'text-orange-500'">
                                        {{ order.order_type === 'dine_in' ? 'Dine In' : 'Take Away' }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right font-mono font-semibold text-slate-800">{{ formatIDR(order.final_amount) }}</td>
                                <td class="px-4 py-3 text-center">
                                    <span
                                        class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold border"
                                        :class="statusBadgeMap[order.status]?.color"
                                    >
                                        {{ statusBadgeMap[order.status]?.label }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <span
                                        class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold border"
                                        :class="paymentBadgeMap[order.payment_status]?.color"
                                    >
                                        {{ paymentBadgeMap[order.payment_status]?.label }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-slate-400 text-xs">
                                    {{ new Date(order.created_at).toLocaleDateString("id-ID", { day: "2-digit", month: "short" }) }}
                                    {{ new Date(order.created_at).toLocaleTimeString("id-ID", { hour: "2-digit", minute: "2-digit" }) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div v-if="!isLoading && records.length > 0" class="flex items-center justify-between px-4 py-3 border-t border-slate-100 bg-slate-50">
                    <p class="text-xs text-slate-500">
                        Halaman <b>{{ pagination.current_page }}</b> dari <b>{{ pagination.last_page }}</b>
                        &nbsp;·&nbsp; <b>{{ pagination.total }}</b> total pesanan
                    </p>
                    <div class="flex gap-1">
                        <button
                            :disabled="pagination.current_page <= 1"
                            @click="fetchRecords(pagination.current_page - 1)"
                            class="p-1.5 rounded-lg hover:bg-slate-200 disabled:opacity-30 disabled:cursor-not-allowed transition cursor-pointer"
                        >
                            <ChevronLeft class="h-4 w-4 text-slate-600" />
                        </button>
                        <button
                            :disabled="pagination.current_page >= pagination.last_page"
                            @click="fetchRecords(pagination.current_page + 1)"
                            class="p-1.5 rounded-lg hover:bg-slate-200 disabled:opacity-30 disabled:cursor-not-allowed transition cursor-pointer"
                        >
                            <ChevronRight class="h-4 w-4 text-slate-600" />
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Menu Hidangan Terlaris -->
    <Teleport to="body">
        <div
            v-if="showTopItemsModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4 transition-opacity duration-300"
            @click.self="showTopItemsModal = false"
        >
            <div class="bg-white border border-slate-200 w-full max-w-md rounded-2xl shadow-xl overflow-hidden transform transition-all flex flex-col max-h-[85vh]">
                <!-- Modal Header -->
                <div class="p-4 border-b border-slate-100 flex items-center justify-between">
                    <h3 class="text-sm font-bold text-slate-900 flex items-center gap-1.5">
                        <ClipboardCheck class="h-4 w-4 text-emerald-600" />
                        Menu Hidangan Terlaris
                    </h3>
                    <button
                        @click="showTopItemsModal = false"
                        class="p-1 text-slate-400 hover:text-slate-600 rounded-lg hover:bg-slate-100 transition cursor-pointer"
                    >
                        ✕
                    </button>
                </div>

                <!-- Modal Body -->
                <div class="p-4 overflow-y-auto space-y-3 flex-1 min-h-0">
                    <div v-if="isLoading" class="flex items-center justify-center py-12 text-slate-400 gap-2">
                        <RefreshCw class="h-4 w-4 animate-spin" />
                        <span class="text-xs">Memuat menu...</span>
                    </div>

                    <div v-else-if="topItems.length === 0" class="text-center py-12 text-slate-400 text-xs">
                        Belum ada item hidangan terjual pada periode ini
                    </div>

                    <div v-else class="space-y-3">
                        <div
                            v-for="(item, index) in topItems"
                            :key="index"
                            class="flex items-center justify-between p-2.5 rounded-xl bg-slate-50 border border-slate-100 hover:border-slate-200 transition"
                        >
                            <div class="flex items-center gap-2">
                                <span class="h-6 w-6 rounded bg-brand-100 text-brand-700 text-xs font-bold flex items-center justify-center">
                                    {{ index + 1 }}
                                </span>
                                <div>
                                    <p class="text-xs font-semibold text-slate-800">{{ item.name }}</p>
                                    <p class="text-[10px] text-slate-400 font-mono mt-0.5">{{ formatIDR(item.total_revenue) }}</p>
                                </div>
                            </div>
                            <span class="text-xs font-bold text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded border border-emerald-100">
                                {{ item.total_qty }} porsi
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Modal Footer -->
                <div class="p-3 border-t border-slate-100 bg-slate-50 flex justify-end">
                    <button
                        @click="showTopItemsModal = false"
                        class="px-4 py-2 bg-slate-200 hover:bg-slate-300 text-slate-800 text-xs font-semibold rounded-lg transition cursor-pointer"
                    >
                        Tutup
                    </button>
                </div>
            </div>
        </div>
    </Teleport>
</template>
