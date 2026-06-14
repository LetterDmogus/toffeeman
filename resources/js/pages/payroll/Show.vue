<script setup lang="ts">
import { Head, router, useForm, usePage } from "@inertiajs/vue3";
import {
    BadgeCheck,
    Banknote,
    Calendar,
    CheckCircle2,
    ChevronLeft,
    Clock,
    Download,
    FileText,
    Minus,
    Plus,
    ToggleLeft,
    ToggleRight,
    Trash2,
    User,
} from "lucide-vue-next";
import { computed } from "vue";

const props = defineProps<{
    payroll: {
        id: number;
        period_month: number;
        period_year: number;
        base_salary: string;
        working_days: number;
        present_days: number;
        absent_days: number;
        apply_absence_deduction: boolean;
        deduction_absence: string;
        allowance: string;
        allowance_notes: string | null;
        deduction_other: string;
        deduction_notes: string | null;
        bonus: string;
        bonus_notes: string | null;
        shift_night_days: number;
        shift_night_rate: string;
        overtime_hours: number;
        overtime_rate: string;
        gross_salary: string;
        net_salary: string;
        status: "draft" | "approved" | "paid";
        paid_at: string | null;
        notes: string | null;
        employee: {
            id: number;
            name: string;
            salary: string;
            position: { name: string } | null;
        };
        approver: { name: string } | null;
        transaction: { transaction_number: string } | null;
    };
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: "Dashboard", href: "/dashboard" },
            { title: "Penggajian", href: route("payroll.index") },
            { title: "Detail Slip", href: "#" },
        ],
    },
});

// ── Auth ───────────────────────────────────────────────────────────────────
const page = usePage();
const user = computed(() => page.props.auth.user);
const canManage = computed(
    () =>
        user.value?.role === "superadmin" ||
        (page.props.auth as any).permissions?.includes("payroll-manage"),
);
const isDraft = computed(() => props.payroll.status === "draft");
const isApproved = computed(() => props.payroll.status === "approved");
const isPaid = computed(() => props.payroll.status === "paid");

// ── Helpers ─────────────────────────────────────────────────────────────────
const months = [
    "Januari", "Februari", "Maret", "April", "Mei", "Juni",
    "Juli", "Agustus", "September", "Oktober", "November", "Desember",
];
const periodLabel = computed(
    () => `${months[props.payroll.period_month - 1]} ${props.payroll.period_year}`,
);
const fmt = (val: string | number) =>
    new Intl.NumberFormat("id-ID", { style: "currency", currency: "IDR", maximumFractionDigits: 0 }).format(Number(val) ?? 0);

const statusConfig: Record<string, { label: string; classes: string }> = {
    draft: { label: "Draft", classes: "bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400" },
    approved: { label: "Disetujui", classes: "bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400" },
    paid: { label: "Dibayar", classes: "bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400" },
};

// ── Live preview (mirrors PHP Payroll::recalculate()) ────────────────────────
const previewBaseSalary = computed(() => Number(props.payroll.base_salary));

const previewDeductionAbsence = computed(() => {
    if (!form.apply_absence_deduction || props.payroll.working_days <= 0) return 0;
    return Math.round((previewBaseSalary.value / props.payroll.working_days) * props.payroll.absent_days * 100) / 100;
});

const previewShiftNightPay = computed(() =>
    Math.round(form.shift_night_days * form.shift_night_rate * 100) / 100,
);

const previewOvertimePay = computed(() =>
    Math.round(form.overtime_hours * form.overtime_rate * 100) / 100,
);

const previewGross = computed(() =>
    previewBaseSalary.value + form.allowance + form.bonus + previewShiftNightPay.value + previewOvertimePay.value,
);

const previewNet = computed(() =>
    Math.max(0, previewGross.value - previewDeductionAbsence.value - form.deduction_other),
);

/** True when any live value differs from the saved prop */
const isPreviewDirty = computed(() =>
    isDraft.value && (
        form.allowance !== Number(props.payroll.allowance) ||
        form.bonus !== Number(props.payroll.bonus) ||
        form.shift_night_days !== props.payroll.shift_night_days ||
        form.shift_night_rate !== Number(props.payroll.shift_night_rate) ||
        form.overtime_hours !== props.payroll.overtime_hours ||
        form.overtime_rate !== Number(props.payroll.overtime_rate) ||
        form.deduction_other !== Number(props.payroll.deduction_other) ||
        form.apply_absence_deduction !== props.payroll.apply_absence_deduction
    ),
);

// ── Edit form (draft only) ───────────────────────────────────────────────────
const form = useForm({
    apply_absence_deduction: props.payroll.apply_absence_deduction,
    allowance: Number(props.payroll.allowance),
    allowance_notes: props.payroll.allowance_notes ?? "",
    deduction_other: Number(props.payroll.deduction_other),
    deduction_notes: props.payroll.deduction_notes ?? "",
    bonus: Number(props.payroll.bonus),
    bonus_notes: props.payroll.bonus_notes ?? "",
    shift_night_days: props.payroll.shift_night_days,
    shift_night_rate: Number(props.payroll.shift_night_rate),
    overtime_hours: props.payroll.overtime_hours,
    overtime_rate: Number(props.payroll.overtime_rate),
    notes: props.payroll.notes ?? "",
});

function save() {
    form.patch(route("payroll.update", props.payroll.id), {
        preserveScroll: true,
    });
}

function approve() {
    if (!confirm("Setujui slip gaji ini?")) return;
    router.patch(route("payroll.approve", props.payroll.id), {}, { preserveScroll: true });
}

function pay() {
    if (!confirm("Tandai slip gaji ini sebagai DIBAYAR? Transaksi pengeluaran akan dibuat otomatis.")) return;
    router.patch(route("payroll.pay", props.payroll.id), {}, { preserveScroll: true });
}

function deleteDraft() {
    if (!confirm("Hapus slip gaji ini?")) return;
    router.delete(route("payroll.destroy", props.payroll.id));
}
</script>

<template>
    <Head :title="`Slip Gaji — ${props.payroll.employee?.name}`" />

    <div class="flex h-full flex-1 flex-col gap-6 p-6">
        <!-- Back + header -->
        <div class="flex items-center gap-3">
            <a
                :href="route('payroll.index')"
                class="flex h-9 w-9 items-center justify-center rounded-xl border border-slate-200 text-slate-500 transition hover:border-brand-300 hover:text-brand-600 dark:border-slate-700"
                @click.prevent="router.visit(route('payroll.index'))"
            >
                <ChevronLeft class="h-4 w-4" />
            </a>
            <div class="flex-1">
                <div class="flex flex-wrap items-center gap-2">
                    <h1 class="text-2xl font-bold text-slate-800 dark:text-slate-100">
                        Slip Gaji — {{ payroll.employee?.name }}
                    </h1>
                    <span
                        class="inline-flex rounded-full px-3 py-1 text-xs font-semibold"
                        :class="statusConfig[payroll.status]?.classes"
                    >
                        {{ statusConfig[payroll.status]?.label }}
                    </span>
                </div>
                <p class="mt-0.5 text-sm text-slate-400">
                    {{ periodLabel }} · {{ payroll.employee?.position?.name ?? '—' }}
                </p>
            </div>

            <!-- Actions -->
            <div class="flex items-center gap-2">
                <a
                    :href="route('payroll.export', props.payroll.id)"
                    target="_blank"
                    class="flex items-center gap-1.5 rounded-xl border border-slate-200 px-3 py-2 text-sm font-medium text-slate-600 transition hover:border-brand-300 hover:text-brand-600 dark:border-slate-700 dark:text-slate-300"
                >
                    <Download class="h-4 w-4" />
                    Export
                </a>
                <button
                    v-if="canManage && isDraft"
                    class="flex items-center gap-1.5 rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-sm font-medium text-amber-700 transition hover:bg-amber-100 dark:border-amber-900/40 dark:bg-amber-950/20 dark:text-amber-400"
                    @click="approve"
                >
                    <BadgeCheck class="h-4 w-4" />
                    Approve
                </button>
                <button
                    v-if="canManage && isApproved"
                    class="flex items-center gap-1.5 rounded-xl bg-emerald-600 px-3 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-700"
                    @click="pay"
                >
                    <Banknote class="h-4 w-4" />
                    Bayar
                </button>
                <button
                    v-if="canManage && isDraft"
                    class="flex h-9 w-9 items-center justify-center rounded-xl border border-red-200 text-red-500 transition hover:bg-red-50 dark:border-red-900/40"
                    @click="deleteDraft"
                >
                    <Trash2 class="h-4 w-4" />
                </button>
            </div>
        </div>

        <div class="grid gap-6 lg:grid-cols-3">
            <!-- Left: Slip summary (live preview) -->
            <div class="flex flex-col gap-4 lg:col-span-2">
                <!-- Salary breakdown card -->
                <div
                    class="overflow-hidden rounded-2xl border bg-white shadow-sm dark:bg-slate-900 transition-all duration-300"
                    :class="isPreviewDirty
                        ? 'border-brand-300 dark:border-brand-700 ring-2 ring-brand-100 dark:ring-brand-900/30'
                        : 'border-slate-100 dark:border-slate-800'"
                >
                    <div class="flex items-center justify-between border-b px-6 py-4 transition-colors"
                        :class="isPreviewDirty ? 'border-brand-100 dark:border-brand-900/40' : 'border-slate-100 dark:border-slate-800'"
                    >
                        <h2 class="flex items-center gap-2 text-sm font-semibold text-slate-600 dark:text-slate-400">
                            <FileText class="h-4 w-4" />
                            Rincian Slip Gaji
                        </h2>
                        <!-- Live preview badge -->
                        <span
                            v-if="isDraft && canManage"
                            class="flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold transition-all"
                            :class="isPreviewDirty
                                ? 'bg-brand-100 text-brand-700 dark:bg-brand-900/40 dark:text-brand-300 animate-pulse'
                                : 'bg-slate-100 text-slate-400 dark:bg-slate-800'"
                        >
                            <span class="inline-block h-1.5 w-1.5 rounded-full"
                                :class="isPreviewDirty ? 'bg-brand-500' : 'bg-slate-300'"
                            />
                            {{ isPreviewDirty ? 'Preview langsung' : 'Tersimpan' }}
                        </span>
                    </div>

                    <div class="divide-y divide-slate-50 dark:divide-slate-800">
                        <!-- Base salary -->
                        <div class="flex justify-between px-6 py-4">
                            <span class="text-sm text-slate-600 dark:text-slate-400">Gaji Pokok</span>
                            <span class="font-semibold text-slate-800 dark:text-slate-100">{{ fmt(previewBaseSalary) }}</span>
                        </div>

                        <!-- Allowance -->
                        <div class="flex justify-between px-6 py-4 transition-colors" :class="isDraft && canManage && form.allowance !== Number(props.payroll.allowance) ? 'bg-brand-50/50 dark:bg-brand-950/5' : ''">
                            <div>
                                <span class="text-sm text-slate-600 dark:text-slate-400">Tunjangan</span>
                                <p v-if="form.allowance_notes" class="text-xs text-slate-400">{{ form.allowance_notes }}</p>
                            </div>
                            <span class="font-semibold text-emerald-600 dark:text-emerald-400">+ {{ fmt(form.allowance) }}</span>
                        </div>

                        <!-- Bonus -->
                        <div class="flex justify-between px-6 py-4 transition-colors" :class="isDraft && canManage && form.bonus !== Number(props.payroll.bonus) ? 'bg-brand-50/50 dark:bg-brand-950/5' : ''">
                            <div>
                                <span class="text-sm text-slate-600 dark:text-slate-400">Bonus</span>
                                <p v-if="form.bonus_notes" class="text-xs text-slate-400">{{ form.bonus_notes }}</p>
                            </div>
                            <span class="font-semibold text-emerald-600 dark:text-emerald-400">+ {{ fmt(form.bonus) }}</span>
                        </div>

                        <!-- Shift Night -->
                        <div class="flex justify-between px-6 py-4 transition-colors" :class="isDraft && canManage && (form.shift_night_days !== props.payroll.shift_night_days || form.shift_night_rate !== Number(props.payroll.shift_night_rate)) ? 'bg-brand-50/50 dark:bg-brand-950/5' : ''">
                            <div>
                                <span class="text-sm text-slate-600 dark:text-slate-400">Shift Malam</span>
                                <p class="text-xs text-slate-400">{{ form.shift_night_days }} hari @ {{ fmt(form.shift_night_rate) }}</p>
                            </div>
                            <span class="font-semibold text-emerald-600 dark:text-emerald-400">+ {{ fmt(previewShiftNightPay) }}</span>
                        </div>

                        <!-- Overtime -->
                        <div class="flex justify-between px-6 py-4 transition-colors" :class="isDraft && canManage && (form.overtime_hours !== props.payroll.overtime_hours || form.overtime_rate !== Number(props.payroll.overtime_rate)) ? 'bg-brand-50/50 dark:bg-brand-950/5' : ''">
                            <div>
                                <span class="text-sm text-slate-600 dark:text-slate-400">Lembur</span>
                                <p class="text-xs text-slate-400">{{ form.overtime_hours }} jam @ {{ fmt(form.overtime_rate) }}</p>
                            </div>
                            <span class="font-semibold text-emerald-600 dark:text-emerald-400">+ {{ fmt(previewOvertimePay) }}</span>
                        </div>

                        <!-- Gross -->
                        <div class="flex justify-between bg-slate-50 px-6 py-4 dark:bg-slate-800/50">
                            <span class="text-sm font-semibold text-slate-700 dark:text-slate-300">Gaji Kotor</span>
                            <span class="font-bold text-slate-800 dark:text-slate-100 tabular-nums">{{ fmt(previewGross) }}</span>
                        </div>

                        <!-- Absence deduction -->
                        <div class="flex justify-between px-6 py-4 transition-colors" :class="isDraft && canManage && form.apply_absence_deduction !== props.payroll.apply_absence_deduction ? 'bg-brand-50/50 dark:bg-brand-950/5' : ''">
                            <div>
                                <span class="text-sm text-slate-600 dark:text-slate-400">Potongan Alpa</span>
                                <p class="text-xs text-slate-400">
                                    {{ props.payroll.absent_days }} hari alpa dari {{ props.payroll.working_days }} hari kerja
                                    <span v-if="!form.apply_absence_deduction" class="ml-1 text-slate-300">(tidak diaktifkan)</span>
                                </p>
                            </div>
                            <span class="font-semibold text-red-500 dark:text-red-400">
                                {{ form.apply_absence_deduction ? `− ${fmt(previewDeductionAbsence)}` : '—' }}
                            </span>
                        </div>

                        <!-- Other deduction -->
                        <div class="flex justify-between px-6 py-4 transition-colors" :class="isDraft && canManage && form.deduction_other !== Number(props.payroll.deduction_other) ? 'bg-brand-50/50 dark:bg-brand-950/5' : ''">
                            <div>
                                <span class="text-sm text-slate-600 dark:text-slate-400">Potongan Lain</span>
                                <p v-if="form.deduction_notes" class="text-xs text-slate-400">{{ form.deduction_notes }}</p>
                            </div>
                            <span class="font-semibold text-red-500 dark:text-red-400">− {{ fmt(form.deduction_other) }}</span>
                        </div>

                        <!-- Net salary -->
                        <div class="flex justify-between bg-brand-50 px-6 py-5 dark:bg-brand-950/10 transition-all duration-300">
                            <span class="text-base font-bold text-slate-800 dark:text-slate-100">Gaji Bersih (Take Home)</span>
                            <span class="text-xl font-extrabold text-brand-600 dark:text-brand-400 tabular-nums transition-all duration-300">{{ fmt(previewNet) }}</span>
                        </div>
                    </div>
                </div>

                <!-- Attendance summary -->
                <div class="grid grid-cols-3 gap-4">
                    <div class="rounded-2xl border border-slate-100 bg-white p-4 text-center shadow-sm dark:border-slate-800 dark:bg-slate-900">
                        <p class="text-xs text-slate-400">Hari Kerja</p>
                        <p class="mt-1 text-2xl font-bold text-slate-800 dark:text-slate-100">{{ payroll.working_days }}</p>
                    </div>
                    <div class="rounded-2xl border border-emerald-100 bg-emerald-50 p-4 text-center shadow-sm dark:border-emerald-900/30 dark:bg-emerald-950/10">
                        <p class="text-xs text-emerald-600 dark:text-emerald-400">Hadir</p>
                        <p class="mt-1 text-2xl font-bold text-emerald-700 dark:text-emerald-400">{{ payroll.present_days }}</p>
                    </div>
                    <div class="rounded-2xl border border-red-100 bg-red-50 p-4 text-center shadow-sm dark:border-red-900/30 dark:bg-red-950/10">
                        <p class="text-xs text-red-500 dark:text-red-400">Alpa</p>
                        <p class="mt-1 text-2xl font-bold text-red-600 dark:text-red-400">{{ payroll.absent_days }}</p>
                    </div>
                </div>

                <!-- Payment info (if paid) -->
                <div
                    v-if="isPaid"
                    class="flex items-center gap-3 rounded-2xl border border-emerald-200 bg-emerald-50 px-5 py-4 dark:border-emerald-900/30 dark:bg-emerald-950/10"
                >
                    <CheckCircle2 class="h-5 w-5 shrink-0 text-emerald-600 dark:text-emerald-400" />
                    <div class="text-sm text-emerald-700 dark:text-emerald-400">
                        <strong>Dibayar</strong> pada {{ new Date(payroll.paid_at!).toLocaleDateString("id-ID", { day: "numeric", month: "long", year: "numeric" }) }}
                        <span v-if="payroll.transaction"> · Transaksi <strong>{{ payroll.transaction.transaction_number }}</strong></span>
                    </div>
                </div>
            </div>

            <!-- Right: Edit panel (draft only) / Info panel -->
            <div class="flex flex-col gap-4">
                <!-- Employee info -->
                <div class="rounded-2xl border border-slate-100 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <div class="flex items-center gap-3">
                        <div class="flex h-12 w-12 items-center justify-center rounded-full bg-brand-100 text-lg font-bold text-brand-700 dark:bg-brand-900/30 dark:text-brand-400">
                            {{ payroll.employee?.name?.charAt(0) }}
                        </div>
                        <div>
                            <p class="font-bold text-slate-800 dark:text-slate-100">{{ payroll.employee?.name }}</p>
                            <p class="text-xs text-slate-400">{{ payroll.employee?.position?.name ?? '—' }}</p>
                        </div>
                    </div>
                    <div class="mt-4 space-y-2 text-sm">
                        <div class="flex items-center gap-2 text-slate-500">
                            <Calendar class="h-3.5 w-3.5" />
                            <span>{{ periodLabel }}</span>
                        </div>
                        <div v-if="payroll.approver" class="flex items-center gap-2 text-slate-500">
                            <BadgeCheck class="h-3.5 w-3.5" />
                            <span>Disetujui oleh <strong>{{ payroll.approver.name }}</strong></span>
                        </div>
                    </div>
                </div>

                <!-- Edit form: draft only -->
                <form
                    v-if="isDraft && canManage"
                    class="overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900"
                    @submit.prevent="save"
                >
                    <div class="border-b border-slate-100 px-5 py-4 dark:border-slate-800">
                        <h3 class="text-sm font-semibold text-slate-600 dark:text-slate-400">Edit Komponen Gaji</h3>
                    </div>
                    <div class="space-y-5 p-5">
                        <!-- Toggle absence deduction -->
                        <div>
                            <label class="mb-1.5 block text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase">Potongan Alpa</label>
                            <button
                                type="button"
                                class="flex w-full items-center justify-between rounded-xl border px-4 py-3 text-sm transition"
                                :class="form.apply_absence_deduction
                                    ? 'border-red-200 bg-red-50 text-red-700 dark:border-red-900/40 dark:bg-red-950/20 dark:text-red-400'
                                    : 'border-slate-200 bg-slate-50 text-slate-500 dark:border-slate-700 dark:bg-slate-800'"
                                @click="form.apply_absence_deduction = !form.apply_absence_deduction"
                            >
                                <span>{{ form.apply_absence_deduction ? 'Aktif — dipotong per hari' : 'Nonaktif — gaji penuh' }}</span>
                                <ToggleRight v-if="form.apply_absence_deduction" class="h-5 w-5" />
                                <ToggleLeft v-else class="h-5 w-5" />
                            </button>
                        </div>

                        <!-- Allowance -->
                        <div>
                            <label class="mb-1.5 block text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase">Tunjangan (Rp)</label>
                            <input
                                v-model.number="form.allowance"
                                type="number"
                                min="0"
                                class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100"
                            />
                            <input
                                v-model="form.allowance_notes"
                                type="text"
                                placeholder="Keterangan tunjangan..."
                                class="mt-2 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-xs focus:outline-none focus:ring-2 focus:ring-brand-500 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-400"
                            />
                        </div>

                        <!-- Bonus -->
                        <div>
                            <label class="mb-1.5 block text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase">Bonus (Rp)</label>
                            <input
                                v-model.number="form.bonus"
                                type="number"
                                min="0"
                                class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100"
                            />
                            <input
                                v-model="form.bonus_notes"
                                type="text"
                                placeholder="Keterangan bonus..."
                                class="mt-2 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-xs focus:outline-none focus:ring-2 focus:ring-brand-500 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-400"
                            />
                        </div>

                        <!-- Shift Night (Jadwal) -->
                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="mb-1.5 block text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase">Hari Shift Malam</label>
                                <input
                                    v-model.number="form.shift_night_days"
                                    type="number"
                                    min="0"
                                    class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100"
                                />
                            </div>
                            <div>
                                <label class="mb-1.5 block text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase">Tarif / Shift (Rp)</label>
                                <input
                                    v-model.number="form.shift_night_rate"
                                    type="number"
                                    min="0"
                                    class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100"
                                />
                            </div>
                        </div>

                        <!-- Overtime (Jadwal) -->
                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="mb-1.5 block text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase">Jam Lembur</label>
                                <input
                                    v-model.number="form.overtime_hours"
                                    type="number"
                                    min="0"
                                    class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100"
                                />
                            </div>
                            <div>
                                <label class="mb-1.5 block text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase">Tarif / Jam (Rp)</label>
                                <input
                                    v-model.number="form.overtime_rate"
                                    type="number"
                                    min="0"
                                    class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100"
                                />
                            </div>
                        </div>

                        <!-- Other deduction -->
                        <div>
                            <label class="mb-1.5 block text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase">Potongan Lain (Rp)</label>
                            <input
                                v-model.number="form.deduction_other"
                                type="number"
                                min="0"
                                class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100"
                            />
                            <input
                                v-model="form.deduction_notes"
                                type="text"
                                placeholder="Keterangan potongan..."
                                class="mt-2 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-xs focus:outline-none focus:ring-2 focus:ring-brand-500 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-400"
                            />
                        </div>

                        <!-- Notes -->
                        <div>
                            <label class="mb-1.5 block text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase">Catatan</label>
                            <textarea
                                v-model="form.notes"
                                rows="2"
                                class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100"
                            />
                        </div>

                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="w-full rounded-xl bg-brand-600 py-2.5 text-sm font-semibold text-white transition hover:bg-brand-700 disabled:opacity-50"
                        >
                            {{ form.processing ? 'Menyimpan...' : 'Simpan Perubahan' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>
