<script setup lang="ts">
import Breadcrumbs from "@/components/Breadcrumbs.vue";
import { SidebarTrigger } from "@/components/ui/sidebar";
import type { BreadcrumbItem } from "@/types";
import { useHandTracking } from "@/composables/useHandTracking";
import { RefreshCw } from "lucide-vue-next";

withDefaults(
	defineProps<{
		breadcrumbs?: BreadcrumbItem[];
	}>(),
	{
		breadcrumbs: () => [],
	},
);

const { handTrackingEnabled, isHandModelLoading, toggleHandTracking } = useHandTracking();
</script>

<template>
    <header
        class="flex h-16 shrink-0 items-center gap-2 border-b border-sidebar-border/70 px-6 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12 md:px-4"
    >
        <div class="flex items-center gap-2 w-full justify-between">
            <div class="flex items-center gap-2">
                <SidebarTrigger class="-ml-1" />
                <template v-if="breadcrumbs && breadcrumbs.length > 0">
                    <Breadcrumbs :breadcrumbs="breadcrumbs" />
                </template>
            </div>
            
            <div class="flex items-center gap-2">
                <button
                    type="button"
                    @click="toggleHandTracking"
                    class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-[11px] font-semibold border transition cursor-pointer select-none"
                    :class="[
                        handTrackingEnabled 
                            ? 'bg-orange-500 border-orange-600 text-white hover:bg-orange-600' 
                            : 'bg-neutral-100 dark:bg-neutral-800 border-neutral-200 dark:border-neutral-700 text-neutral-600 dark:text-neutral-300 hover:bg-neutral-200 dark:hover:bg-neutral-700'
                    ]"
                >
                    <RefreshCw v-if="isHandModelLoading" class="h-3 w-3 animate-spin" />
                    <span v-else class="h-1.5 w-1.5 rounded-full" :class="handTrackingEnabled ? 'bg-white animate-ping' : 'bg-neutral-400 dark:bg-neutral-500'"></span>
                    Hand Tracking: {{ handTrackingEnabled ? 'Aktif' : 'Nonaktif' }}
                </button>
            </div>
        </div>
    </header>
</template>
