<script setup lang="ts">
import { Link } from "@inertiajs/vue3";
import { ref } from "vue";
import { ChevronDown, ChevronRight } from "lucide-vue-next";
import {
	SidebarGroup,
	SidebarGroupLabel,
	SidebarMenu,
	SidebarMenuButton,
	SidebarMenuItem,
} from "@/components/ui/sidebar";
import { useCurrentUrl } from "@/composables/useCurrentUrl";
import type { NavItem } from "@/types";

defineProps<{
	items: NavItem[];
	label?: string;
}>();

const { isCurrentUrl } = useCurrentUrl();
const isCollapsed = ref(true);

const toggleCollapse = () => {
	isCollapsed.value = !isCollapsed.value;
};
</script>

<template>
    <SidebarGroup class="px-2 py-0">
        <SidebarGroupLabel 
            v-if="label" 
            @click="toggleCollapse"
            class="flex items-center justify-between cursor-pointer select-none py-2 hover:text-slate-900 dark:hover:text-slate-100 transition-colors"
        >
            <span>{{ label }}</span>
            <component 
                :is="isCollapsed ? ChevronRight : ChevronDown" 
                class="h-3.5 w-3.5 shrink-0 text-muted-foreground ml-1"
            />
        </SidebarGroupLabel>
        
        <SidebarMenu v-show="!isCollapsed" class="transition-all duration-300">
            <SidebarMenuItem v-for="item in items" :key="item.title">
                <SidebarMenuButton
                    as-child
                    :is-active="isCurrentUrl(item.href)"
                    :tooltip="item.title"
                >
                    <Link :href="item.href">
                        <component :is="item.icon" />
                        <span>{{ item.title }}</span>
                    </Link>
                </SidebarMenuButton>
            </SidebarMenuItem>
        </SidebarMenu>
    </SidebarGroup>
</template>
