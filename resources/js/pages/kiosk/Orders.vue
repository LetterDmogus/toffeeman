<script setup lang="ts">
import { Head, Link } from "@inertiajs/vue3";
import { ChevronLeft, UtensilsCrossed } from "lucide-vue-next";
import { Card, CardContent } from "@/components/ui/card";

const props = defineProps({
	token: String,
	customer: Object,
	orders: Object,
});

function formatRp(amount) {
	return new Intl.NumberFormat("id-ID", {
		style: "currency",
		currency: "IDR",
		minimumFractionDigits: 0,
	}).format(amount);
}

function formatDate(date) {
	return new Date(date).toLocaleDateString("id-ID", {
		day: "numeric",
		month: "short",
		year: "numeric",
		hour: "2-digit",
		minute: "2-digit",
	});
}

const statusMap = {
	pending: {
		label: "Menunggu",
		color:
			"bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400",
	},
	preparing: {
		label: "Dimasak",
		color:
			"bg-indigo-100 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-400",
	},
	ready: {
		label: "Siap",
		color:
			"bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400",
	},
	served: {
		label: "Disajikan",
		color:
			"bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400",
	},
	completed: {
		label: "Selesai",
		color:
			"bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400",
	},
	cancelled: {
		label: "Dibatalkan",
		color: "bg-destructive/15 text-destructive",
	},
};

function getStepIndex(status: string) {
	return ["pending", "preparing", "ready", "served"].indexOf(status);
}
</script>

<style>
@import url('https://fonts.googleapis.com/css2?family=EB+Garamond:ital,wght@0,400..800;1,400..800&display=swap');

.font-garamond {
    font-family: 'EB Garamond', serif;
}
</style>

<template>
    <Head title="Riwayat Pesanan" />
    <div class="flex flex-col min-h-[100dvh] bg-background text-foreground max-w-md md:max-w-3xl lg:max-w-5xl mx-auto relative border-x font-garamond shadow-2xl">
        <header class="sticky top-0 z-40 flex items-center justify-between px-4 md:px-6 py-3 md:py-4 bg-background/90 backdrop-blur-md border-b">
            <Link :href="`/kiosk/${token}`" class="flex items-center text-sm md:text-base font-medium text-muted-foreground hover:text-foreground">
                <ChevronLeft class="w-4 h-4 md:w-5 md:h-5 mr-1" /> Menu
            </Link>
            <div class="font-bold text-base md:text-xl">Riwayat Pesanan</div>
            <div class="w-[60px]"></div>
        </header>

        <main class="flex-1 p-4 md:p-6 pb-12">
            <div class="flex items-center gap-4 p-4 md:p-6 rounded-2xl bg-secondary border mb-6">
                <div class="w-12 h-12 md:w-16 md:h-16 rounded-full bg-primary/10 flex items-center justify-center text-primary font-bold text-xl md:text-2xl">
                    {{ customer.name[0].toUpperCase() }}
                </div>
                <div>
                    <div class="font-bold text-lg md:text-xl">{{ customer.name }}</div>
                    <div class="text-sm md:text-base text-muted-foreground">{{ customer.email ?? customer.phone ?? '' }}</div>
                </div>
            </div>

            <div v-if="orders.data.length === 0" class="text-center py-16 md:py-24 text-muted-foreground">
                <UtensilsCrossed class="w-12 h-12 md:w-16 md:h-16 mx-auto mb-4 opacity-20" />
                <p class="text-lg md:text-xl">Belum ada pesanan</p>
            </div>

            <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 md:gap-6">
                <Card v-for="order in orders.data" :key="order.id" class="overflow-hidden shadow-sm">
                    <CardContent class="p-4 md:p-5">
                        <div class="flex justify-between items-start mb-2 md:mb-3">
                            <div>
                                <div class="font-bold text-base md:text-lg">{{ order.order_number }}</div>
                                <div class="text-xs md:text-sm text-muted-foreground">{{ formatDate(order.created_at) }}</div>
                            </div>
                            <div class="text-xs md:text-sm font-semibold px-2 md:px-3 py-1 md:py-1.5 rounded-md" :class="statusMap[order.status]?.color || 'bg-secondary text-secondary-foreground'">
                                {{ statusMap[order.status]?.label ?? order.status }}
                            </div>
                        </div>
                        
                        <div class="flex flex-col gap-1 md:gap-1.5 text-sm md:text-base text-muted-foreground mb-4 md:mb-5">
                            <span v-for="item in order.items" :key="item.id">
                                {{ item.qty }}× {{ item.name }}
                            </span>
                        </div>
                        
                        <!-- Stepper Progress for active order -->
                        <div v-if="getStepIndex(order.status) !== -1" class="mb-4 md:mb-5 pt-3 border-t space-y-2">
                            <div class="relative flex items-center justify-between px-1">
                                <!-- Connecting Line -->
                                <div class="absolute left-[12.5%] right-[12.5%] top-3 h-0.5 bg-muted -z-10">
                                    <div 
                                        class="h-full bg-primary transition-all duration-500" 
                                        :style="{ width: (getStepIndex(order.status) / 3 * 100) + '%' }"
                                    ></div>
                                </div>
                                
                                <!-- Steps -->
                                <div 
                                    v-for="(step, idx) in ['pending', 'preparing', 'ready', 'served']" 
                                    :key="step"
                                    class="flex flex-col items-center flex-1 relative"
                                >
                                    <div 
                                        class="w-6 h-6 rounded-full flex items-center justify-center text-[10px] font-bold transition-all duration-300"
                                        :class="getStepIndex(order.status) >= idx 
                                            ? 'bg-primary text-primary-foreground shadow-sm scale-110' 
                                            : 'bg-muted text-muted-foreground'"
                                    >
                                        <span class="relative z-10">{{ idx + 1 }}</span>
                                    </div>
                                    <span 
                                        class="text-[9px] md:text-[10px] mt-1 font-semibold text-center transition-colors"
                                        :class="getStepIndex(order.status) >= idx ? 'text-primary font-bold' : 'text-muted-foreground'"
                                    >
                                        {{ step === 'pending' ? 'Diterima' : step === 'preparing' ? 'Dimasak' : step === 'ready' ? 'Siap' : 'Disajikan' }}
                                    </span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="flex justify-between items-center pt-3 md:pt-4 border-t">
                            <span class="font-bold text-base md:text-lg">{{ formatRp(order.final_amount) }}</span>
                            <div class="text-xs md:text-sm font-medium px-2 md:px-3 py-1 md:py-1.5 rounded-md" :class="order.payment_status === 'paid' ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400' : 'bg-destructive/15 text-destructive'">
                                {{ order.payment_status === 'paid' ? 'Lunas' : 'Belum bayar' }}
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </main>
    </div>
</template>
