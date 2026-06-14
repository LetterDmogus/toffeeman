<script setup lang="ts">
import { ref } from "vue";
import type { Column, FormField } from "@/components/CRUDTable.vue";
import CRUDTable from "@/components/CRUDTable.vue";

defineOptions({
	layout: {
		breadcrumbs: [
			{ title: "Dashboard", href: "/dashboard" },
			{ title: "Operasional", href: "#" },
			{ title: "Meja", href: route("ops.tables") },
		],
	},
});

const columns: Column<any>[] = [
	{ key: "number", label: "No. Meja" },
	{ key: "name", label: "Nama Meja" },
	{ key: "capacity", label: "Kapasitas" },
	{ key: "location", label: "Lokasi" },
	{ key: "status", label: "Status" },
];

const fields: FormField[] = [
	{ key: "number", label: "Nomor Meja", type: "text", required: true },
	{ key: "name", label: "Nama Meja", type: "text" },
	{ key: "capacity", label: "Kapasitas", type: "number", required: true },
	{
		key: "location",
		label: "Lokasi",
		type: "select",
		required: true,
		options: [
			{ value: "indoor", label: "Indoor" },
			{ value: "outdoor", label: "Outdoor" },
			{ value: "private", label: "Private Room" },
		],
	},
	{
		key: "status",
		label: "Status",
		type: "select",
		required: true,
		options: [
			{ value: "available", label: "Tersedia" },
			{ value: "occupied", label: "Terisi" },
			{ value: "reserved", label: "Dipesan" },
		],
	},
];

const badgeMap = {
	available: "success",
	occupied: "danger",
	reserved: "warning",
};

// ── QR Modal ──────────────────────────────────────────────────────────────
const qrModal = ref<{
	open: boolean;
	table: any;
	imageUrl: string | null;
	generating: boolean;
}>({
	open: false,
	table: null,
	imageUrl: null,
	generating: false,
});

async function openQr(row: any) {
	qrModal.value = { open: true, table: row, imageUrl: null, generating: false };

	// If the table already has a QR image, show it; otherwise generate
	if (row.qr_image_path) {
		qrModal.value.imageUrl = `/storage/${row.qr_image_path}`;
	} else {
		await generateQr(row);
	}
}

async function generateQr(row: any) {
	qrModal.value.generating = true;

	try {
		const res = await fetch(`/api/tables/${row.id}/qr/generate`, {
			method: "POST",
			headers: {
				"X-CSRF-TOKEN":
					document
						.querySelector('meta[name="csrf-token"]')
						?.getAttribute("content") ?? "",
				Accept: "application/json",
			},
		});
		const data = await res.json();
		qrModal.value.imageUrl = data.qr_image_url;
		qrModal.value.table = {
			...qrModal.value.table,
			qr_image_path: data.qr_image_path,
		};
	} finally {
		qrModal.value.generating = false;
	}
}

function downloadQr(row: any) {
	window.open(`/api/tables/${row.id}/qr/download`, "_blank");
}

function closeQr() {
	qrModal.value.open = false;
}

function openKiosk(row: any) {
	window.open(`/kiosk/${row.qr_code}`, "_blank");
}
</script>

<template>
    <div class="p-6">
        <CRUDTable resource-name="Meja" api-url="/api/tables" :columns="columns" :form-fields="fields" :badge-map="badgeMap" auditable-type="Table">
            <template #actions="{ row }">
                <!-- QR Code button -->
                <button
                    class="inline-flex items-center justify-center h-7 w-7 rounded-md text-muted-foreground hover:text-foreground hover:bg-accent transition-colors"
                    title="QR Code"
                    @click="openQr(row)"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/>
                        <path d="M14 14h3v3h-3z"/><path d="M17 17h3v3h-3z"/><path d="M14 17h3"/><path d="M17 14h3"/>
                    </svg>
                </button>
            </template>
        </CRUDTable>

        <!-- QR Modal -->
        <Teleport to="body">
            <div v-if="qrModal.open" class="fixed inset-0 z-50 flex items-center justify-center" @click.self="closeQr">
                <div class="fixed inset-0 bg-black/60 backdrop-blur-sm" @click="closeQr" />
                <div class="relative bg-card border border-border rounded-2xl shadow-2xl w-full max-w-sm mx-4 overflow-hidden">
                    <!-- Header -->
                    <div class="flex items-center justify-between px-5 py-4 border-b border-border">
                        <div>
                            <h3 class="font-semibold text-foreground">QR Code Meja {{ qrModal.table?.number }}</h3>
                            <p class="text-xs text-muted-foreground mt-0.5">{{ qrModal.table?.name }}</p>
                        </div>
                        <button class="text-muted-foreground hover:text-foreground transition-colors" @click="closeQr">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6 6 18M6 6l12 12"/></svg>
                        </button>
                    </div>

                    <!-- Body -->
                    <div class="p-6">
                        <!-- Loading state -->
                        <div v-if="qrModal.generating" class="flex flex-col items-center gap-3 py-8">
                            <div class="h-8 w-8 animate-spin rounded-full border-2 border-primary border-t-transparent" />
                            <p class="text-sm text-muted-foreground">Generating QR code...</p>
                        </div>

                        <!-- QR Image -->
                        <div v-else-if="qrModal.imageUrl" class="flex flex-col items-center gap-4">
                            <div class="bg-white rounded-xl p-3 shadow-sm">
                                <img :src="qrModal.imageUrl" :alt="`QR Meja ${qrModal.table?.number}`" class="w-48 h-48 object-contain" />
                            </div>

                            <!-- Kiosk URL preview -->
                            <div class="w-full bg-muted/40 rounded-lg px-3 py-2 text-center">
                                <p class="text-xs text-muted-foreground truncate font-mono">/kiosk/{{ qrModal.table?.qr_code }}</p>
                            </div>

                            <!-- Actions -->
                            <div class="flex gap-2 w-full">
                                <button
                                    class="flex-1 flex items-center justify-center gap-2 px-3 py-2 rounded-lg border border-border text-sm hover:bg-accent transition-colors"
                                    @click="openKiosk(qrModal.table)"
                                >
                                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
                                    Test Kiosk
                                </button>
                                <button
                                    class="flex-1 flex items-center justify-center gap-2 px-3 py-2 rounded-lg border border-border text-sm hover:bg-accent transition-colors"
                                    @click="generateQr(qrModal.table)"
                                >
                                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 12a9 9 0 0 1 9-9 9.75 9.75 0 0 1 6.74 2.74L21 8"/><path d="M21 3v5h-5"/><path d="M21 12a9 9 0 0 1-9 9 9.75 9.75 0 0 1-6.74-2.74L3 16"/><path d="M8 16H3v5"/></svg>
                                    Regenerate
                                </button>
                                <button
                                    class="flex-1 flex items-center justify-center gap-2 px-3 py-2 rounded-lg bg-primary text-primary-foreground text-sm hover:bg-primary/90 transition-colors"
                                    @click="downloadQr(qrModal.table)"
                                >
                                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                                    Download
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </Teleport>
    </div>
</template>
