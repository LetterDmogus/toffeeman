<script setup lang="ts">
import { Head, Link, router, usePage } from "@inertiajs/vue3";
import {
    AlertCircle,
    BadgeCheck,
    Banknote,
    Calendar,
    ChevronLeft,
    ChevronRight,
    Clock,
    Download,
    FileText,
    Filter,
    Plus,
    Search,
    Users,
    Wallet,
} from "lucide-vue-next";
import { computed, onMounted, ref, watch } from "vue";
import payrollRoutes from "@/routes/payroll/index";

defineOptions({
    layout: {
        breadcrumbs: [
            { title: "Dashboard", href: "/dashboard" },
            { title: "Penggajian", href: payrollRoutes.index().url },
        ],
    },
});

defineProps<{
    filters: { month?: string; year?: string; status?: string; search?: string };
}>();

// ── State ────────────────────────────────────────────────────────────────────
const page = usePage();
const user = computed(() => page.props.auth.user);
const canManage = computed(
    () =>
        user.value?.role === "superadmin" ||
        (page.props.auth as any).permissions?.includes("payroll-manage"),
);

const currentMonth = ref(new Date().getMonth() + 1);
const currentYear = ref(new Date().getFullYear());
const statusFilter = ref("");
const searchQuery = ref("");

const loading = ref(false);
const payrolls = ref<any[]>([]);
const meta = ref<any>(null);
const summary = ref<any>(null);

// Tab/View Switcher
const activeView = ref<"list" | "report">("list");
const reportYear = ref(new Date().getFullYear());
const reportTrend = ref<any[]>([]);
const reportPositionCosts = ref<any[]>([]);
const loadingReport = ref(false);

// ── Helpers ───────────────────────────────────────────────────────────────────
const months = [
    "Januari", "Februari", "Maret", "April", "Mei", "Juni",
    "Juli", "Agustus", "September", "Oktober", "November", "Desember",
];

const periodLabel = computed(() => `${months[currentMonth.value - 1]} ${currentYear.value}`);

const formatCurrency = (val: number) =>
    new Intl.NumberFormat("id-ID", { style: "currency", currency: "IDR", maximumFractionDigits: 0 }).format(val ?? 0);

const statusConfig: Record<string, { label: string; classes: string }> = {
    draft: { label: "Draft", classes: "bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400" },
    approved: { label: "Disetujui", classes: "bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400" },
    paid: { label: "Dibayar", classes: "bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400" },
};

// ── Fetch ─────────────────────────────────────────────────────────────────────
async function fetchPayrolls() {
    loading.value = true;
    try {
        const params = new URLSearchParams({
            month: String(currentMonth.value),
            year: String(currentYear.value),
        });
        if (statusFilter.value) params.set("status", statusFilter.value);
        if (searchQuery.value) params.set("search", searchQuery.value);

        const res = await fetch(`/api/payrolls?${params}`);
        const data = await res.json();
        payrolls.value = data.payrolls.data;
        meta.value = data.payrolls;
        summary.value = data.summary;
    } finally {
        loading.value = false;
    }
}

async function fetchReport() {
    loadingReport.value = true;
    try {
        const res = await fetch(`/api/payrolls/report?year=${reportYear.value}`);
        const data = await res.json();
        reportTrend.value = data.trend;
        reportPositionCosts.value = data.position_costs;
    } finally {
        loadingReport.value = false;
    }
}

// ── Period nav ────────────────────────────────────────────────────────────────
function prevMonth() {
    if (currentMonth.value === 1) { currentMonth.value = 12; currentYear.value--; }
    else currentMonth.value--;
}
function nextMonth() {
    if (currentMonth.value === 12) { currentMonth.value = 1; currentYear.value++; }
    else currentMonth.value++;
}

// ── Actions ────────────────────────────────────────────────────────────────────
function openSlip(id: number) {
    router.visit(payrollRoutes.show(id).url);
}

onMounted(fetchPayrolls);
watch([currentMonth, currentYear, statusFilter, searchQuery], fetchPayrolls);
watch(reportYear, fetchReport);
watch(activeView, (val) => {
    if (val === 'report' && reportTrend.value.length === 0) {
        fetchReport();
    }
});

let searchTimeout: ReturnType<typeof setTimeout>;
function onSearch(e: Event) {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        searchQuery.value = (e.target as HTMLInputElement).value;
    }, 350);
}
</script>

<template>
    <Head title="Penggajian" />

    <div class="flex h-full flex-1 flex-col gap-6 p-6">
        <!-- Header -->
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-slate-800 dark:text-slate-100">Penggajian</h1>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    Kelola slip gaji karyawan dan lihat rekapitulasi penggajian restoran.
                </p>
            </div>
            <div class="flex items-center gap-3">
                <!-- View Selector Buttons -->
                <div class="inline-flex rounded-xl bg-slate-100 p-1 dark:bg-slate-800">
                    <button
                        type="button"
                        class="rounded-lg px-3 py-1.5 text-xs font-semibold transition cursor-pointer"
                        :class="activeView === 'list' ? 'bg-white text-slate-800 shadow-sm dark:bg-slate-700 dark:text-white' : 'text-slate-500 hover:text-slate-800 dark:text-slate-400 dark:hover:text-white'"
                        @click="activeView = 'list'"
                    >
                        Daftar Slip
                    </button>
                    <button
                        type="button"
                        class="rounded-lg px-3 py-1.5 text-xs font-semibold transition cursor-pointer"
                        :class="activeView === 'report' ? 'bg-white text-slate-800 shadow-sm dark:bg-slate-700 dark:text-white' : 'text-slate-500 hover:text-slate-800 dark:text-slate-400 dark:hover:text-white'"
                        @click="activeView = 'report'"
                    >
                        Laporan & Rekap
                    </button>
                </div>

                <Link
                    v-if="canManage"
                    :href="payrollRoutes.create().url"
                    class="inline-flex items-center gap-2 rounded-xl bg-brand-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700"
                >
                    <Plus class="h-4 w-4" />
                    Generate Slip
                </Link>
            </div>
        </div>

        <!-- VIEW LAPORAN & REKAP -->
        <div v-if="activeView === 'report'" class="flex flex-col gap-6">
            <!-- Report Header / Year Filter -->
            <div class="flex items-center justify-between rounded-2xl border border-slate-100 bg-white px-6 py-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="flex items-center gap-2 text-lg font-bold text-slate-800 dark:text-slate-100">
                    <Calendar class="h-5 w-5 text-brand-500" />
                    Rekapitulasi Tahun {{ reportYear }}
                </div>
                <div class="flex items-center gap-2">
                    <a
                        :href="payrollRoutes.exportReport({ year: reportYear }).url"
                        class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-600 transition hover:border-brand-300 hover:text-brand-600 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300"
                    >
                        <Download class="h-3.5 w-3.5" />
                        Export Excel
                    </a>
                    <button
                        class="flex h-9 w-9 items-center justify-center rounded-xl border border-slate-200 text-slate-500 transition hover:border-brand-300 hover:text-brand-600 dark:border-slate-700 cursor-pointer"
                        @click="reportYear--"
                    >
                        <ChevronLeft class="h-4 w-4" />
                    </button>
                    <span class="text-sm font-bold text-slate-700 dark:text-slate-300">{{ reportYear }}</span>
                    <button
                        class="flex h-9 w-9 items-center justify-center rounded-xl border border-slate-200 text-slate-500 transition hover:border-brand-300 hover:text-brand-600 dark:border-slate-700 cursor-pointer"
                        @click="reportYear++"
                    >
                        <ChevronRight class="h-4 w-4" />
                    </button>
                </div>
            </div>

            <!-- Report KPI Summaries -->
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <div class="rounded-2xl border border-slate-100 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <div class="flex items-center gap-3">
                        <div class="rounded-xl bg-brand-100 p-2.5 dark:bg-brand-900/30">
                            <Wallet class="h-5 w-5 text-brand-600 dark:text-brand-400" />
                        </div>
                        <div>
                            <p class="text-xs text-slate-500 dark:text-slate-400">Total Pengeluaran Gaji Bersih (YTD)</p>
                            <p class="text-xl font-bold text-slate-800 dark:text-slate-100">
                                {{ formatCurrency(reportTrend.reduce((acc, curr) => acc + curr.total_net, 0)) }}
                            </p>
                        </div>
                    </div>
                </div>
                <div class="rounded-2xl border border-slate-100 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <div class="flex items-center gap-3">
                        <div class="rounded-xl bg-amber-100 p-2.5 dark:bg-amber-900/30">
                            <Plus class="h-5 w-5 text-amber-600 dark:text-amber-400" />
                        </div>
                        <div>
                            <p class="text-xs text-slate-500 dark:text-slate-400">Total Gaji Pokok & Bonus (YTD)</p>
                            <p class="text-xl font-bold text-slate-800 dark:text-slate-100">
                                {{ formatCurrency(reportTrend.reduce((acc, curr) => acc + curr.total_gross, 0)) }}
                            </p>
                        </div>
                    </div>
                </div>
                <div class="rounded-2xl border border-slate-100 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <div class="flex items-center gap-3">
                        <div class="rounded-xl bg-red-100 p-2.5 dark:bg-red-900/30">
                            <AlertCircle class="h-5 w-5 text-red-600 dark:text-red-400" />
                        </div>
                        <div>
                            <p class="text-xs text-slate-500 dark:text-slate-400">Total Potongan Gaji (YTD)</p>
                            <p class="text-xl font-bold text-slate-800 dark:text-slate-100">
                                {{ formatCurrency(reportTrend.reduce((acc, curr) => acc + curr.total_deductions, 0)) }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Report Grid: Monthly Costs & Position Costs -->
            <div class="grid gap-6 lg:grid-cols-3">
                <!-- Trend Table -->
                <div class="lg:col-span-2 rounded-2xl border border-slate-100 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <h3 class="text-base font-bold text-slate-800 dark:text-slate-100 mb-4">Tren Biaya Gaji Bulanan</h3>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-slate-100 bg-slate-50 text-left dark:border-slate-800 dark:bg-slate-800/50">
                                    <th class="px-4 py-2 font-semibold text-slate-500 dark:text-slate-400">Bulan</th>
                                    <th class="px-4 py-2 text-right font-semibold text-slate-500 dark:text-slate-400">Gaji Pokok</th>
                                    <th class="px-4 py-2 text-right font-semibold text-slate-500 dark:text-slate-400">Kotor (Gross)</th>
                                    <th class="px-4 py-2 text-right font-semibold text-slate-500 dark:text-slate-400">Potongan</th>
                                    <th class="px-4 py-2 text-right font-semibold text-slate-500 dark:text-slate-400">Bersih (Net)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-if="loadingReport" v-for="i in 6" :key="i" class="border-b border-slate-50 dark:border-slate-800">
                                    <td colspan="5" class="px-4 py-3">
                                        <div class="h-4 animate-pulse rounded bg-slate-100 dark:bg-slate-800" />
                                    </td>
                                </tr>
                                <tr
                                    v-else
                                    v-for="t in reportTrend"
                                    :key="t.month"
                                    class="border-b border-slate-50 dark:border-slate-800 hover:bg-slate-50/50 dark:hover:bg-slate-800/20"
                                >
                                    <td class="px-4 py-2.5 font-semibold text-slate-800 dark:text-slate-100">{{ t.label }}</td>
                                    <td class="px-4 py-2.5 text-right text-slate-600 dark:text-slate-300">{{ formatCurrency(t.total_base) }}</td>
                                    <td class="px-4 py-2.5 text-right text-slate-600 dark:text-slate-300">{{ formatCurrency(t.total_gross) }}</td>
                                    <td class="px-4 py-2.5 text-right text-red-500 dark:text-red-400">{{ formatCurrency(t.total_deductions) }}</td>
                                    <td class="px-4 py-2.5 text-right font-bold text-brand-600 dark:text-brand-400">{{ formatCurrency(t.total_net) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Department Cost Table -->
                <div class="rounded-2xl border border-slate-100 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <h3 class="text-base font-bold text-slate-800 dark:text-slate-100 mb-4">Pengeluaran per Jabatan</h3>
                    <div class="flex flex-col gap-4">
                        <div v-if="loadingReport" v-for="i in 4" :key="i" class="flex items-center justify-between py-2 border-b border-slate-50">
                            <div class="h-4 w-24 animate-pulse rounded bg-slate-100 dark:bg-slate-800" />
                            <div class="h-4 w-16 animate-pulse rounded bg-slate-100 dark:bg-slate-800" />
                        </div>
                        <div v-else-if="reportPositionCosts.length === 0" class="text-center py-12 text-slate-400 text-sm">
                            Belum ada rekap pengeluaran jabatan.
                        </div>
                        <div
                            v-else
                            v-for="pc in reportPositionCosts"
                            :key="pc.position_name"
                            class="flex flex-col gap-1 pb-3 border-b border-slate-50 last:border-0 last:pb-0 dark:border-slate-800"
                        >
                            <div class="flex items-center justify-between">
                                <span class="font-semibold text-slate-800 dark:text-slate-200 text-sm">{{ pc.position_name }}</span>
                                <span class="text-xs text-slate-400">{{ pc.count }} slip</span>
                            </div>
                            <div class="flex justify-between items-center mt-0.5">
                                <span class="text-xs text-slate-500">Total Biaya Gaji</span>
                                <span class="font-bold text-slate-800 dark:text-slate-100 text-sm">{{ formatCurrency(pc.total_net) }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- VIEW DAFTAR SLIP -->
        <div v-else class="flex flex-col gap-6">
            <!-- Period navigator -->
            <div class="flex items-center justify-between rounded-2xl border border-slate-100 bg-white px-6 py-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <button
                    class="flex h-9 w-9 items-center justify-center rounded-xl border border-slate-200 text-slate-500 transition hover:border-brand-300 hover:text-brand-600 dark:border-slate-700 cursor-pointer"
                    @click="prevMonth"
                >
                    <ChevronLeft class="h-4 w-4" />
                </button>
                <div class="flex items-center gap-2 text-lg font-bold text-slate-800 dark:text-slate-100">
                    <Calendar class="h-5 w-5 text-brand-500" />
                    {{ periodLabel }}
                </div>
                <button
                    class="flex h-9 w-9 items-center justify-center rounded-xl border border-slate-200 text-slate-500 transition hover:border-brand-300 hover:text-brand-600 dark:border-slate-700 cursor-pointer"
                    @click="nextMonth"
                >
                    <ChevronRight class="h-4 w-4" />
                </button>
            </div>

            <!-- Summary KPI cards -->
            <div v-if="summary" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div class="rounded-2xl border border-slate-100 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <div class="flex items-center gap-3">
                        <div class="rounded-xl bg-brand-100 p-2.5 dark:bg-brand-900/30">
                            <Wallet class="h-5 w-5 text-brand-600 dark:text-brand-400" />
                        </div>
                        <div>
                            <p class="text-xs text-slate-500 dark:text-slate-400">Total Gaji Bersih</p>
                            <p class="text-lg font-bold text-slate-800 dark:text-slate-100">{{ formatCurrency(summary.total_net) }}</p>
                        </div>
                    </div>
                </div>
                <div class="rounded-2xl border border-slate-100 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <div class="flex items-center gap-3">
                        <div class="rounded-xl bg-slate-100 p-2.5 dark:bg-slate-800">
                            <Clock class="h-5 w-5 text-slate-500" />
                        </div>
                        <div>
                            <p class="text-xs text-slate-500 dark:text-slate-400">Draft</p>
                            <p class="text-lg font-bold text-slate-800 dark:text-slate-100">{{ summary.count_draft }} slip</p>
                        </div>
                    </div>
                </div>
                <div class="rounded-2xl border border-slate-100 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <div class="flex items-center gap-3">
                        <div class="rounded-xl bg-amber-100 p-2.5 dark:bg-amber-900/30">
                            <AlertCircle class="h-5 w-5 text-amber-600 dark:text-amber-400" />
                        </div>
                        <div>
                            <p class="text-xs text-slate-500 dark:text-slate-400">Disetujui</p>
                            <p class="text-lg font-bold text-slate-800 dark:text-slate-100">{{ summary.count_approved }} slip</p>
                        </div>
                    </div>
                </div>
                <div class="rounded-2xl border border-slate-100 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <div class="flex items-center gap-3">
                        <div class="rounded-xl bg-emerald-100 p-2.5 dark:bg-emerald-900/30">
                            <BadgeCheck class="h-5 w-5 text-emerald-600 dark:text-emerald-400" />
                        </div>
                        <div>
                            <p class="text-xs text-slate-500 dark:text-slate-400">Dibayar</p>
                            <p class="text-lg font-bold text-slate-800 dark:text-slate-100">{{ summary.count_paid }} slip</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filters & table -->
            <div class="flex flex-col overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <!-- Toolbar -->
                <div class="flex flex-col gap-3 border-b border-slate-100 px-6 py-4 sm:flex-row sm:items-center dark:border-slate-800">
                    <div class="relative flex-1">
                        <Search class="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-slate-400" />
                        <input
                            type="text"
                            placeholder="Cari karyawan..."
                            class="w-full rounded-xl border border-slate-200 bg-slate-50 py-2 pr-4 pl-9 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100"
                            @input="onSearch"
                        />
                    </div>
                    <div class="flex items-center gap-2">
                        <a
                            :href="payrollRoutes.exportList({ month: currentMonth, year: currentYear, status: statusFilter, search: searchQuery }).url"
                            class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-600 transition hover:border-brand-300 hover:text-brand-600 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300"
                        >
                            <Download class="h-3.5 w-3.5" />
                            Export Excel
                        </a>
                        <Filter class="h-4 w-4 text-slate-400" />
                        <select
                            v-model="statusFilter"
                            class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100"
                        >
                            <option value="">Semua Status</option>
                            <option value="draft">Draft</option>
                            <option value="approved">Disetujui</option>
                            <option value="paid">Dibayar</option>
                        </select>
                    </div>
                </div>

                <!-- Table -->
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-slate-100 bg-slate-50 text-left dark:border-slate-800 dark:bg-slate-800/50">
                                <th class="px-6 py-3 font-semibold text-slate-500 dark:text-slate-400">Karyawan</th>
                                <th class="px-6 py-3 font-semibold text-slate-500 dark:text-slate-400">Jabatan</th>
                                <th class="px-6 py-3 text-right font-semibold text-slate-500 dark:text-slate-400">Gaji Pokok</th>
                                <th class="px-6 py-3 text-right font-semibold text-slate-500 dark:text-slate-400">Gaji Bersih</th>
                                <th class="px-6 py-3 text-center font-semibold text-slate-500 dark:text-slate-400">Kehadiran</th>
                                <th class="px-6 py-3 text-center font-semibold text-slate-500 dark:text-slate-400">Status</th>
                                <th class="px-6 py-3" />
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-if="loading"
                                v-for="i in 4"
                                :key="i"
                                class="border-b border-slate-50 dark:border-slate-800"
                            >
                                <td colspan="7" class="px-6 py-4">
                                    <div class="h-4 animate-pulse rounded bg-slate-100 dark:bg-slate-800" />
                                </td>
                            </tr>

                            <tr
                                v-else-if="payrolls.length === 0"
                            >
                                <td colspan="7" class="px-6 py-16 text-center text-slate-400">
                                    <Users class="mx-auto mb-3 h-10 w-10 opacity-30" />
                                    <p class="font-medium">Belum ada slip gaji untuk periode ini.</p>
                                    <p v-if="canManage" class="mt-1 text-xs">
                                        Klik <strong>Generate Slip</strong> untuk membuat slip baru.
                                    </p>
                                </td>
                            </tr>

                            <tr
                                v-else
                                v-for="p in payrolls"
                                :key="p.id"
                                class="cursor-pointer border-b border-slate-50 transition hover:bg-slate-50 dark:border-slate-800 dark:hover:bg-slate-800/50"
                                @click="openSlip(p.id)"
                            >
                                <td class="px-6 py-4 font-medium text-slate-800 dark:text-slate-100">
                                    {{ p.employee?.name }}
                                </td>
                                <td class="px-6 py-4 text-slate-500 dark:text-slate-400">
                                    {{ p.employee?.position?.name ?? '—' }}
                                </td>
                                <td class="px-6 py-4 text-right text-slate-600 dark:text-slate-300">
                                    {{ formatCurrency(p.base_salary) }}
                                </td>
                                <td class="px-6 py-4 text-right font-semibold text-slate-800 dark:text-slate-100">
                                    {{ formatCurrency(p.net_salary) }}
                                </td>
                                <td class="px-6 py-4 text-center text-slate-500">
                                    <span class="text-emerald-600 font-semibold dark:text-emerald-400">{{ p.present_days }}</span>
                                    <span class="text-slate-300 dark:text-slate-600"> / </span>
                                    <span>{{ p.working_days }} hari</span>
                                </td>
                                <td class="px-6 py-4 text-center">
                                    <span
                                        class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold"
                                        :class="statusConfig[p.status]?.classes"
                                    >
                                        {{ statusConfig[p.status]?.label }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <Link
                                        :href="payrollRoutes.show(p.id).url"
                                        class="inline-flex items-center gap-1 text-xs font-medium text-brand-600 hover:underline dark:text-brand-400"
                                        @click.stop
                                    >
                                        <FileText class="h-3.5 w-3.5" />
                                        Detail
                                    </Link>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div v-if="meta && meta.last_page > 1" class="flex items-center justify-between border-t border-slate-100 px-6 py-3 dark:border-slate-800">
                    <span class="text-xs text-slate-400">
                        {{ meta.from }}–{{ meta.to }} dari {{ meta.total }} slip
                    </span>
                    <div class="flex gap-1">
                        <button
                            v-if="meta.prev_page_url"
                            class="rounded-lg border px-3 py-1.5 text-xs hover:bg-slate-50 dark:border-slate-700 dark:hover:bg-slate-800 cursor-pointer"
                            @click="fetchPayrolls"
                        >
                            &laquo;
                        </button>
                        <button
                            v-if="meta.next_page_url"
                            class="rounded-lg border px-3 py-1.5 text-xs hover:bg-slate-50 dark:border-slate-700 dark:hover:bg-slate-800 cursor-pointer"
                            @click="fetchPayrolls"
                        >
                            &raquo;
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
