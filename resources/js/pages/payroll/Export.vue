<script setup lang="ts">
import { Head } from "@inertiajs/vue3";
import { computed, onMounted } from "vue";

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
        status: string;
        paid_at: string | null;
        notes: string | null;
        employee: {
            id: number;
            name: string;
            salary: string;
            position: { name: string } | null;
        };
        approver: { name: string } | null;
    };
}>();

const months = [
    "Januari", "Februari", "Maret", "April", "Mei", "Juni",
    "Juli", "Agustus", "September", "Oktober", "November", "Desember",
];
const periodLabel = computed(
    () => `${months[props.payroll.period_month - 1]} ${props.payroll.period_year}`,
);
const fmt = (val: string | number) =>
    new Intl.NumberFormat("id-ID", { style: "currency", currency: "IDR", maximumFractionDigits: 0 }).format(Number(val) ?? 0);

onMounted(() => {
    // Automatically trigger print dialog when page loads
    window.print();
});
</script>

<template>
    <Head :title="`Slip_Gaji_${payroll.employee?.name}_${payroll.period_month}_${payroll.period_year}`" />

    <div class="mx-auto max-w-3xl bg-white p-8 text-slate-800 antialiased print:p-0">
        <!-- Header -->
        <div class="flex items-start justify-between border-b-2 border-slate-900 pb-6">
            <div>
                <h1 class="text-2xl font-black tracking-tight text-slate-900">SLIP GAJI KARYAWAN</h1>
                <p class="mt-1 text-sm font-semibold uppercase text-brand-600">Restoran D'Mogus</p>
            </div>
            <div class="text-right text-sm">
                <p class="font-bold text-slate-900">Periode</p>
                <p class="text-slate-600">{{ periodLabel }}</p>
            </div>
        </div>

        <!-- Info Grid -->
        <div class="my-6 grid grid-cols-2 gap-4 rounded-xl bg-slate-50 p-4 text-xs dark:bg-slate-50 dark:text-slate-800">
            <div>
                <table class="w-full text-left">
                    <tbody>
                        <tr>
                            <th class="py-1 pr-4 font-semibold text-slate-500">Nama</th>
                            <td class="py-1 font-bold text-slate-800">: {{ payroll.employee?.name }}</td>
                        </tr>
                        <tr>
                            <th class="py-1 pr-4 font-semibold text-slate-500">Jabatan</th>
                            <td class="py-1 text-slate-700">: {{ payroll.employee?.position?.name ?? '—' }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div>
                <table class="w-full text-left">
                    <tbody>
                        <tr>
                            <th class="py-1 pr-4 font-semibold text-slate-500">Hari Kerja</th>
                            <td class="py-1 text-slate-700">: {{ payroll.working_days }} hari</td>
                        </tr>
                        <tr>
                            <th class="py-1 pr-4 font-semibold text-slate-500">Kehadiran</th>
                            <td class="py-1 text-slate-700">: {{ payroll.present_days }} hadir / {{ payroll.absent_days }} alpa</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Details Grid (Penerimaan & Potongan) -->
        <div class="grid grid-cols-2 gap-8">
            <!-- Penerimaan -->
            <div>
                <h3 class="border-b border-slate-900 pb-2 text-xs font-bold uppercase tracking-wider text-slate-900">
                    Penerimaan (+)
                </h3>
                <table class="w-full text-xs">
                    <tbody>
                        <tr class="border-b border-slate-100">
                            <td class="py-3 text-slate-600">Gaji Pokok</td>
                            <td class="py-3 text-right font-semibold text-slate-800">{{ fmt(payroll.base_salary) }}</td>
                        </tr>
                        <tr class="border-b border-slate-100" v-if="Number(payroll.allowance) > 0">
                            <td class="py-3">
                                <span class="block text-slate-600">Tunjangan</span>
                                <span class="text-[10px] text-slate-400" v-if="payroll.allowance_notes">{{ payroll.allowance_notes }}</span>
                            </td>
                            <td class="py-3 text-right font-semibold text-slate-800">{{ fmt(payroll.allowance) }}</td>
                        </tr>
                        <tr class="border-b border-slate-100" v-if="Number(payroll.bonus) > 0">
                            <td class="py-3">
                                <span class="block text-slate-600">Bonus</span>
                                <span class="text-[10px] text-slate-400" v-if="payroll.bonus_notes">{{ payroll.bonus_notes }}</span>
                            </td>
                            <td class="py-3 text-right font-semibold text-slate-800">{{ fmt(payroll.bonus) }}</td>
                        </tr>
                        <tr class="border-b border-slate-100" v-if="payroll.shift_night_days > 0">
                            <td class="py-3">
                                <span class="block text-slate-600">Shift Malam</span>
                                <span class="text-[10px] text-slate-400">{{ payroll.shift_night_days }} hari @ {{ fmt(payroll.shift_night_rate) }}</span>
                            </td>
                            <td class="py-3 text-right font-semibold text-slate-800">
                                {{ fmt(Number(payroll.shift_night_days) * Number(payroll.shift_night_rate)) }}
                            </td>
                        </tr>
                        <tr class="border-b border-slate-100" v-if="payroll.overtime_hours > 0">
                            <td class="py-3">
                                <span class="block text-slate-600">Lembur</span>
                                <span class="text-[10px] text-slate-400">{{ payroll.overtime_hours }} jam @ {{ fmt(payroll.overtime_rate) }}</span>
                            </td>
                            <td class="py-3 text-right font-semibold text-slate-800">
                                {{ fmt(Number(payroll.overtime_hours) * Number(payroll.overtime_rate)) }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Potongan -->
            <div>
                <h3 class="border-b border-slate-900 pb-2 text-xs font-bold uppercase tracking-wider text-slate-900">
                    Potongan (-)
                </h3>
                <table class="w-full text-xs">
                    <tbody>
                        <tr class="border-b border-slate-100" v-if="payroll.apply_absence_deduction && Number(payroll.deduction_absence) > 0">
                            <td class="py-3">
                                <span class="block text-slate-600">Potongan Alpa</span>
                                <span class="text-[10px] text-slate-400">{{ payroll.absent_days }} hari alpa</span>
                            </td>
                            <td class="py-3 text-right font-semibold text-red-600">{{ fmt(payroll.deduction_absence) }}</td>
                        </tr>
                        <tr class="border-b border-slate-100" v-if="Number(payroll.deduction_other) > 0">
                            <td class="py-3">
                                <span class="block text-slate-600">Potongan Lain</span>
                                <span class="text-[10px] text-slate-400" v-if="payroll.deduction_notes">{{ payroll.deduction_notes }}</span>
                            </td>
                            <td class="py-3 text-right font-semibold text-red-600">{{ fmt(payroll.deduction_other) }}</td>
                        </tr>
                        <tr v-if="!(payroll.apply_absence_deduction && Number(payroll.deduction_absence) > 0) && !(Number(payroll.deduction_other) > 0)">
                            <td colspan="2" class="py-4 text-center text-slate-400 italic">Tidak ada potongan</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Total Breakdown -->
        <div class="mt-8 border-t-2 border-slate-900 pt-6">
            <div class="space-y-2 text-xs">
                <div class="flex justify-between">
                    <span class="text-slate-600">Total Penerimaan (Gaji Kotor)</span>
                    <span class="font-bold text-slate-800">{{ fmt(payroll.gross_salary) }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-slate-600">Total Potongan</span>
                    <span class="font-bold text-red-600">
                        {{ fmt(Number(payroll.apply_absence_deduction ? payroll.deduction_absence : 0) + Number(payroll.deduction_other)) }}
                    </span>
                </div>
                <div class="flex justify-between border-t border-dashed border-slate-200 pt-3 text-sm">
                    <span class="font-black text-slate-900 uppercase">Gaji Bersih (Take Home Pay)</span>
                    <span class="text-base font-black text-brand-600">{{ fmt(payroll.net_salary) }}</span>
                </div>
            </div>
        </div>

        <!-- Notes if any -->
        <div class="mt-8 border-t border-slate-100 pt-4" v-if="payroll.notes">
            <h4 class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Catatan/Keterangan:</h4>
            <p class="mt-1 text-xs text-slate-600 leading-relaxed">{{ payroll.notes }}</p>
        </div>

        <!-- Footer Signatures -->
        <div class="mt-16 grid grid-cols-2 text-center text-xs">
            <div>
                <p class="text-slate-400">Penerima,</p>
                <div class="h-20"></div>
                <p class="font-bold border-t border-slate-300 mx-auto w-48 pt-2 text-slate-800">{{ payroll.employee?.name }}</p>
            </div>
            <div>
                <p class="text-slate-400">Mengetahui,</p>
                <div class="h-20"></div>
                <p class="font-bold border-t border-slate-300 mx-auto w-48 pt-2 text-slate-800">
                    {{ payroll.approver?.name ?? 'Superadmin' }}
                </p>
            </div>
        </div>
    </div>
</template>
