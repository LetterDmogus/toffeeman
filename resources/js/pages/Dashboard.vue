<script setup lang="ts">
import { Head, Link, usePage } from "@inertiajs/vue3";
import {
    ArrowRight,
    BarChart3,
    Boxes,
    ChefHat,
    ClipboardCheck,
    ClipboardList,
    Clock,
    Monitor,
    Receipt,
    Sparkles,
    UtensilsCrossed,
} from "lucide-vue-next";
import { computed } from "vue";
defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: "Dashboard",
                href: route("dashboard"),
            },
        ],
    },
});

const page = usePage();
const user = computed(() => page.props.auth.user);
const permissions = computed<string[]>(() => (page.props.auth as any).permissions ?? []);

const hasPermission = (permission: string): boolean => {
    if (!user.value) return false;
    if (user.value.role === "superadmin") return true;
    return permissions.value.includes(permission);
};

// Greeting based on current hour
const greeting = computed(() => {
    const hour = new Date().getHours();
    if (hour < 11) return "Selamat Pagi";
    if (hour < 15) return "Selamat Siang";
    if (hour < 18) return "Selamat Sore";
    return "Selamat Malam";
});

const firstName = computed(() => {
    return user.value?.name?.split(" ")[0] ?? "Pengguna";
});

const todayLabel = computed(() => {
    return new Intl.DateTimeFormat("id-ID", {
        weekday: "long",
        day: "numeric",
        month: "long",
        year: "numeric",
    }).format(new Date());
});

// All possible shortcut cards, gated by permission
const allShortcuts = computed(() => [
    {
        label: "Kasir (POS)",
        description: "Buka sesi kasir dan proses pesanan pelanggan secara langsung.",
        href: route("pos"),
        icon: Monitor,
        color: "from-violet-500 to-purple-600",
        bg: "from-violet-50 to-purple-50 dark:from-violet-950/20 dark:to-purple-950/10",
        border: "border-violet-200 dark:border-violet-900/30",
        text: "text-violet-600 dark:text-violet-400",
        permission: "pos-access",
    },
    {
        label: "Dapur (KDS)",
        description: "Pantau antrian masakan dan update status hidangan secara real-time.",
        href: route("kitchen"),
        icon: ChefHat,
        color: "from-orange-500 to-amber-600",
        bg: "from-orange-50 to-amber-50 dark:from-orange-950/20 dark:to-amber-950/10",
        border: "border-orange-200 dark:border-orange-900/30",
        text: "text-orange-600 dark:text-orange-400",
        permission: "kitchen-access",
    },
    {
        label: "Daftar Pesanan",
        description: "Lihat semua pesanan aktif, riwayat, dan status pembayaran.",
        href: route("orders"),
        icon: Receipt,
        color: "from-sky-500 to-blue-600",
        bg: "from-sky-50 to-blue-50 dark:from-sky-950/20 dark:to-blue-950/10",
        border: "border-sky-200 dark:border-sky-900/30",
        text: "text-sky-600 dark:text-sky-400",
        permission: "orders-access",
    },
    {
        label: "Laporan Keuangan",
        description: "Pantau arus kas, transaksi, dan tren pemasukan harian.",
        href: route("reports"),
        icon: BarChart3,
        color: "from-emerald-500 to-teal-600",
        bg: "from-emerald-50 to-teal-50 dark:from-emerald-950/20 dark:to-teal-950/10",
        border: "border-emerald-200 dark:border-emerald-900/30",
        text: "text-emerald-600 dark:text-emerald-400",
        permission: "view-reports",
    },
    {
        label: "Laporan Pesanan",
        description: "Rekap pesanan harian, ekspor CSV, dan analisis performa menu.",
        href: route("reports.orders"),
        icon: ClipboardCheck,
        color: "from-cyan-500 to-blue-500",
        bg: "from-cyan-50 to-blue-50 dark:from-cyan-950/20 dark:to-blue-950/10",
        border: "border-cyan-200 dark:border-cyan-900/30",
        text: "text-cyan-600 dark:text-cyan-400",
        permission: "view-reports",
    },
    {
        label: "Menu & Katalog",
        description: "Kelola menu, kategori, paket, promo, dan variasi hidangan.",
        href: route("catalog.menu"),
        icon: UtensilsCrossed,
        color: "from-rose-500 to-pink-600",
        bg: "from-rose-50 to-pink-50 dark:from-rose-950/20 dark:to-pink-950/10",
        border: "border-rose-200 dark:border-rose-900/30",
        text: "text-rose-600 dark:text-rose-400",
        permission: "manage-catalog",
    },
    {
        label: "Stok Bahan Baku",
        description: "Monitor bahan baku, batch, mutasi, dan notifikasi stok menipis.",
        href: route("ops.ingredients"),
        icon: Boxes,
        color: "from-lime-500 to-green-600",
        bg: "from-lime-50 to-green-50 dark:from-lime-950/20 dark:to-green-950/10",
        border: "border-lime-200 dark:border-lime-900/30",
        text: "text-lime-600 dark:text-lime-400",
        permission: "manage-ops-ingredients",
    },
    {
        label: "Kios Absensi",
        description: "Buka kios absensi berbasis face-scan atau QR untuk karyawan.",
        href: route("attendance.kiosk"),
        icon: Clock,
        color: "from-indigo-500 to-blue-600",
        bg: "from-indigo-50 to-blue-50 dark:from-indigo-950/20 dark:to-blue-950/10",
        border: "border-indigo-200 dark:border-indigo-900/30",
        text: "text-indigo-600 dark:text-indigo-400",
        permission: "kiosk-attendance-access",
    },
    {
        label: "Manajemen Absensi",
        description: "Rekap kehadiran karyawan, izin, dan data jam kerja.",
        href: route("attendance.index"),
        icon: ClipboardList,
        color: "from-fuchsia-500 to-purple-600",
        bg: "from-fuchsia-50 to-purple-50 dark:from-fuchsia-950/20 dark:to-purple-950/10",
        border: "border-fuchsia-200 dark:border-fuchsia-900/30",
        text: "text-fuchsia-600 dark:text-fuchsia-400",
        permission: "attendance-management",
    },
]);

const shortcuts = computed(() =>
    allShortcuts.value.filter((s) => hasPermission(s.permission)),
);
</script>

<template>
    <Head title="Dashboard" />

    <div class="flex h-full flex-1 flex-col gap-8 overflow-x-hidden p-6">
        <!-- ── Welcome Banner ──────────────────────────────────── -->
        <div
            class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-brand-600 via-brand-500 to-rose-500 p-8 text-white shadow-lg"
        >
            <!-- Decorative circles -->
            <div
                class="pointer-events-none absolute -right-16 -top-16 h-64 w-64 rounded-full bg-white/5"
            />
            <div
                class="pointer-events-none absolute -bottom-10 -left-10 h-48 w-48 rounded-full bg-white/5"
            />
            <div
                class="pointer-events-none absolute right-32 top-4 h-20 w-20 rounded-full bg-white/10"
            />

            <div class="relative z-10 flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <div class="mb-1 flex items-center gap-2 text-white/70">
                        <Sparkles class="h-4 w-4" />
                        <span class="text-sm font-medium">{{ todayLabel }}</span>
                    </div>
                    <h1 class="text-3xl font-bold tracking-tight">
                        {{ greeting }}, {{ firstName }}! 👋
                    </h1>
                    <p class="mt-2 max-w-lg text-sm leading-relaxed text-white/80">
                        Semua sistem berjalan normal. Gunakan shortcut di bawah untuk
                        mengakses fitur yang kamu butuhkan hari ini.
                    </p>
                </div>

                <!-- Role badge -->
                <div
                    class="mt-4 inline-flex shrink-0 items-center gap-2 rounded-xl bg-white/15 px-4 py-2 text-sm font-semibold backdrop-blur-sm sm:mt-0"
                >
                    <span class="h-2 w-2 animate-pulse rounded-full bg-emerald-300" />
                    {{ user?.role ?? "—" }}
                </div>
            </div>
        </div>

        <!-- ── Shortcut Cards ───────────────────────────────────── -->
        <div v-if="shortcuts.length > 0">
            <h2 class="mb-4 text-sm font-semibold tracking-wide text-slate-500 uppercase dark:text-slate-400">
                Akses Cepat
            </h2>
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <Link
                    v-for="shortcut in shortcuts"
                    :key="shortcut.label"
                    :href="shortcut.href"
                    prefetch
                    class="group relative overflow-hidden rounded-2xl border p-5 shadow-sm transition-all duration-300 hover:-translate-y-0.5 hover:shadow-md"
                    :class="[shortcut.bg, shortcut.border]"
                >
                    <!-- Faint bg icon -->
                    <component
                        :is="shortcut.icon"
                        class="pointer-events-none absolute -right-4 -top-4 h-24 w-24 opacity-[0.07] transition-transform duration-500 group-hover:scale-110"
                    />

                    <div class="relative z-10 flex flex-col gap-3">
                        <!-- Icon pill -->
                        <div
                            class="inline-flex w-fit rounded-xl bg-gradient-to-br p-3 text-white shadow-sm"
                            :class="shortcut.color"
                        >
                            <component :is="shortcut.icon" class="h-5 w-5" />
                        </div>

                        <!-- Text -->
                        <div>
                            <h3 class="font-bold text-slate-800 dark:text-slate-100">
                                {{ shortcut.label }}
                            </h3>
                            <p class="mt-1 text-xs leading-relaxed text-slate-500 dark:text-slate-400">
                                {{ shortcut.description }}
                            </p>
                        </div>

                        <!-- CTA -->
                        <div
                            class="flex items-center gap-1 text-xs font-semibold"
                            :class="shortcut.text"
                        >
                            <span>Buka</span>
                            <ArrowRight
                                class="h-3.5 w-3.5 transition-transform duration-200 group-hover:translate-x-0.5"
                            />
                        </div>
                    </div>
                </Link>
            </div>
        </div>

        <!-- No shortcuts fallback (rare edge case) -->
        <div
            v-else
            class="flex flex-1 items-center justify-center rounded-2xl border border-dashed border-slate-200 p-12 dark:border-slate-700"
        >
            <p class="text-sm text-slate-400">
                Tidak ada modul yang dapat diakses dengan role ini.
            </p>
        </div>
    </div>
</template>
