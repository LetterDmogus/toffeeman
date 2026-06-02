<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { dashboard } from '@/routes';
import catalog from '@/routes/catalog';
import { ArrowRight, Database, ShoppingCart, UserCheck } from 'lucide-vue-next';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Dashboard',
                href: dashboard().url,
            },
        ],
    },
});
</script>

<template>
    <Head title="Dashboard" />

    <div class="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-6">
        <!-- Welcoming Section & Admin Entry Option -->
        <div class="grid gap-6 md:grid-cols-3">
            <!-- Catalog Menu Navigation Card -->
            <Link
                :href="catalog.menu().url"
                class="group relative overflow-hidden rounded-2xl border border-brand-200 bg-gradient-to-br from-brand-50 to-rose-50 p-6 shadow-sm transition-all duration-300 hover:-translate-y-1 hover:border-brand-300 hover:shadow-md dark:border-brand-950/30 dark:from-brand-950/20 dark:to-rose-950/10"
            >
                <div class="absolute -right-6 -top-6 text-brand-200/50 transition-transform duration-500 group-hover:scale-110 dark:text-brand-900/20">
                    <Database class="h-32 w-32" />
                </div>
                <div class="relative z-10 flex h-full flex-col justify-between">
                    <div>
                        <div class="inline-flex rounded-xl bg-brand-500 p-3 text-white">
                            <Database class="h-6 w-6" />
                        </div>
                        <h3 class="mt-4 text-xl font-bold text-slate-800 dark:text-slate-100">
                            Kelola Katalog Menu
                        </h3>
                        <p class="mt-2 text-sm text-slate-600 dark:text-slate-300">
                            Akses modul menu, varian, kategori, dan paket restoran secara instan.
                        </p>
                    </div>
                    <div class="mt-6 flex items-center gap-2 text-sm font-semibold text-brand-600 dark:text-brand-400">
                        <span>Buka Katalog Menu</span>
                        <ArrowRight class="h-4 w-4 transition-transform group-hover:translate-x-1" />
                    </div>
                </div>
            </Link>

            <!-- Quick Info: POS -->
            <div class="relative overflow-hidden rounded-2xl border border-slate-100 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="inline-flex rounded-xl bg-blue-500 p-3 text-white">
                    <ShoppingCart class="h-6 w-6" />
                </div>
                <h3 class="mt-4 text-xl font-bold text-slate-800 dark:text-slate-100">
                    POS / Kasir Aktif
                </h3>
                <p class="mt-2 text-sm text-slate-600 dark:text-slate-300">
                    Layanan pemesanan menu via QR meja dan kasir restoran terintegrasi real-time.
                </p>
            </div>

            <!-- Quick Info: Karyawan -->
            <div class="relative overflow-hidden rounded-2xl border border-slate-100 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="inline-flex rounded-xl bg-emerald-500 p-3 text-white">
                    <UserCheck class="h-6 w-6" />
                </div>
                <h3 class="mt-4 text-xl font-bold text-slate-800 dark:text-slate-100">
                    Status Kehadiran
                </h3>
                <p class="mt-2 text-sm text-slate-600 dark:text-slate-300">
                    Semua karyawan aktif terdata. Rekap absensi dan penggajian otomatis aman.
                </p>
            </div>
        </div>

        <div class="relative min-h-[50vh] flex-1 rounded-2xl border border-slate-100 bg-slate-50/50 p-6 dark:border-slate-800 dark:bg-slate-900/50">
            <h4 class="text-sm font-semibold text-slate-500 dark:text-slate-400">Aktivitas Terbaru Restoran</h4>
            <div class="mt-4 flex h-full items-center justify-center text-slate-400">
                Belum ada aktivitas hari ini.
            </div>
        </div>
    </div>
</template>
