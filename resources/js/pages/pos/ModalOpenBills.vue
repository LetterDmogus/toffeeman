<script setup lang="ts">
import { Badge, Receipt, ShoppingCart, X } from "lucide-vue-next";
import { Button } from "@/components/ui/button";
import { Separator } from "@/components/ui/separator";

// 1. Tentukan PROPS (Data yang dikirim dari Induk ke Anak)
defineProps<{
	isOpen: boolean;
	openBills: any[];
}>();

// 2. Tentukan EMITS (Event/Sinyal yang dikirim dari Anak ke Induk)
const emit = defineEmits<{
	(e: "close"): void;
	(e: "select", bill: any): void;
	(e: "pay-directly", bill: any): void;
}>();

// Helper format uang lokal di komponen ini jika diperlukan
function formatIDR(value: number | string) {
	return new Intl.NumberFormat("id-ID", {
		style: "currency",
		currency: "IDR",
	}).format(Number(value));
}
</script>

<template>
<div
    v-if="isOpen"
    class="fixed inset-0 z-50 flex items-center justify-center p-8"
>
    <div
        class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm"
        @click="emit('close')"
    ></div>
    <div
        class="relative flex h-[70vh] w-full max-w-4xl animate-in flex-col overflow-hidden rounded-2xl bg-white shadow-2xl duration-300 zoom-in-95"
    >
        <div
            class="flex items-center justify-between p-8 text-brand-900"
        >
            <div>
                <h2
                    class="text-2xl font-black tracking-tight uppercase"
                >
                    Open Bills
                </h2>
                <p
                    class="text-xs font-bold tracking-widest text-slate-400 uppercase"
                >
                    Tagihan yang sedang berjalan
                </p>
            </div>
            <Button
                variant="ghost"
                @click="emit('close')"
                class="h-12 w-12 rounded-full text-slate-600 hover:bg-slate-100"
                ><X class="h-6 w-6"
            /></Button>
        </div>
        <div
            class="custom-scrollbar flex-1 overflow-y-auto bg-slate-50 p-8"
        >
            <div
                v-if="openBills.length === 0"
                class="flex h-full flex-col items-center justify-center text-slate-300"
            >
                <Receipt class="mb-4 h-20 w-20 opacity-20" />
                <p class="font-black tracking-widest uppercase">
                    Tidak ada tagihan aktif
                </p>
            </div>
            <div v-else class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <div
                    v-for="bill in openBills"
                    :key="bill.id"
                    @click="emit('select', bill)"
                    class="group cursor-pointer rounded-3xl border-2 border-slate-100 bg-white p-6 shadow-sm transition-all hover:border-brand-600"
                >
                    <div class="mb-4 flex items-start justify-between">
                        <div>
                            <Badge
                                class="mb-2 border-brand-100 bg-brand-50 text-[10px] font-black text-brand-600"
                                >{{ bill.order_number }}</Badge
                            >
                            <h4
                                class="text-lg leading-none font-black text-slate-800 uppercase"
                            >
                                {{
                                    bill.table
                                        ? 'Meja ' + bill.table.number
                                        : 'Take Away'
                                }}
                            </h4>
                        </div>
                        <p class="text-lg font-black text-brand-600">
                            {{ formatIDR(bill.final_amount) }}
                        </p>
                    </div>
                    <Separator class="my-4 bg-slate-50" />
                    <div class="flex items-center justify-between">
                        <div
                            class="flex items-center gap-2 text-[10px] font-bold tracking-widest text-slate-400 uppercase"
                        >
                            <ShoppingCart class="h-3 w-3" />
                            {{ bill.items.length }} Item
                        </div>
                        <div class="flex items-center gap-2">
                            <Button
                                @click.stop="emit('pay-directly', bill)"
                                size="sm"
                                class="h-8 rounded-xl bg-brand-600 px-3 text-[10px] font-black text-white uppercase hover:bg-brand-700"
                                >Bayar</Button
                            >
                            <Button
                                variant="ghost"
                                class="h-8 rounded-lg text-[10px] font-black text-brand-600 uppercase group-hover:bg-brand-50"
                                >Edit &rarr;</Button
                            >
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
</template>
