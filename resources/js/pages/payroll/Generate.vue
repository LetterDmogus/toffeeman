<script setup lang="ts">
import { Head, router } from "@inertiajs/vue3";
import {
    AlertTriangle,
    Calendar,
    ChevronLeft,
    ChevronRight,
    RefreshCw,
    Users,
    Zap,
} from "lucide-vue-next";
import { computed, onMounted, ref, watch } from "vue";

defineOptions({
    layout: {
        breadcrumbs: [
            { title: "Dashboard", href: "/dashboard" },
            { title: "Penggajian", href: route("payroll.index") },
            { title: "Generate Slip", href: route("payroll.create") },
        ],
    },
});

// ── State ─────────────────────────────────────────────────────────────────────
const currentMonth = ref(new Date().getMonth() + 1);
const currentYear = ref(new Date().getFullYear());
const loading = ref(false);
const generating = ref(false);

const preview = ref<{
    employees: { id: number; name: string; position: string; salary: number }[];
    working_days: number;
    period_label: string;
} | null>(null);

// ── Helpers ───────────────────────────────────────────────────────────────────
const months = [
    "Januari", "Februari", "Maret", "April", "Mei", "Juni",
    "Juli", "Agustus", "September", "Oktober", "November", "Desember",
];
const periodLabel = computed(() => `${months[currentMonth.value - 1]} ${currentYear.value}`);
const formatCurrency = (val: number) =>
    new Intl.NumberFormat("id-ID", { style: "currency", currency: "IDR", maximumFractionDigits: 0 }).format(val ?? 0);

// ── Fetch preview ─────────────────────────────────────────────────────────────
async function fetchPreview() {
    loading.value = true;
    try {
        const res = await fetch(`/api/payrolls/missing-employees?month=${currentMonth.value}&year=${currentYear.value}`);
        preview.value = await res.json();
    } finally {
        loading.value = false;
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

// ── Generate ──────────────────────────────────────────────────────────────────
function generate(employeeId: number | null = null) {
    generating.value = true;
    router.post(
        route("payroll.generate"),
        { 
            month: currentMonth.value, 
            year: currentYear.value,
            employee_id: employeeId 
        },
        {
            onFinish: () => { generating.value = false; },
        },
    );
}

onMounted(fetchPreview);
watch([currentMonth, currentYear], fetchPreview);
</script>

<template>
    <Head title="Generate Slip Gaji" />

    <div class="flex h-full flex-1 flex-col gap-6 p-6">
        <!-- Header -->
        <div>
            <h1 class="text-2xl font-bold text-slate-800 dark:text-slate-100">Generate Slip Gaji</h1>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                Pilih periode, lalu generate slip untuk semua karyawan aktif yang belum punya slip di periode tersebut, atau buat secara individu.
            </p>
        </div>

        <div class="grid gap-6 lg:grid-cols-3">
            <!-- Left: Period picker + action -->
            <div class="flex flex-col gap-4">
                <!-- Period card -->
                <div class="rounded-2xl border border-slate-100 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <h2 class="mb-4 flex items-center gap-2 text-sm font-semibold text-slate-600 dark:text-slate-400">
                        <Calendar class="h-4 w-4" />
                        Pilih Periode
                    </h2>
                    <div class="flex items-center justify-between">
                        <button
                            class="flex h-9 w-9 items-center justify-center rounded-xl border border-slate-200 text-slate-500 transition hover:border-brand-400 hover:text-brand-600 dark:border-slate-700"
                            @click="prevMonth"
                        >
                            <ChevronLeft class="h-4 w-4" />
                        </button>
                        <span class="text-xl font-bold text-slate-800 dark:text-slate-100">{{ periodLabel }}</span>
                        <button
                            class="flex h-9 w-9 items-center justify-center rounded-xl border border-slate-200 text-slate-500 transition hover:border-brand-400 hover:text-brand-600 dark:border-slate-700"
                            @click="nextMonth"
                        >
                            <ChevronRight class="h-4 w-4" />
                        </button>
                    </div>

                    <div v-if="preview" class="mt-4 rounded-xl bg-slate-50 p-3 text-sm dark:bg-slate-800">
                        <div class="flex justify-between text-slate-500 dark:text-slate-400">
                            <span>Hari kerja</span>
                            <span class="font-semibold text-slate-700 dark:text-slate-200">{{ preview.working_days }} hari</span>
                        </div>
                    </div>
                </div>

                <!-- Info box -->
                <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-700 dark:border-amber-900/30 dark:bg-amber-950/20 dark:text-amber-400">
                    <div class="flex gap-2">
                        <AlertTriangle class="mt-0.5 h-4 w-4 shrink-0" />
                        <p>Karyawan yang sudah punya slip di periode ini akan dilewati otomatis.</p>
                    </div>
                </div>

                <!-- Generate button -->
                <button
                    :disabled="generating || loading || !preview?.employees?.length"
                    class="flex w-full items-center justify-center gap-2 rounded-2xl bg-brand-600 px-6 py-4 font-semibold text-white shadow-sm transition hover:bg-brand-700 disabled:cursor-not-allowed disabled:opacity-50"
                    @click="generate(null)"
                >
                    <RefreshCw v-if="generating" class="h-5 w-5 animate-spin" />
                    <Zap v-else class="h-5 w-5" />
                    {{ generating ? "Memproses..." : `Generate Semua (${preview?.employees?.length ?? 0})` }}
                </button>
            </div>

            <!-- Right: Employee preview -->
            <div class="lg:col-span-2">
                <div class="overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <div class="flex items-center gap-2 border-b border-slate-100 px-6 py-4 dark:border-slate-800">
                        <Users class="h-4 w-4 text-slate-400" />
                        <h2 class="text-sm font-semibold text-slate-700 dark:text-slate-300">
                            Karyawan belum memiliki slip
                        </h2>
                    </div>

                    <!-- Loading skeleton -->
                    <div v-if="loading">
                        <div v-for="i in 4" :key="i" class="flex items-center gap-4 border-b border-slate-50 px-6 py-4 dark:border-slate-800">
                            <div class="h-9 w-9 animate-pulse rounded-full bg-slate-100 dark:bg-slate-800" />
                            <div class="flex-1 space-y-2">
                                <div class="h-3 w-1/3 animate-pulse rounded bg-slate-100 dark:bg-slate-800" />
                                <div class="h-3 w-1/4 animate-pulse rounded bg-slate-100 dark:bg-slate-800" />
                            </div>
                        </div>
                    </div>

                    <!-- Empty state -->
                    <div v-else-if="!preview?.employees?.length" class="flex flex-col items-center justify-center py-16 text-slate-400">
                        <Users class="mb-3 h-10 w-10 opacity-30" />
                        <p class="font-medium">Semua karyawan aktif sudah punya slip</p>
                        <p class="mt-1 text-xs">untuk periode {{ periodLabel }}.</p>
                    </div>

                    <!-- List -->
                    <div v-else>
                        <div
                            v-for="emp in preview.employees"
                            :key="emp.id"
                            class="flex items-center justify-between border-b border-slate-50 px-6 py-4 last:border-0 dark:border-slate-800"
                        >
                            <div class="flex items-center gap-3">
                                <div class="flex h-9 w-9 items-center justify-center rounded-full bg-brand-100 text-sm font-bold text-brand-700 dark:bg-brand-900/30 dark:text-brand-400">
                                    {{ emp.name?.charAt(0) }}
                                </div>
                                <div>
                                    <p class="font-medium text-slate-800 dark:text-slate-100">{{ emp.name }}</p>
                                    <p class="text-xs text-slate-400">{{ emp.position ?? '—' }} · <span class="font-semibold">{{ formatCurrency(emp.salary) }}</span></p>
                                </div>
                            </div>
                            
                            <button
                                :disabled="generating"
                                class="inline-flex items-center gap-1 rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-700 transition hover:border-brand-300 hover:text-brand-600 disabled:opacity-50 dark:border-slate-700 dark:text-slate-300"
                                @click="generate(emp.id)"
                            >
                                Buat Slip
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
