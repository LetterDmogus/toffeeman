<script setup lang="ts">
import { Head } from "@inertiajs/vue3";
import { ref, computed, watch, onMounted } from "vue";
import {
    Calendar,
    Camera,
    CheckCircle,
    ChevronLeft,
    ChevronRight,
    ClipboardList,
    Clock,
    Download,
    Filter,
    Image,
    LogIn,
    LogOut,
    PenLine,
    Plus,
    RefreshCw,
    Search,
    ShieldAlert,
    ShieldCheck,
    Trash2,
    User,
    XCircle,
} from "lucide-vue-next";

defineOptions({
    layout: {
        breadcrumbs: [
            { title: "Dashboard", href: "/dashboard" },
            { title: "Manajemen Absensi", href: "/attendance" },
        ],
    },
});

const props = defineProps<{
    positions: Array<{ id: number; name: string }>;
}>();

// ── Types ──────────────────────────────────────────────────────────────
interface AttendanceRecord {
    id: number;
    type: "in" | "out";
    status: "pending" | "auto_approved" | "approved" | "rejected";
    notes: string | null;
    photo_url: string | null;
    latitude: number | null;
    longitude: number | null;
    ip_address: string | null;
    verified_at: string | null;
    created_at: string;
    deleted_at: string | null;
    employee: {
        id: number;
        name: string;
        position: string;
        face_photo_url: string | null;
    } | null;
    verifier: { id: number; name: string } | null;
}

// ── Filters ─────────────────────────────────────────────────────────────
const today = new Date().toISOString().slice(0, 10);
const filterType = ref<"day" | "month" | "year" | "range">("day");
const filterDate = ref(today);
const filterMonth = ref(new Date().getMonth() + 1);
const filterYear = ref(new Date().getFullYear());
const filterStartDate = ref("");
const filterEndDate = ref("");
const filterPosition = ref("");
const filterStatus = ref("");
const filterSearch = ref("");
const filterTrash = ref(false);

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
const records = ref<AttendanceRecord[]>([]);
const pagination = ref({ current_page: 1, last_page: 1, total: 0, per_page: 15 });
const isLoading = ref(false);

// ── Photo Preview Modal ────────────────────────────────────────────────
const showPhotoModal = ref(false);
const previewPhotoUrl = ref<string | null>(null);

// ── Verify Modal ──────────────────────────────────────────────────────
const showVerifyModal = ref(false);
const verifyingRecord = ref<AttendanceRecord | null>(null);
const verifyStatus = ref<"approved" | "rejected">("approved");
const verifyNotes = ref("");
const isVerifying = ref(false);

// ── Manual Attendance Modal ────────────────────────────────────────────
const showManualModal = ref(false);
const manualForm = ref({
    employee_id: "",
    type: "in" as "in" | "out",
    notes: "",
    created_at: today + "T" + new Date().toTimeString().slice(0, 5),
});
const isSubmittingManual = ref(false);
const manualError = ref<string | null>(null);

// ── Employee list for manual form dropdown ─────────────────────────────
const employees = ref<Array<{ id: number; name: string; position: string }>>([]);

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

    if (filterPosition.value) params.set("position_id", filterPosition.value);
    if (filterStatus.value) params.set("status", filterStatus.value);
    if (filterSearch.value) params.set("search", filterSearch.value);
    if (filterTrash.value) params.set("trash", "1");

    return params;
}

async function fetchRecords(page = 1) {
    isLoading.value = true;
    try {
        const params = buildParams(false);
        params.set("page", String(page));
        params.set("per_page", "15");

        const res = await fetch(`/api/attendances?${params.toString()}`);
        if (!res.ok) throw new Error("Gagal memuat data");
        const data = await res.json();

        records.value = data.data ?? [];
        pagination.value = {
            current_page: data.current_page,
            last_page: data.last_page,
            total: data.total,
            per_page: data.per_page,
        };
    } catch (e) {
        console.error(e);
    } finally {
        isLoading.value = false;
    }
}

async function fetchEmployees() {
    try {
        const res = await fetch("/api/employees?status=active&per_page=500");
        if (!res.ok) return;
        const data = await res.json();
        employees.value = (data.data ?? data).map((e: any) => ({
            id: e.id,
            name: e.user?.name ?? `Karyawan #${e.id}`,
            position: e.user?.position?.name ?? "—",
        }));
    } catch (e) {
        console.error(e);
    }
}

function handleExport() {
    const params = buildParams(true);
    window.open(`/api/attendances?${params.toString()}`, "_blank");
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
        filterPosition,
        filterStatus,
        filterTrash,
    ],
    () => fetchRecords(1)
);
watch(filterSearch, () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => fetchRecords(1), 350);
});

onMounted(() => {
    fetchRecords();
    fetchEmployees();
});

// ── Helpers ────────────────────────────────────────────────────────────
function formatDateTime(iso: string | null): string {
    if (!iso) return "—";
    return new Date(iso).toLocaleString("id-ID", {
        day: "2-digit",
        month: "short",
        year: "numeric",
        hour: "2-digit",
        minute: "2-digit",
    });
}

function formatTime(iso: string | null): string {
    if (!iso) return "—";
    return new Date(iso).toLocaleTimeString("id-ID", { hour: "2-digit", minute: "2-digit" });
}

const statusConfig: Record<string, { label: string; color: string }> = {
    pending: { label: "Menunggu", color: "bg-amber-100 text-amber-700 border-amber-200" },
    auto_approved: { label: "Otomatis", color: "bg-blue-100 text-blue-700 border-blue-200" },
    approved: { label: "Disetujui", color: "bg-emerald-100 text-emerald-700 border-emerald-200" },
    rejected: { label: "Ditolak", color: "bg-rose-100 text-rose-700 border-rose-200" },
};

const typeConfig: Record<string, { label: string; icon: any; color: string }> = {
    in: { label: "Masuk", icon: LogIn, color: "text-emerald-600" },
    out: { label: "Keluar", icon: LogOut, color: "text-orange-500" },
};

// ── Summary stats ─────────────────────────────────────────────────────
const summaryStats = computed(() => ({
    total: pagination.value.total,
    approved: records.value.filter((r) => r.status === "approved" || r.status === "auto_approved").length,
    pending: records.value.filter((r) => r.status === "pending").length,
    rejected: records.value.filter((r) => r.status === "rejected").length,
    clockIns: records.value.filter((r) => r.type === "in").length,
    clockOuts: records.value.filter((r) => r.type === "out").length,
}));

// ── Photo preview ─────────────────────────────────────────────────────
function openPhoto(url: string | null) {
    if (!url) return;
    previewPhotoUrl.value = url;
    showPhotoModal.value = true;
}

// ── Verify actions ─────────────────────────────────────────────────────
function openVerify(record: AttendanceRecord, status: "approved" | "rejected") {
    verifyingRecord.value = record;
    verifyStatus.value = status;
    verifyNotes.value = record.notes ?? "";
    showVerifyModal.value = true;
}

async function submitVerify() {
    if (!verifyingRecord.value) return;
    isVerifying.value = true;
    try {
        const res = await fetch(`/api/attendances/${verifyingRecord.value.id}/verify`, {
            method: "PATCH",
            headers: { "Content-Type": "application/json", "X-XSRF-TOKEN": getCsrf() },
            body: JSON.stringify({ status: verifyStatus.value, notes: verifyNotes.value || null }),
        });
        if (!res.ok) throw new Error("Gagal memverifikasi");
        showVerifyModal.value = false;
        fetchRecords(pagination.value.current_page);
    } catch (e) {
        alert("Gagal memverifikasi. Silakan coba lagi.");
    } finally {
        isVerifying.value = false;
    }
}

// ── Manual attendance ──────────────────────────────────────────────────
function openManualModal() {
    manualForm.value = {
        employee_id: "",
        type: "in",
        notes: "",
        created_at: today + "T" + new Date().toTimeString().slice(0, 5),
    };
    manualError.value = null;
    showManualModal.value = true;
}

async function submitManual() {
    if (!manualForm.value.employee_id) {
        manualError.value = "Pilih karyawan terlebih dahulu.";
        return;
    }
    isSubmittingManual.value = true;
    manualError.value = null;
    try {
        const res = await fetch("/api/attendances", {
            method: "POST",
            headers: { "Content-Type": "application/json", "X-XSRF-TOKEN": getCsrf() },
            body: JSON.stringify({
                employee_id: Number(manualForm.value.employee_id),
                type: manualForm.value.type,
                notes: manualForm.value.notes || null,
                created_at: manualForm.value.created_at || null,
            }),
        });
        if (!res.ok) {
            const err = await res.json();
            throw new Error(err.message ?? "Gagal membuat absensi");
        }
        showManualModal.value = false;
        fetchRecords(pagination.value.current_page);
    } catch (e: any) {
        manualError.value = e.message ?? "Terjadi kesalahan.";
    } finally {
        isSubmittingManual.value = false;
    }
}

// ── Delete ─────────────────────────────────────────────────────────────
async function deleteRecord(record: AttendanceRecord) {
    if (!confirm(`Hapus absensi ${record.employee?.name ?? "ini"}?`)) return;
    await fetch(`/api/attendances/${record.id}`, {
        method: "DELETE",
        headers: { "X-XSRF-TOKEN": getCsrf() },
    });
    fetchRecords(pagination.value.current_page);
}

// ── CSRF helper ────────────────────────────────────────────────────────
function getCsrf(): string {
    return decodeURIComponent(
        document.cookie
            .split("; ")
            .find((r) => r.startsWith("XSRF-TOKEN="))
            ?.split("=")[1] ?? ""
    );
}
</script>

<template>
    <Head title="Manajemen Absensi" />

    <div class="p-6 space-y-6">
        <!-- Header -->
        <div class="flex items-center justify-between flex-wrap gap-3">
            <div>
                <h1 class="text-2xl font-bold text-slate-900 tracking-tight">Manajemen Absensi</h1>
                <p class="text-sm text-slate-500 mt-0.5">Pantau, verifikasi, dan kelola absensi karyawan</p>
            </div>
            <button
                @click="openManualModal"
                class="flex items-center gap-2 px-4 py-2.5 bg-brand-600 hover:bg-brand-700 text-white text-sm font-semibold rounded-xl shadow-sm transition-all duration-200 hover:-translate-y-0.5 cursor-pointer"
            >
                <Plus class="h-4 w-4" />
                Absensi Manual
            </button>
        </div>

        <!-- Stats row -->
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
            <div class="bg-white border border-slate-200 rounded-xl p-4 text-center shadow-xs">
                <p class="text-2xl font-bold text-emerald-600">{{ summaryStats.clockIns }}</p>
                <p class="text-xs text-slate-500 font-medium mt-0.5 flex items-center justify-center gap-1">
                    <LogIn class="h-3 w-3 text-emerald-500" /> Absen Masuk
                </p>
            </div>
            <div class="bg-white border border-slate-200 rounded-xl p-4 text-center shadow-xs">
                <p class="text-2xl font-bold text-orange-500">{{ summaryStats.clockOuts }}</p>
                <p class="text-xs text-slate-500 font-medium mt-0.5 flex items-center justify-center gap-1">
                    <LogOut class="h-3 w-3 text-orange-400" /> Absen Keluar
                </p>
            </div>
            <div class="bg-white border border-slate-200 rounded-xl p-4 text-center shadow-xs">
                <p class="text-2xl font-bold text-amber-600">{{ summaryStats.pending }}</p>
                <p class="text-xs text-slate-500 font-medium mt-0.5">Menunggu Verifikasi</p>
            </div>
            <div class="bg-white border border-slate-200 rounded-xl p-4 text-center shadow-xs">
                <p class="text-2xl font-bold text-blue-600">{{ summaryStats.approved }}</p>
                <p class="text-xs text-slate-500 font-medium mt-0.5">Disetujui</p>
            </div>
            <div class="bg-white border border-slate-200 rounded-xl p-4 text-center shadow-xs">
                <p class="text-2xl font-bold text-rose-600">{{ summaryStats.rejected }}</p>
                <p class="text-xs text-slate-500 font-medium mt-0.5">Ditolak</p>
            </div>
            <div class="bg-white border border-slate-200 rounded-xl p-4 text-center shadow-xs">
                <p class="text-2xl font-bold text-slate-700">{{ pagination.total }}</p>
                <p class="text-xs text-slate-500 font-medium mt-0.5">Total Records</p>
            </div>
        </div>

        <!-- Filters -->
        <div class="bg-white border border-slate-200 rounded-2xl p-4 shadow-xs flex flex-col gap-4">
            <div class="flex flex-wrap gap-4 items-end">
                <!-- Period Type Selection -->
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

                <!-- Day Filter -->
                <div v-if="filterType === 'day'" class="flex flex-col gap-1">
                    <label class="text-xs font-semibold text-slate-600">Pilih Tanggal</label>
                    <input
                        v-model="filterDate"
                        type="date"
                        class="border border-slate-200 rounded-lg px-3 py-1.5 text-sm text-slate-700 bg-white shadow-xs focus:ring-2 focus:ring-brand-400 focus:outline-none"
                    />
                </div>

                <!-- Month Filter -->
                <div v-if="filterType === 'month'" class="flex flex-col gap-1">
                    <label class="text-xs font-semibold text-slate-600">Pilih Bulan</label>
                    <select
                        v-model="filterMonth"
                        class="border border-slate-200 rounded-lg px-3 py-1.5 text-sm text-slate-700 bg-white shadow-xs focus:ring-2 focus:ring-brand-400 focus:outline-none"
                    >
                        <option v-for="m in months" :key="m.value" :value="m.value">{{ m.label }}</option>
                    </select>
                </div>

                <!-- Year Filter (for month & year view) -->
                <div v-if="filterType === 'month' || filterType === 'year'" class="flex flex-col gap-1">
                    <label class="text-xs font-semibold text-slate-600">Pilih Tahun</label>
                    <select
                        v-model="filterYear"
                        class="border border-slate-200 rounded-lg px-3 py-1.5 text-sm text-slate-700 bg-white shadow-xs focus:ring-2 focus:ring-brand-400 focus:outline-none"
                    >
                        <option v-for="y in years" :key="y" :value="y">{{ y }}</option>
                    </select>
                </div>

                <!-- Date Range Filter -->
                <div v-if="filterType === 'range'" class="flex flex-wrap gap-2 items-end">
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

                <!-- Position -->
                <div class="flex flex-col gap-1">
                    <label class="text-xs font-semibold text-slate-600 flex items-center gap-1">
                        <Filter class="h-3 w-3" /> Jabatan
                    </label>
                    <select
                        v-model="filterPosition"
                        class="border border-slate-200 rounded-lg px-3 py-1.5 text-sm text-slate-700 bg-white shadow-xs focus:ring-2 focus:ring-brand-400 focus:outline-none"
                    >
                        <option value="">Semua Jabatan</option>
                        <option v-for="pos in positions" :key="pos.id" :value="String(pos.id)">{{ pos.name }}</option>
                    </select>
                </div>

                <!-- Status -->
                <div class="flex flex-col gap-1">
                    <label class="text-xs font-semibold text-slate-600 flex items-center gap-1">
                        <ShieldCheck class="h-3 w-3" /> Status
                    </label>
                    <select
                        v-model="filterStatus"
                        class="border border-slate-200 rounded-lg px-3 py-1.5 text-sm text-slate-700 bg-white shadow-xs focus:ring-2 focus:ring-brand-400 focus:outline-none"
                    >
                        <option value="">Semua Status</option>
                        <option value="pending">Menunggu</option>
                        <option value="auto_approved">Otomatis Disetujui</option>
                        <option value="approved">Disetujui</option>
                        <option value="rejected">Ditolak</option>
                    </select>
                </div>

                <!-- Search -->
                <div class="flex flex-col gap-1 flex-1 min-w-48">
                    <label class="text-xs font-semibold text-slate-600 flex items-center gap-1">
                        <Search class="h-3 w-3" /> Cari Karyawan
                    </label>
                    <div class="relative">
                        <Search class="absolute left-2.5 top-1/2 -translate-y-1/2 h-3.5 w-3.5 text-slate-400" />
                        <input
                            v-model="filterSearch"
                            type="text"
                            placeholder="Cari nama karyawan..."
                            class="w-full border border-slate-200 rounded-lg pl-8 pr-3 py-1.5 text-sm text-slate-700 bg-white shadow-xs focus:ring-2 focus:ring-brand-400 focus:outline-none"
                        />
                    </div>
                </div>

                <!-- Actions Group -->
                <div class="flex items-center gap-2 pb-0.5">
                    <!-- Trash toggle -->
                    <label class="flex items-center gap-2 text-sm text-slate-600 cursor-pointer select-none mr-2">
                        <input v-model="filterTrash" type="checkbox" class="w-4 h-4 accent-brand-600 rounded" />
                        Arsip
                    </label>
                    <!-- Refresh -->
                    <button
                        @click="fetchRecords(pagination.current_page)"
                        class="flex items-center gap-1.5 px-3 py-1.5 text-sm text-slate-600 bg-slate-100 hover:bg-slate-200 rounded-lg transition cursor-pointer"
                    >
                        <RefreshCw class="h-3.5 w-3.5" :class="isLoading ? 'animate-spin' : ''" />
                        Refresh
                    </button>
                    <!-- Export CSV -->
                    <button
                        @click="handleExport"
                        class="flex items-center gap-1.5 px-3 py-1.5 text-sm text-emerald-700 bg-emerald-50 hover:bg-emerald-100 border border-emerald-200 rounded-lg transition cursor-pointer"
                        title="Ekspor rekap absensi ke file CSV"
                    >
                        <Download class="h-3.5 w-3.5 text-emerald-600" />
                        Ekspor CSV
                    </button>
                </div>
            </div>
        </div>

        <!-- Table -->
        <div class="bg-white border border-slate-200 rounded-2xl shadow-xs overflow-hidden">
            <div v-if="isLoading" class="flex items-center justify-center py-20 text-slate-400 gap-2">
                <RefreshCw class="h-5 w-5 animate-spin" />
                <span class="text-sm">Memuat data...</span>
            </div>

            <div v-else-if="records.length === 0" class="flex flex-col items-center justify-center py-20 text-slate-400 gap-3">
                <ClipboardList class="h-12 w-12 opacity-30" />
                <p class="text-sm font-medium">Tidak ada data absensi untuk filter ini</p>
            </div>

            <div v-else class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-slate-100 bg-slate-50 text-xs font-semibold text-slate-500 uppercase tracking-wide">
                            <th class="px-4 py-3 text-left">Karyawan</th>
                            <th class="px-4 py-3 text-left">Tipe</th>
                            <th class="px-4 py-3 text-left">Waktu</th>
                            <th class="px-4 py-3 text-left">Status</th>
                            <th class="px-4 py-3 text-left">Foto</th>
                            <th class="px-4 py-3 text-left">Keterangan</th>
                            <th class="px-4 py-3 text-left">Verifikator</th>
                            <th class="px-4 py-3 text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        <tr
                            v-for="record in records"
                            :key="record.id"
                            class="hover:bg-slate-50/80 transition-colors duration-150"
                            :class="record.deleted_at ? 'opacity-50 bg-slate-50' : ''"
                        >
                            <!-- Employee -->
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-3">
                                    <div class="h-9 w-9 rounded-full bg-slate-100 overflow-hidden border border-slate-200 shrink-0 flex items-center justify-center">
                                        <img
                                            v-if="record.employee?.face_photo_url"
                                            :src="record.employee.face_photo_url"
                                            class="h-full w-full object-cover"
                                            :alt="record.employee?.name"
                                        />
                                        <User v-else class="h-4 w-4 text-slate-400" />
                                    </div>
                                    <div>
                                        <p class="font-semibold text-slate-800 leading-tight text-sm">{{ record.employee?.name ?? "—" }}</p>
                                        <p class="text-xs text-slate-400">{{ record.employee?.position ?? "—" }}</p>
                                    </div>
                                </div>
                            </td>

                            <!-- Type -->
                            <td class="px-4 py-3">
                                <span
                                    class="inline-flex items-center gap-1.5 text-xs font-semibold"
                                    :class="typeConfig[record.type]?.color ?? 'text-slate-500'"
                                >
                                    <component :is="typeConfig[record.type]?.icon" class="h-3.5 w-3.5" />
                                    {{ typeConfig[record.type]?.label ?? record.type }}
                                </span>
                            </td>

                            <!-- Time -->
                            <td class="px-4 py-3">
                                <p class="font-mono text-slate-800 font-bold text-sm leading-tight">{{ formatTime(record.created_at) }}</p>
                                <p class="text-xs text-slate-400">{{ new Date(record.created_at).toLocaleDateString("id-ID", { day: "2-digit", month: "short", year: "numeric" }) }}</p>
                            </td>

                            <!-- Status -->
                            <td class="px-4 py-3">
                                <div class="flex flex-col gap-1 items-start">
                                    <span
                                        class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold border"
                                        :class="statusConfig[record.status]?.color ?? 'bg-slate-100 text-slate-700'"
                                    >
                                        {{ statusConfig[record.status]?.label ?? record.status }}
                                    </span>
                                    <span
                                        v-if="record.status === 'pending'"
                                        class="inline-flex items-center gap-1 text-[10px] font-medium text-amber-600 bg-amber-50 px-1.5 py-0.5 rounded border border-amber-100"
                                    >
                                        <ShieldAlert class="h-3 w-3 text-amber-500 animate-pulse" />
                                        Perlu info verifikator
                                    </span>
                                </div>
                            </td>

                            <!-- Photo -->
                            <td class="px-4 py-3">
                                <button
                                    v-if="record.photo_url"
                                    @click="openPhoto(record.photo_url)"
                                    class="group relative h-10 w-10 rounded-lg overflow-hidden border border-slate-200 hover:border-brand-400 transition cursor-pointer"
                                    title="Lihat foto absensi"
                                >
                                    <img :src="record.photo_url" class="h-full w-full object-cover" alt="Foto absensi" />
                                    <div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition flex items-center justify-center">
                                        <Image class="h-3.5 w-3.5 text-white" />
                                    </div>
                                </button>
                                <span v-else class="text-slate-300 text-xs flex items-center gap-1">
                                    <Camera class="h-3.5 w-3.5" /> —
                                </span>
                            </td>

                            <!-- Notes -->
                            <td class="px-4 py-3 max-w-44">
                                <p class="text-xs text-slate-500 line-clamp-2 leading-relaxed">{{ record.notes ?? "—" }}</p>
                            </td>

                            <!-- Verifier -->
                            <td class="px-4 py-3">
                                <div v-if="record.verifier" class="text-xs">
                                    <p class="text-slate-700 font-medium">{{ record.verifier.name }}</p>
                                    <p class="text-slate-400">{{ formatTime(record.verified_at) }}</p>
                                </div>
                                <span v-else class="text-slate-300 text-xs">—</span>
                            </td>

                            <!-- Actions -->
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-center gap-1.5">
                                    <button
                                        v-if="record.status === 'pending' && !record.deleted_at"
                                        @click="openVerify(record, 'approved')"
                                        title="Setujui"
                                        class="p-1.5 rounded-lg bg-emerald-50 hover:bg-emerald-100 text-emerald-600 transition cursor-pointer"
                                    >
                                        <CheckCircle class="h-4 w-4" />
                                    </button>
                                    <button
                                        v-if="record.status === 'pending' && !record.deleted_at"
                                        @click="openVerify(record, 'rejected')"
                                        title="Tolak"
                                        class="p-1.5 rounded-lg bg-rose-50 hover:bg-rose-100 text-rose-500 transition cursor-pointer"
                                    >
                                        <XCircle class="h-4 w-4" />
                                    </button>
                                    <button
                                        v-if="!record.deleted_at && record.status !== 'pending'"
                                        @click="openVerify(record, record.status === 'rejected' ? 'rejected' : 'approved')"
                                        title="Edit Keterangan"
                                        class="p-1.5 rounded-lg bg-slate-50 hover:bg-slate-100 text-slate-500 transition cursor-pointer"
                                    >
                                        <PenLine class="h-4 w-4" />
                                    </button>
                                    <button
                                        v-if="!record.deleted_at"
                                        @click="deleteRecord(record)"
                                        title="Hapus"
                                        class="p-1.5 rounded-lg bg-rose-50 hover:bg-rose-100 text-rose-400 transition cursor-pointer"
                                    >
                                        <Trash2 class="h-4 w-4" />
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div v-if="!isLoading && records.length > 0" class="flex items-center justify-between px-4 py-3 border-t border-slate-100 bg-slate-50">
                <p class="text-xs text-slate-500">
                    Halaman <b>{{ pagination.current_page }}</b> dari <b>{{ pagination.last_page }}</b>
                    &nbsp;·&nbsp; <b>{{ pagination.total }}</b> total record
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

    <!-- Photo Preview Modal -->
    <Teleport to="body">
        <div
            v-if="showPhotoModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 backdrop-blur-sm p-4"
            @click.self="showPhotoModal = false"
        >
            <div class="bg-white rounded-2xl shadow-2xl max-w-lg w-full overflow-hidden animate-in zoom-in-95 duration-200">
                <div class="flex items-center justify-between px-5 py-4 border-b border-slate-100">
                    <h3 class="font-semibold text-slate-800 flex items-center gap-2">
                        <Camera class="h-4 w-4 text-slate-500" /> Foto Absensi
                    </h3>
                    <button @click="showPhotoModal = false" class="text-slate-400 hover:text-slate-600 transition cursor-pointer">
                        <XCircle class="h-5 w-5" />
                    </button>
                </div>
                <div class="p-4">
                    <img :src="previewPhotoUrl ?? ''" class="w-full rounded-xl object-contain max-h-[70vh]" alt="Foto Absensi" />
                </div>
            </div>
        </div>
    </Teleport>

    <!-- Verify Modal -->
    <Teleport to="body">
        <div
            v-if="showVerifyModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm p-4"
            @click.self="showVerifyModal = false"
        >
            <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full animate-in zoom-in-95 duration-200">
                <div class="flex items-center justify-between px-5 py-4 border-b border-slate-100">
                    <h3 class="font-semibold text-slate-800 flex items-center gap-2">
                        <component
                            :is="verifyStatus === 'approved' ? CheckCircle : XCircle"
                            class="h-4 w-4"
                            :class="verifyStatus === 'approved' ? 'text-emerald-500' : 'text-rose-500'"
                        />
                        {{ verifyStatus === "approved" ? "Verifikasi Absensi" : "Tolak Absensi" }}
                    </h3>
                    <button @click="showVerifyModal = false" class="text-slate-400 hover:text-slate-600 cursor-pointer">
                        <XCircle class="h-5 w-5" />
                    </button>
                </div>

                <div class="p-5 space-y-4">
                    <!-- Employee info chip -->
                    <div class="flex items-center gap-3 p-3 bg-slate-50 rounded-xl border border-slate-100">
                        <div class="h-10 w-10 rounded-full bg-slate-200 overflow-hidden border border-slate-300 shrink-0 flex items-center justify-center">
                            <img
                                v-if="verifyingRecord?.employee?.face_photo_url"
                                :src="verifyingRecord.employee.face_photo_url"
                                class="h-full w-full object-cover"
                                :alt="verifyingRecord.employee.name"
                            />
                            <User v-else class="h-5 w-5 text-slate-400" />
                        </div>
                        <div>
                            <p class="font-semibold text-slate-800 text-sm">{{ verifyingRecord?.employee?.name }}</p>
                            <p class="text-xs text-slate-400">
                                {{ verifyingRecord?.employee?.position }} ·
                                {{ typeConfig[verifyingRecord?.type ?? "in"]?.label }} ·
                                {{ formatDateTime(verifyingRecord?.created_at ?? null) }}
                            </p>
                        </div>
                    </div>

                    <!-- Status toggle -->
                    <div class="flex gap-2">
                        <button
                            @click="verifyStatus = 'approved'"
                            class="flex-1 py-2 text-sm font-semibold rounded-xl border-2 transition cursor-pointer flex items-center justify-center gap-1.5"
                            :class="verifyStatus === 'approved' ? 'border-emerald-500 bg-emerald-50 text-emerald-700' : 'border-slate-200 text-slate-500 hover:border-slate-300'"
                        >
                            <CheckCircle class="h-4 w-4" /> Setujui
                        </button>
                        <button
                            @click="verifyStatus = 'rejected'"
                            class="flex-1 py-2 text-sm font-semibold rounded-xl border-2 transition cursor-pointer flex items-center justify-center gap-1.5"
                            :class="verifyStatus === 'rejected' ? 'border-rose-500 bg-rose-50 text-rose-700' : 'border-slate-200 text-slate-500 hover:border-slate-300'"
                        >
                            <XCircle class="h-4 w-4" /> Tolak
                        </button>
                    </div>

                    <!-- Notes -->
                    <div>
                        <label class="text-xs font-semibold text-slate-600 mb-1.5 flex items-center gap-1 block">
                            <PenLine class="h-3 w-3" /> Keterangan (dari manager)
                        </label>
                        <textarea
                            v-model="verifyNotes"
                            rows="3"
                            placeholder="Tambahkan catatan untuk karyawan..."
                            class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm text-slate-700 placeholder-slate-400 focus:ring-2 focus:ring-brand-400 focus:outline-none resize-none"
                        ></textarea>
                    </div>

                    <div class="flex gap-2 pt-1">
                        <button
                            @click="showVerifyModal = false"
                            class="flex-1 py-2.5 text-sm font-medium rounded-xl border border-slate-200 text-slate-600 hover:bg-slate-50 transition cursor-pointer"
                        >
                            Batal
                        </button>
                        <button
                            @click="submitVerify"
                            :disabled="isVerifying"
                            class="flex-1 py-2.5 text-sm font-semibold rounded-xl text-white transition cursor-pointer disabled:opacity-70 flex items-center justify-center gap-2"
                            :class="verifyStatus === 'approved' ? 'bg-emerald-600 hover:bg-emerald-700' : 'bg-rose-600 hover:bg-rose-700'"
                        >
                            <RefreshCw v-if="isVerifying" class="h-4 w-4 animate-spin" />
                            {{ verifyStatus === "approved" ? "Setujui" : "Tolak" }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </Teleport>

    <!-- Manual Attendance Modal -->
    <Teleport to="body">
        <div
            v-if="showManualModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm p-4"
            @click.self="showManualModal = false"
        >
            <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full animate-in zoom-in-95 duration-200">
                <div class="flex items-center justify-between px-5 py-4 border-b border-slate-100">
                    <h3 class="font-semibold text-slate-800 flex items-center gap-2">
                        <ClipboardList class="h-4 w-4 text-brand-500" /> Input Absensi Manual
                    </h3>
                    <button @click="showManualModal = false" class="text-slate-400 hover:text-slate-600 cursor-pointer">
                        <XCircle class="h-5 w-5" />
                    </button>
                </div>

                <div class="p-5 space-y-4">
                    <!-- Error -->
                    <div v-if="manualError" class="p-3 bg-rose-50 border border-rose-200 rounded-xl text-sm text-rose-700 flex items-center gap-2">
                        <ShieldAlert class="h-4 w-4 shrink-0" /> {{ manualError }}
                    </div>

                    <!-- Employee select -->
                    <div>
                        <label class="text-xs font-semibold text-slate-600 mb-1.5 flex items-center gap-1 block">
                            <User class="h-3 w-3" /> Karyawan <span class="text-rose-500 ml-0.5">*</span>
                        </label>
                        <select
                            v-model="manualForm.employee_id"
                            class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm text-slate-700 focus:ring-2 focus:ring-brand-400 focus:outline-none bg-white"
                        >
                            <option value="">— Pilih Karyawan —</option>
                            <option v-for="emp in employees" :key="emp.id" :value="String(emp.id)">
                                {{ emp.name }} ({{ emp.position }})
                            </option>
                        </select>
                    </div>

                    <!-- Type -->
                    <div>
                        <label class="text-xs font-semibold text-slate-600 mb-1.5 flex items-center gap-1 block">
                            <LogIn class="h-3 w-3" /> Tipe Absensi <span class="text-rose-500 ml-0.5">*</span>
                        </label>
                        <div class="grid grid-cols-2 gap-2">
                            <button
                                @click="manualForm.type = 'in'"
                                class="py-2.5 text-sm font-semibold rounded-xl border-2 transition cursor-pointer flex items-center justify-center gap-2"
                                :class="manualForm.type === 'in' ? 'border-emerald-500 bg-emerald-50 text-emerald-700' : 'border-slate-200 text-slate-500 hover:border-slate-300'"
                            >
                                <LogIn class="h-4 w-4" /> Masuk
                            </button>
                            <button
                                @click="manualForm.type = 'out'"
                                class="py-2.5 text-sm font-semibold rounded-xl border-2 transition cursor-pointer flex items-center justify-center gap-2"
                                :class="manualForm.type === 'out' ? 'border-orange-500 bg-orange-50 text-orange-700' : 'border-slate-200 text-slate-500 hover:border-slate-300'"
                            >
                                <LogOut class="h-4 w-4" /> Keluar
                            </button>
                        </div>
                    </div>

                    <!-- Datetime -->
                    <div>
                        <label class="text-xs font-semibold text-slate-600 mb-1.5 flex items-center gap-1 block">
                            <Clock class="h-3 w-3" /> Tanggal &amp; Waktu
                        </label>
                        <input
                            v-model="manualForm.created_at"
                            type="datetime-local"
                            class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm text-slate-700 focus:ring-2 focus:ring-brand-400 focus:outline-none"
                        />
                    </div>

                    <!-- Notes -->
                    <div>
                        <label class="text-xs font-semibold text-slate-600 mb-1.5 flex items-center gap-1 block">
                            <PenLine class="h-3 w-3" /> Keterangan
                        </label>
                        <textarea
                            v-model="manualForm.notes"
                            rows="3"
                            placeholder="Alasan absensi manual, misal: lupa scan, kamera error, dsb..."
                            class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm text-slate-700 placeholder-slate-400 focus:ring-2 focus:ring-brand-400 focus:outline-none resize-none"
                        ></textarea>
                    </div>

                    <div class="flex gap-2 pt-1">
                        <button
                            @click="showManualModal = false"
                            class="flex-1 py-2.5 text-sm font-medium rounded-xl border border-slate-200 text-slate-600 hover:bg-slate-50 transition cursor-pointer"
                        >
                            Batal
                        </button>
                        <button
                            @click="submitManual"
                            :disabled="isSubmittingManual"
                            class="flex-1 py-2.5 text-sm font-semibold rounded-xl bg-brand-600 hover:bg-brand-700 text-white transition cursor-pointer disabled:opacity-70 flex items-center justify-center gap-2"
                        >
                            <RefreshCw v-if="isSubmittingManual" class="h-4 w-4 animate-spin" />
                            Simpan Absensi
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </Teleport>
</template>
