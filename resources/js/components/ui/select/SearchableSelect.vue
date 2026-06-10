<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from "vue";
import { ChevronDown, Search, Check, X } from "lucide-vue-next";

const props = defineProps<{
	modelValue: string | number | boolean | null | undefined;
	options: { value: string | number | boolean; label: string }[];
	placeholder?: string;
	disabled?: boolean;
}>();

const emit = defineEmits(["update:modelValue"]);

const isOpen = ref(false);
const searchQuery = ref("");
const dropdownRef = ref<HTMLElement | null>(null);

// Get currently selected option
const selectedOption = computed(() => {
	return props.options.find(opt => opt.value === props.modelValue);
});

// Filter options based on search query
const filteredOptions = computed(() => {
	if (!searchQuery.value) return props.options;
	return props.options.filter(opt =>
		opt.label.toLowerCase().includes(searchQuery.value.toLowerCase())
	);
});

const toggleDropdown = () => {
	if (props.disabled) return;
	isOpen.value = !isOpen.value;
	if (isOpen.value) {
		searchQuery.value = "";
	}
};

const selectOption = (value: string | number | boolean) => {
	emit("update:modelValue", value);
	isOpen.value = false;
};

const clearSelection = (e: Event) => {
	e.stopPropagation();
	emit("update:modelValue", "");
};

// Handle clicks outside of the dropdown to close it
const handleClickOutside = (event: MouseEvent) => {
	if (dropdownRef.value && !dropdownRef.value.contains(event.target as Node)) {
		isOpen.value = false;
	}
};

onMounted(() => {
	document.addEventListener("click", handleClickOutside);
});

onUnmounted(() => {
	document.removeEventListener("click", handleClickOutside);
});
</script>

<template>
    <div ref="dropdownRef" class="relative w-full">
        <!-- Trigger button -->
        <button
            type="button"
            @click="toggleDropdown"
            :disabled="disabled"
            class="flex h-10 w-full items-center justify-between rounded-lg border border-input bg-background px-3 py-2 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 text-left"
        >
            <span v-if="selectedOption" class="truncate font-medium text-slate-900 dark:text-slate-100">
                {{ selectedOption.label }}
            </span>
            <span v-else class="text-muted-foreground truncate">
                {{ placeholder || 'Pilih opsi...' }}
            </span>

            <div class="flex items-center gap-1.5 shrink-0 ml-2">
                <!-- Clear Button if selected and enabled -->
                <span 
                    v-if="selectedOption && !disabled" 
                    @click="clearSelection" 
                    class="p-0.5 hover:bg-slate-100 dark:hover:bg-slate-800 rounded text-muted-foreground hover:text-slate-900 transition-colors cursor-pointer"
                >
                    <X class="h-3.5 w-3.5" />
                </span>
                <ChevronDown class="h-4 w-4 text-muted-foreground transition-transform duration-200" :class="{ 'rotate-180': isOpen }" />
            </div>
        </button>

        <!-- Dropdown Popover menu -->
        <div
            v-if="isOpen"
            class="absolute z-50 mt-1.5 max-h-60 w-full overflow-hidden rounded-lg border border-border bg-popover text-popover-foreground shadow-lg animate-in fade-in slide-in-from-top-1 duration-150 flex flex-col bg-white dark:bg-slate-950"
        >
            <!-- Search Input inside Popover -->
            <div class="relative flex items-center border-b border-border p-2 bg-slate-50/50 dark:bg-slate-900/50 shrink-0">
                <Search class="absolute left-3 h-4 w-4 text-muted-foreground" />
                <input
                    v-model="searchQuery"
                    type="text"
                    placeholder="Cari..."
                    class="w-full rounded-md border border-input bg-background py-1.5 pr-3 pl-9 text-xs focus:outline-none focus:ring-1 focus:ring-brand-500"
                    @click.stopPropagation
                />
            </div>

            <!-- Options List scroll container -->
            <div class="overflow-y-auto flex-1 py-1 max-h-48">
                <div v-if="filteredOptions.length === 0" class="px-3 py-3 text-center text-xs text-muted-foreground">
                    Tidak ditemukan hasil.
                </div>
                
                <button
                    v-else
                    v-for="opt in filteredOptions"
                    :key="String(opt.value)"
                    type="button"
                    @click="selectOption(opt.value)"
                    class="flex w-full items-center justify-between px-3 py-2 text-left text-sm hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors"
                    :class="{ 'bg-slate-50 dark:bg-slate-900 font-semibold text-brand-600': opt.value === modelValue }"
                >
                    <span class="truncate">{{ opt.label }}</span>
                    <Check v-if="opt.value === modelValue" class="h-4 w-4 text-brand-600 shrink-0 ml-2" />
                </button>
            </div>
        </div>
    </div>
</template>
