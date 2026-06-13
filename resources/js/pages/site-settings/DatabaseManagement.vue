<script setup lang="ts">
import { Head, router } from "@inertiajs/vue3";
import Heading from "@/components/Heading.vue";
import { Button } from "@/components/ui/button";
import { Database, RefreshCw, Download } from "lucide-vue-next";
import AppLayout from "@/layouts/AppLayout.vue";

defineOptions({
	layout: AppLayout,
	breadcrumbs: [
		{
			title: "Site Settings",
			href: "/site-settings/ip-location",
		},
		{
			title: "Database Management",
			href: "/site-settings/database",
		},
	],
});

const handleBackup = () => {
	window.location.href = "/site-settings/database/backup";
};

const handleReset = () => {
	if (confirm("Apakah Anda yakin ingin me-reset database? Tindakan ini akan menghapus seluruh data transaksi, menu, bahan baku, dll. dan mengembalikannya ke pengaturan awal (seeding). Anda juga akan otomatis keluar dari akun.")) {
		router.post("/site-settings/database/reset");
	}
};
</script>

<template>
    <Head title="Database Management" />

    <div class="px-6 py-6 w-full space-y-6">
        <Heading
            title="Database Management"
            description="Lakukan pencadangan (backup) atau pengembalian database ke pengaturan awal (reset)."
        />

        <div class="border border-border bg-card rounded-xl p-6 shadow-sm space-y-6">
            <div>
                <h3 class="text-lg font-medium flex items-center gap-2">
                    <Database class="h-5 w-5 text-brand-500" />
                    Database Tools
                </h3>
                <p class="text-xs text-muted-foreground">Kelola penyimpanan dan integritas data aplikasi Anda secara langsung.</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <!-- Backup Card -->
                <div class="p-4 border rounded-lg space-y-3 flex flex-col justify-between bg-card">
                    <div>
                        <h4 class="font-semibold text-sm flex items-center gap-2">
                            <Download class="h-4 w-4 text-emerald-500" />
                            Backup Database
                        </h4>
                        <p class="text-xs text-muted-foreground mt-1">Unduh salinan berkas database SQLite saat ini sebagai file cadangan.</p>
                    </div>
                    <Button type="button" variant="outline" @click="handleBackup" class="w-full mt-2 gap-1.5">
                        <Download class="h-4 w-4" />
                        Unduh Backup
                    </Button>
                </div>

                <!-- Reset Card -->
                <div class="p-4 border border-destructive/20 rounded-lg space-y-3 flex flex-col justify-between bg-destructive/5">
                    <div>
                        <h4 class="font-semibold text-sm flex items-center gap-2 text-destructive">
                            <RefreshCw class="h-4 w-4 text-destructive" />
                            Reset Database
                        </h4>
                        <p class="text-xs text-muted-foreground mt-1">Hapus seluruh data dinamis dan kembalikan ke data bawaan pabrik (seeding).</p>
                    </div>
                    <Button type="button" variant="destructive" @click="handleReset" class="w-full mt-2 gap-1.5">
                        <RefreshCw class="h-4 w-4" />
                        Reset Database
                    </Button>
                </div>
            </div>
        </div>
    </div>
</template>
