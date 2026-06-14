<script setup lang="ts">
import { ref } from "vue";
import type { Column, FormField } from "@/components/CRUDTable.vue";
import CRUDTable from "@/components/CRUDTable.vue";
import { Eye } from "lucide-vue-next";
import { Button } from "@/components/ui/button";
import {
	Dialog,
	DialogContent,
	DialogHeader,
	DialogTitle,
	DialogFooter,
} from "@/components/ui/dialog";

defineOptions({
	layout: {
		breadcrumbs: [
			{ title: "Dashboard", href: "/dashboard" },
			{ title: "Operasional", href: "#" },
			{ title: "Biaya Operasional", href: route("ops.expenses") },
		],
	},
});

const categoryLabels: Record<string, string> = {
	electricity: "Listrik",
	marketing: "Promosi / Iklan",
	water: "Air (PAM)",
	internet: "Internet & Telepon",
	rent: "Sewa Tempat",
	maintenance: "Pemeliharaan / Perbaikan",
	office_supplies: "Alat Tulis Kantor",
	other: "Lainnya",
	payroll: "Gaji Karyawan",
	inventory_purchase: "Pembelian Inventaris",
};

const paymentMethodLabels: Record<string, string> = {
	cash: "Tunai (Cash)",
	bank_transfer: "Transfer Bank",
	e_wallet: "E-Wallet",
	card: "Kartu Debit/Kredit",
	qris: "QRIS",
};

const columns: Column<any>[] = [
	{ key: "transaction_number", label: "No. Transaksi" },
	{
		key: "category",
		label: "Kategori",
		render: (val) => categoryLabels[val as string] || (val as string),
	},
	{
		key: "amount",
		label: "Jumlah",
		render: (val) => `Rp ${Math.round(val as number).toLocaleString("id-ID")}`,
	},
	{
		key: "payment_method",
		label: "Metode",
		render: (val) => paymentMethodLabels[val as string] || (val as string),
	},
	{
		key: "payment_status",
		label: "Status",
		render: (val) => (val === "completed" ? "Selesai" : "Pending"),
	},
	{
		key: "transaction_date",
		label: "Tanggal",
		render: (val) =>
			val ? new Date(val as string).toLocaleDateString("id-ID") : "—",
	},
];

const fields: FormField[] = [
	{
		key: "type",
		label: "Tipe Transaksi",
		type: "select",
		required: true,
		options: [
			{ value: "expense", label: "Pengeluaran (Operasional)" },
		],
	},
	{
		key: "category",
		label: "Kategori Pengeluaran",
		type: "select",
		required: true,
		options: [
			{ value: "electricity", label: "Listrik" },
			{ value: "marketing", label: "Promosi / Iklan" },
			{ value: "water", label: "Air (PAM)" },
			{ value: "internet", text: "Internet & Telepon", label: "Internet & Telepon" },
			{ value: "rent", label: "Sewa Tempat" },
			{ value: "maintenance", label: "Pemeliharaan / Perbaikan" },
			{ value: "office_supplies", label: "Alat Tulis Kantor" },
			{ value: "other", label: "Lainnya" },
		],
	},
	{ key: "amount", label: "Nominal Uang (Rp)", type: "number", required: true },
	{
		key: "payment_method",
		label: "Metode Pembayaran",
		type: "select",
		required: true,
		options: [
			{ value: "cash", label: "Tunai (Cash)" },
			{ value: "bank_transfer", label: "Transfer Bank" },
			{ value: "e_wallet", label: "E-Wallet" },
			{ value: "card", label: "Kartu Debit/Kredit" },
		],
	},
	{
		key: "payment_status",
		label: "Status Pembayaran",
		type: "select",
		required: true,
		options: [
			{ value: "completed", label: "Selesai (Lunas)" },
			{ value: "pending", label: "Pending / Belum Lunas" },
		],
	},
	{
		key: "transaction_date",
		label: "Tanggal Transaksi",
		type: "date",
		required: true,
	},
	{ key: "description", label: "Keterangan / Catatan", type: "textarea" },
];

const showDetailDialog = ref(false);
const selectedTransaction = ref<any>(null);

const openDetail = (row: any) => {
	selectedTransaction.value = row;
	showDetailDialog.value = true;
};
</script>

<template>
    <div class="p-6">
        <CRUDTable 
            resource-name="Biaya Operasional" 
            api-url="/api/transactions" 
            default-query="type=expense" 
            :columns="columns" 
            :form-fields="fields" 
            disable-edit
            disable-delete
            auditable-type="Transaction"
        >
            <template #actions="{ row }">
                <Button 
                    size="icon-sm" 
                    variant="ghost" 
                    class="text-blue-600 hover:text-blue-700 hover:bg-blue-50 dark:text-blue-400 dark:hover:text-blue-300 dark:hover:bg-blue-950/50"
                    @click="openDetail(row)" 
                    title="Cek Detail"
                >
                    <Eye class="h-4 w-4" />
                </Button>
            </template>
        </CRUDTable>

        <Dialog v-model:open="showDetailDialog">
            <DialogContent class="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle class="text-xl font-bold flex items-center gap-2">
                        <Eye class="h-5 w-5 text-blue-600 dark:text-blue-400" />
                        Detail Biaya Operasional
                    </DialogTitle>
                </DialogHeader>

                <div v-if="selectedTransaction" class="mt-4 space-y-4 text-sm">
                    <div class="grid grid-cols-2 gap-4 border-b border-border pb-4">
                        <div>
                            <span class="text-muted-foreground block text-xs font-medium uppercase tracking-wider">No. Transaksi</span>
                            <span class="font-mono text-base font-semibold text-foreground">{{ selectedTransaction.transaction_number }}</span>
                        </div>
                        <div>
                            <span class="text-muted-foreground block text-xs font-medium uppercase tracking-wider">Tanggal Transaksi</span>
                            <span class="font-semibold text-foreground">
                                {{ selectedTransaction.transaction_date ? new Date(selectedTransaction.transaction_date).toLocaleDateString("id-ID", { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' }) : '—' }}
                            </span>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <span class="text-muted-foreground block text-xs font-medium uppercase tracking-wider">Kategori</span>
                            <span class="inline-flex items-center rounded-md bg-secondary px-2.5 py-1 text-xs font-semibold text-secondary-foreground mt-0.5">
                                {{ categoryLabels[selectedTransaction.category] || selectedTransaction.category }}
                            </span>
                        </div>
                        <div>
                            <span class="text-muted-foreground block text-xs font-medium uppercase tracking-wider">Nominal</span>
                            <span class="text-lg font-bold text-blue-600 dark:text-blue-400">
                                Rp {{ Math.round(selectedTransaction.amount).toLocaleString("id-ID") }}
                            </span>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <span class="text-muted-foreground block text-xs font-medium uppercase tracking-wider">Metode Pembayaran</span>
                            <span class="font-medium text-foreground">
                                {{ paymentMethodLabels[selectedTransaction.payment_method] || selectedTransaction.payment_method }}
                            </span>
                        </div>
                        <div>
                            <span class="text-muted-foreground block text-xs font-medium uppercase tracking-wider">Status Pembayaran</span>
                            <span 
                                class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold mt-0.5"
                                :class="selectedTransaction.payment_status === 'completed' ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/30 dark:text-emerald-400' : 'bg-amber-50 text-amber-700 dark:bg-amber-950/30 dark:text-amber-400'"
                            >
                                {{ selectedTransaction.payment_status === "completed" ? "Selesai" : "Pending" }}
                            </span>
                        </div>
                    </div>

                    <div class="border-t border-border pt-4">
                        <span class="text-muted-foreground block text-xs font-medium uppercase tracking-wider">Keterangan / Catatan</span>
                        <p class="text-foreground mt-1 bg-muted/30 p-2.5 rounded-md border border-border/50 min-h-[60px] whitespace-pre-line italic">
                            {{ selectedTransaction.description || 'Tidak ada keterangan.' }}
                        </p>
                    </div>

                    <div class="grid grid-cols-2 gap-4 border-t border-border pt-4">
                        <div>
                            <span class="text-muted-foreground block text-xs font-medium uppercase tracking-wider">Dibuat Oleh</span>
                            <span class="font-medium text-foreground">
                                {{ selectedTransaction.user?.name || 'Sistem' }}
                            </span>
                        </div>
                        <div>
                            <span class="text-muted-foreground block text-xs font-medium uppercase tracking-wider">Waktu Input</span>
                            <span class="font-medium text-foreground">
                                {{ new Date(selectedTransaction.created_at).toLocaleString("id-ID", { dateStyle: 'short', timeStyle: 'short' }) }}
                            </span>
                        </div>
                    </div>
                </div>

                <DialogFooter class="mt-6">
                    <Button variant="outline" @click="showDetailDialog = false">
                        Tutup
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
