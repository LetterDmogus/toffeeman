<script setup lang="ts">
import { Check, CreditCard, Loader2 } from "lucide-vue-next";
import { Button } from "@/components/ui/button";
import {
	Dialog,
	DialogContent,
	DialogDescription,
	DialogFooter,
	DialogHeader,
	DialogTitle,
} from "@/components/ui/dialog";

// 1. PROPS
defineProps<{
	isOpen: boolean;
	activeOrderNumber: string;
	activeOrderFinalAmount: number | string;
	paymentSuccess: boolean;
	vaNumber: string;
	vaBank: string;
	requestedBank: string;
	qrUrl: string;
}>();

// 2. EMITS
const emit = defineEmits<(e: "close") => void>();

// Helper format uang lokal
function formatIDR(value: number | string) {
	return new Intl.NumberFormat("id-ID", {
		style: "currency",
		currency: "IDR",
	}).format(Number(value));
}
</script>

<template>
    <Dialog
        :open="isOpen"
        @update:open="(val) => { if (!val) emit('close') }"
    >
        <DialogContent class="text-center sm:max-w-md">
            <DialogHeader class="border-b pb-4">
                <DialogTitle
                    class="flex items-center justify-center gap-2 text-xl font-bold"
                >
                    <CreditCard class="h-6 w-6 text-brand-600" />
                    {{
                        vaNumber
                            ? 'Virtual Account Pembayaran'
                            : 'Scan QRIS Pembayaran'
                    }}
                </DialogTitle>
                <DialogDescription
                    class="text-xs font-semibold text-slate-500 uppercase"
                >
                    No. Pesanan: {{ activeOrderNumber }}
                </DialogDescription>
            </DialogHeader>

            <div
                class="flex flex-col items-center justify-center space-y-4 py-6"
            >
                <div
                    class="dark:bg-brand-950/20 flex flex-col items-center justify-center rounded-2xl border-2 border-dashed border-brand-200 bg-brand-50/50 p-6 dark:border-brand-900/50"
                >
                    <div
                        v-if="paymentSuccess"
                        class="flex h-60 w-60 flex-col items-center justify-center space-y-4 rounded-2xl bg-white p-4 shadow-inner dark:bg-slate-900"
                    >
                        <div
                            class="flex h-24 w-24 animate-bounce items-center justify-center rounded-full border-4 border-emerald-500 bg-emerald-50 text-emerald-500 shadow-lg shadow-emerald-100 dark:bg-emerald-950/30 dark:shadow-none"
                        >
                            <Check class="h-12 w-12 stroke-[3]" />
                        </div>
                        <span
                            class="animate-pulse text-sm font-extrabold tracking-wider text-emerald-600 uppercase dark:text-emerald-400"
                            >Pembayaran Berhasil!</span
                        >
                    </div>
                    <div
                        v-else-if="vaNumber"
                        class="flex h-60 w-60 flex-col items-center justify-center space-y-2 rounded-2xl border border-slate-100 bg-white p-4 shadow-inner dark:border-slate-800 dark:bg-slate-900"
                    >
                        <span
                            class="text-center text-xs leading-none font-bold tracking-widest text-brand-600 uppercase dark:text-brand-400"
                        >
                            <template
                                v-if="
                                    requestedBank &&
                                    requestedBank.toLowerCase() !==
                                        vaBank.toLowerCase()
                                "
                            >
                                {{ requestedBank.toUpperCase() }}<br /><span
                                    class="text-[9px] font-semibold text-slate-400"
                                    >(via
                                    {{ vaBank.toUpperCase() }} VA)</span
                                >
                            </template>
                            <template v-else>
                                {{ vaBank.toUpperCase() }}
                                {{
                                    vaBank.toLowerCase() === 'mandiri'
                                        ? 'BILL'
                                        : 'VA'
                                }}
                            </template>
                        </span>
                        <div
                            v-if="
                                vaBank.toLowerCase() === 'mandiri' &&
                                vaNumber.includes(' - ')
                            "
                            class="w-full space-y-2"
                        >
                            <div
                                class="dark:bg-brand-950/30 w-full rounded-xl bg-brand-50 p-2 text-center"
                            >
                                <span
                                    class="mb-0.5 block text-[9px] font-black text-slate-400 uppercase"
                                    >Kode Perusahaan (Biller)</span
                                >
                                <span
                                    class="block font-mono text-sm font-black tracking-wider text-slate-800 select-all dark:text-slate-200"
                                    >{{ vaNumber.split(' - ')[0] }}</span
                                >
                            </div>
                            <div
                                class="dark:bg-brand-950/30 w-full rounded-xl bg-brand-50 p-2 text-center"
                            >
                                <span
                                    class="mb-0.5 block text-[9px] font-black text-slate-400 uppercase"
                                    >Kode Pelanggan (Bill Key)</span
                                >
                                <span
                                    class="block font-mono text-base font-black tracking-wider text-slate-800 select-all dark:text-slate-200"
                                    >{{ vaNumber.split(' - ')[1] }}</span
                                >
                            </div>
                        </div>
                        <div
                            v-else
                            class="dark:bg-brand-950/30 w-full rounded-xl bg-brand-50 p-3 text-center"
                        >
                            <span
                                class="block font-mono text-xl font-black tracking-wider text-slate-800 select-all dark:text-slate-200"
                                >{{ vaNumber }}</span
                            >
                        </div>
                        <p
                            v-if="
                                requestedBank &&
                                requestedBank.toLowerCase() !==
                                    vaBank.toLowerCase()
                            "
                            class="text-center text-[8px] leading-tight font-extrabold text-amber-600 uppercase dark:text-amber-400"
                        >
                            *PENTING: Pilih bank tujuan "BANK
                            {{ vaBank.toUpperCase() }}" saat transfer
                        </p>
                        <p
                            class="text-center text-[10px] leading-tight font-bold text-slate-400 uppercase"
                        >
                            Salin nomor di atas untuk simulasi pembayaran
                        </p>
                    </div>
                    <img
                        v-else-if="qrUrl"
                        :src="qrUrl"
                        alt="QRIS Code"
                        class="h-60 w-60 rounded-2xl object-contain mix-blend-multiply dark:mix-blend-normal"
                    />
                    <div
                        v-else
                        class="flex h-60 w-60 items-center justify-center rounded-2xl bg-white text-slate-400"
                    >
                        <Loader2
                            class="h-8 w-8 animate-spin text-brand-500"
                        />
                    </div>

                    <span
                        class="mt-4 block text-xs font-bold tracking-widest text-slate-500 uppercase"
                        >Total Tagihan</span
                    >
                    <span
                        class="text-3xl font-black text-brand-600 dark:text-brand-400"
                    >{{ formatIDR(activeOrderFinalAmount) }}</span
                    >
                </div>
            </div>

            <DialogFooter class="border-t pt-4">
                <Button
                    @click="emit('close')"
                    class="h-12 w-full rounded-xl bg-brand-600 font-bold tracking-wider text-white uppercase hover:bg-brand-700"
                >
                    Selesai / Tutup
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
