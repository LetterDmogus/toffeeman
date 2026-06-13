<script setup lang="ts" generic="T extends Record<string, unknown>">
import {
	AlertCircle,
	ArrowLeft,
	Check,
	ChevronLeft,
	ChevronRight,
	ChevronsLeft,
	ChevronsRight,
	Loader2,
	Pencil,
	Plus,
	RotateCcw,
	Search,
	SlidersHorizontal,
	Trash,
	Trash2,
	X,
	History,
} from "lucide-vue-next";
import { computed, ref, watch } from "vue";
import { usePage } from "@inertiajs/vue3";
import ImageUploader from "@/components/ImageUploader.vue";
import SearchableSelect from "@/components/ui/select/SearchableSelect.vue";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import {
	Dialog,
	DialogContent,
	DialogDescription,
	DialogFooter,
	DialogHeader,
	DialogTitle,
} from "@/components/ui/dialog";
import {
	DropdownMenu,
	DropdownMenuContent,
	DropdownMenuItem,
	DropdownMenuLabel,
	DropdownMenuSeparator,
	DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";

// ─── Column definition ────────────────────────────────────────────────────────
export type Column<Row> = {
	key: keyof Row & string;
	label: string;
	sortable?: boolean;
	/** Optional render function for custom display */
	render?: (value: unknown, row: Row) => string;
};

// ─── Field definition for the form ───────────────────────────────────────────
export type FormField = {
	key: string;
	label: string;
	type:
		| "text"
		| "number"
		| "textarea"
		| "select"
		| "url"
		| "date"
		| "image"
		| "phone"
		| "tel"
		| "tags";
	placeholder?: string;
	required?: boolean;
	options?: { value: string | number | boolean; label: string }[];
	advanced?: boolean;
	disableOnEdit?: boolean;
	disabled?: boolean;
};

// ─── Paginated response shape ─────────────────────────────────────────────────
type PaginatedResponse<Row> = {
	data: Row[];
	current_page: number;
	last_page: number;
	per_page: number;
	total: number;
	from: number | null;
	to: number | null;
};

const props = withDefaults(
	defineProps<{
		/** Resource label (singular), e.g. "Menu Item" */
		resourceName: string;
		/** API base URL, e.g. "/api/menu-items" */
		apiUrl: string;
		/** Column definitions */
		columns: Column<T>[];
		/** Form field definitions for Create / Edit */
		formFields: FormField[];
		/** Optional badge-coloring map for string values */
		badgeMap?: Record<string, string>;
		/** Key to use as unique identifier (default: "id") */
		idKey?: keyof T & string;
		/** Default visible columns */
		defaultVisibleColumns?: string[];
		disableEdit?: boolean;
		disableDelete?: boolean;
		auditableType?: string;
	}>(),
	{
		idKey: "id",
		disableEdit: false,
		disableDelete: false,
	},
);

const emit = defineEmits<{
	(e: "saved", data: any): void;
	(e: "deleted", data: any): void;
	(e: "formOpened", data: { mode: "create" | "edit"; row: any }): void;
}>();

// ─── State ────────────────────────────────────────────────────────────────────
const rows = ref<T[]>([]) as { value: T[] };
const pagination = ref({
	current_page: 1,
	last_page: 1,
	total: 0,
	from: null as number | null,
	to: null as number | null,
});
const search = ref("");
const loading = ref(false);
const error = ref("");
const page = ref(1);

// Column visibility state
const visibleKeys = ref<string[]>(
	props.defaultVisibleColumns && props.defaultVisibleColumns.length > 0
		? [...props.defaultVisibleColumns]
		: props.columns.map((c) => c.key),
);

watch(
	() => props.columns,
	(newCols) => {
		if (
			!props.defaultVisibleColumns ||
			props.defaultVisibleColumns.length === 0
		) {
			visibleKeys.value = newCols.map((c) => c.key);
		}
	},
	{ deep: true },
);

const activeColumns = computed(() => {
	return props.columns.filter((col) => visibleKeys.value.includes(col.key));
});

function toggleColumnVisibility(key: string, checked: boolean) {
	if (checked) {
		if (!visibleKeys.value.includes(key)) {
			visibleKeys.value = [...visibleKeys.value, key];
		}
	} else {
		if (visibleKeys.value.length > 1) {
			visibleKeys.value = visibleKeys.value.filter((k) => k !== key);
		}
	}
}

// Bulk Selection State
const selectedIds = ref<Array<string | number>>([]);
const bulkActionLoading = ref(false);
const bulkDeleteDialogOpen = ref(false);
const bulkForceDeleteDialogOpen = ref(false);

// View mode state ('table' | 'form')
const viewMode = ref<"table" | "form">("table");
const dialogMode = ref<"create" | "edit">("create");
const editingRow = ref<T | null>(null);
const formData = ref<Record<string, unknown>>({});
const formErrors = ref<Record<string, string>>({});
const submitting = ref(false);

// Delete confirmation state
const deleteDialogOpen = ref(false);
const deletingRow = ref<T | null>(null);
const deleting = ref(false);

// Soft Delete trash mode state
const showTrash = ref(false);
const restoring = ref(false);
const forceDeleteDialogOpen = ref(false);
const forceDeletingRow = ref<T | null>(null);
const forceDeleting = ref(false);

// Advanced collapsible state
const showAdvanced = ref(false);

// ─── Fetch ────────────────────────────────────────────────────────────────────
async function fetchData() {
	loading.value = true;
	error.value = "";
	selectedIds.value = []; // Reset selection on fetch

	try {
		const params = new URLSearchParams({
			page: String(page.value),
			per_page: "12",
		});

		if (showTrash.value) {
			params.set("trash", "true");
		}

		if (search.value) {
			params.set("search", search.value);
		}

		const res = await fetch(`${props.apiUrl}?${params}`, {
			headers: { Accept: "application/json" },
		});

		if (!res.ok) {
			throw new Error("Failed to fetch data.");
		}

		const json = (await res.json()) as PaginatedResponse<T>;
		rows.value = json.data;
		pagination.value = {
			current_page: json.current_page,
			last_page: json.last_page,
			total: json.total,
			from: json.from,
			to: json.to,
		};
	} catch (e) {
		error.value = (e as Error).message;
	} finally {
		loading.value = false;
	}
}

// Debounce search
let searchTimer: ReturnType<typeof setTimeout>;
watch(search, () => {
	clearTimeout(searchTimer);
	searchTimer = setTimeout(() => {
		page.value = 1;
		fetchData();
	}, 350);
});

// Watch page change without firing on initial load
watch(page, () => {
	fetchData();
});

// Automatically calculate total stock (qty) if good/fair/damaged quantities are defined
watch(
	[
		() => formData.value.qty_good,
		() => formData.value.qty_fair,
		() => formData.value.qty_damaged,
	],
	([good, fair, damaged]) => {
		if (good !== undefined || fair !== undefined || damaged !== undefined) {
			formData.value.qty =
				(Number(good) || 0) + (Number(fair) || 0) + (Number(damaged) || 0);
		}
	},
);

fetchData();

// ─── Bulk Actions Logic ──────────────────────────────────────────────────────
const isAllSelected = computed(() => {
	return (
		rows.value.length > 0 &&
		rows.value.every((row) =>
			selectedIds.value.includes(
				(row as Record<string, unknown>)[props.idKey] as string | number,
			),
		)
	);
});

const isSomeSelected = computed(() => {
	return selectedIds.value.length > 0 && !isAllSelected.value;
});

const selectAllState = computed(() => {
	if (isAllSelected.value) {
		return true;
	}

	if (isSomeSelected.value) {
		return "indeterminate";
	}

	return false;
});

function toggleSelectAll(checked: boolean | "indeterminate") {
	if (checked === true) {
		selectedIds.value = rows.value.map(
			(row) => (row as Record<string, unknown>)[props.idKey] as string | number,
		);
	} else {
		selectedIds.value = [];
	}
}

function toggleSelect(row: T, checked: boolean | "indeterminate") {
	const id = (row as Record<string, unknown>)[props.idKey] as string | number;

	if (checked === true) {
		if (!selectedIds.value.includes(id)) {
			selectedIds.value = [...selectedIds.value, id];
		}
	} else {
		selectedIds.value = selectedIds.value.filter((item) => item !== id);
	}
}

// Remove watcher

async function executeBulkAction(action: "delete" | "restore" | "force") {
	bulkActionLoading.value = true;
	error.value = "";

	try {
		const url = `${props.apiUrl}/bulk-${action}`;
		const method = action === "restore" ? "PUT" : "DELETE";

		const res = await fetch(url, {
			method,
			headers: {
				"Content-Type": "application/json",
				Accept: "application/json",
				"X-CSRF-TOKEN": getCsrfToken(),
			},
			body: JSON.stringify({ ids: selectedIds.value }),
		});

		if (!res.ok) {
			throw new Error(`Bulk ${action} failed.`);
		}

		bulkDeleteDialogOpen.value = false;
		bulkForceDeleteDialogOpen.value = false;
		await fetchData();
		emit("saved", res);
	} catch (e) {
		error.value = (e as Error).message;
	} finally {
		bulkActionLoading.value = false;
	}
}

// ─── Create ───────────────────────────────────────────────────────────────────
function openCreate() {
	dialogMode.value = "create";
	editingRow.value = null;
	formData.value = {};
	formErrors.value = {};
	showAdvanced.value = false;
	viewMode.value = "form";
	emit("formOpened", { mode: "create", row: null });
}

// ─── Edit ─────────────────────────────────────────────────────────────────────
function openEdit(row: T) {
	dialogMode.value = "edit";
	editingRow.value = row;
	const data = { ...(row as Record<string, unknown>) };
	props.formFields.forEach((field) => {
		if (field.type === "tags" && Array.isArray(data[field.key])) {
			data[field.key] = (data[field.key] as string[]).join(", ");
		}
	});
	formData.value = data;
	formErrors.value = {};
	showAdvanced.value = false;
	viewMode.value = "form";
	emit("formOpened", { mode: "edit", row });
}

// ─── Authorization ────────────────────────────────────────────────────────────
const pageInstance = usePage();
const hasPermission = (permission: string) => {
	const permissions = (pageInstance.props.auth as any)?.permissions ?? [];
	return permissions.includes(permission);
};
const canViewAuditLogs = computed(() => hasPermission("view-audit-logs"));
const canViewTrash = computed(() => hasPermission("view-trash"));

function cancelForm() {
	viewMode.value = "table";
	formData.value = {};
	formErrors.value = {};
	showAdvanced.value = false;
}

// ─── CSRF helper ─────────────────────────────────────────────────────────────
function getCsrfToken(): string {
	return (
		(document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)
			?.content ?? ""
	);
}

// ─── Submit ───────────────────────────────────────────────────────────────────
async function submitForm() {
	submitting.value = true;
	formErrors.value = {};

	try {
		const isEdit = dialogMode.value === "edit";
		const id = isEdit
			? (editingRow.value as Record<string, unknown>)[props.idKey]
			: null;
		const url = isEdit ? `${props.apiUrl}/${id}` : props.apiUrl;
		const method = isEdit ? "PATCH" : "POST";

		const payload = { ...formData.value };
		props.formFields.forEach((field) => {
			if (field.type === "tags") {
				if (typeof payload[field.key] === "string") {
					payload[field.key] = (payload[field.key] as string)
						.split(",")
						.map((s) => s.trim())
						.filter(Boolean);
				} else if (!payload[field.key]) {
					payload[field.key] = [];
				}
			}
		});

		const res = await fetch(url, {
			method,
			headers: {
				"Content-Type": "application/json",
				Accept: "application/json",
				"X-CSRF-TOKEN": getCsrfToken(),
			},
			body: JSON.stringify(payload),
		});

		if (res.status === 422) {
			const json = (await res.json()) as {
				errors: Record<string, string[]>;
			};
			Object.entries(json.errors).forEach(([key, msgs]) => {
				formErrors.value[key] = msgs[0];
			});

			return;
		}

		if (!res.ok) {
			throw new Error("Request failed.");
		}

		viewMode.value = "table";
		await fetchData();
		emit("saved", res);
	} catch (e) {
		error.value = (e as Error).message;
	} finally {
		submitting.value = false;
	}
}

// ─── Delete ───────────────────────────────────────────────────────────────────
function confirmDelete(row: T) {
	deletingRow.value = row;
	deleteDialogOpen.value = true;
}

async function executeDelete() {
	if (!deletingRow.value) {
		return;
	}

	deleting.value = true;

	try {
		const id = (deletingRow.value as Record<string, unknown>)[props.idKey];
		const res = await fetch(`${props.apiUrl}/${id}`, {
			method: "DELETE",
			headers: {
				Accept: "application/json",
				"X-CSRF-TOKEN": getCsrfToken(),
			},
		});

		if (!res.ok) {
			throw new Error("Delete failed.");
		}

		deleteDialogOpen.value = false;
		await fetchData();
		emit("deleted", deletingRow.value);
	} catch (e) {
		error.value = (e as Error).message;
	} finally {
		deleting.value = false;
	}
}

watch(showTrash, () => {
	page.value = 1;
	fetchData();
});

async function restoreRow(row: T) {
	restoring.value = true;
	error.value = "";

	try {
		const id = (row as Record<string, unknown>)[props.idKey];
		const res = await fetch(`${props.apiUrl}/${id}/restore`, {
			method: "PUT",
			headers: {
				Accept: "application/json",
				"X-CSRF-TOKEN": getCsrfToken(),
			},
		});

		if (!res.ok) {
			throw new Error("Restore failed.");
		}

		await fetchData();
		emit("saved", res);
	} catch (e) {
		error.value = (e as Error).message;
	} finally {
		restoring.value = false;
	}
}

function confirmForceDelete(row: T) {
	forceDeletingRow.value = row;
	forceDeleteDialogOpen.value = true;
}

async function executeForceDelete() {
	if (!forceDeletingRow.value) {
		return;
	}

	forceDeleting.value = true;
	error.value = "";

	try {
		const id = (forceDeletingRow.value as Record<string, unknown>)[props.idKey];
		const res = await fetch(`${props.apiUrl}/${id}/force`, {
			method: "DELETE",
			headers: {
				Accept: "application/json",
				"X-CSRF-TOKEN": getCsrfToken(),
			},
		});

		if (!res.ok) {
			throw new Error("Permanent delete failed.");
		}

		forceDeleteDialogOpen.value = false;
		await fetchData();
		emit("deleted", forceDeletingRow.value);
	} catch (e) {
		error.value = (e as Error).message;
	} finally {
		forceDeleting.value = false;
	}
}

// ─── Badge color helper ───────────────────────────────────────────────────────
function badgeVariant(
	value: string,
): "default" | "secondary" | "destructive" | "outline" {
	if (!props.badgeMap) {
		return "secondary";
	}

	const color = props.badgeMap[value];

	switch (color) {
		case "success":
			return "default";
		case "danger":
			return "destructive";
		case "warning":
			return "outline";
		default:
			return "secondary";
	}
}

function displayValue(col: Column<T>, row: T): string {
	const val = row[col.key];

	if (col.render) {
		return col.render(val, row);
	}

	if (val === null || val === undefined) {
		return "—";
	}

	if (Array.isArray(val)) {
		return val.join(", ") || "—";
	}

	return String(val);
}

function isBadgeColumn(col: Column<T>, row: T): boolean {
	if (!props.badgeMap) {
		return false;
	}

	const val = String(row[col.key] ?? "");

	return val in props.badgeMap;
}

const totalPages = computed(() => pagination.value.last_page);

const showAuditDialog = ref(false);
const auditLogs = ref<any[]>([]);
const auditLoading = ref(false);
const selectedAuditRow = ref<any>(null);
const auditPagination = ref({
	current_page: 1,
	last_page: 1,
	total: 0,
});

const fetchAuditLogs = async (pageNumber = 1) => {
	if (!props.auditableType) {
		return;
	}
	auditLoading.value = true;
	try {
		const params = new URLSearchParams({
			auditable_type: props.auditableType,
			page: pageNumber.toString(),
		});
		if (selectedAuditRow.value) {
			params.append("auditable_id", String(selectedAuditRow.value[props.idKey]));
		}
		const res = await fetch(`/api/audit-logs?${params.toString()}`);
		if (res.ok) {
			const data = await res.json();
			auditLogs.value = data.data;
			auditPagination.value = {
				current_page: data.current_page,
				last_page: data.last_page,
				total: data.total,
			};
		}
	} catch (e) {
		console.error("Failed to fetch audit logs", e);
	} finally {
		auditLoading.value = false;
	}
};

const openAuditLogs = (row: any = null) => {
	selectedAuditRow.value = row;
	auditLogs.value = [];
	showAuditDialog.value = true;
	fetchAuditLogs(1);
};

defineExpose({
	formData,
	dialogMode,
	fetchData,
});
</script>

<template>
    <div class="flex flex-col gap-4">
        <!-- 📋 TABLE VIEW -->
        <template v-if="viewMode === 'table'">
            <!-- Toolbar -->
            <div
                class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
            >
                <div class="relative w-full sm:max-w-sm">
                    <Search
                        class="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-muted-foreground"
                    />
                    <Input
                        v-model="search"
                        :placeholder="`Cari ${resourceName.toLowerCase()}...`"
                        class="pl-9"
                    />
                </div>
                <div class="ml-auto flex shrink-0 items-center gap-2 sm:ml-0">
                    <DropdownMenu>
                        <DropdownMenuTrigger :as-child="true">
                            <Button
                                variant="outline"
                                class="flex h-10 items-center gap-2 rounded-lg border-solid"
                                title="Pilih Kolom"
                            >
                                <SlidersHorizontal
                                    class="h-4 w-4 text-muted-foreground"
                                />
                                <span>Kolom</span>
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end" class="w-56">
                            <DropdownMenuLabel
                                >Tampilkan Kolom</DropdownMenuLabel
                            >
                            <DropdownMenuSeparator />
                            <DropdownMenuItem
                                v-for="col in columns"
                                :key="col.key"
                                @select.prevent="
                                    toggleColumnVisibility(
                                        col.key,
                                        !visibleKeys.includes(col.key),
                                    )
                                "
                                class="flex cursor-pointer items-center gap-2.5 px-3 py-2"
                            >
                                <div
                                    class="flex h-4 w-4 shrink-0 items-center justify-center rounded border border-brand-600 transition-colors"
                                    :class="
                                        visibleKeys.includes(col.key)
                                            ? 'border-brand-600 bg-brand-600 text-white'
                                            : 'border-input bg-transparent'
                                    "
                                >
                                    <Check
                                        v-if="visibleKeys.includes(col.key)"
                                        class="h-3 w-3 stroke-[3]"
                                    />
                                </div>
                                <span
                                    class="text-sm font-medium text-foreground"
                                    >{{ col.label }}</span
                                >
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>

                    <Button
                        v-if="!disableDelete && canViewTrash"
                        variant="outline"
                        @click="showTrash = !showTrash"
                        class="flex h-10 items-center gap-2 rounded-lg border-dashed"
                        :class="
                            showTrash
                                ? 'border-destructive bg-destructive/10 text-destructive hover:bg-destructive/20'
                                : ''
                        "
                        title="Tampilkan Tong Sampah"
                    >
                        <Trash class="h-4 w-4" />
                        <span>{{
                            showTrash ? 'Tutup Sampah' : 'Tong Sampah'
                        }}</span>
                    </Button>
                    <Button
                        v-if="auditableType && !showTrash && canViewAuditLogs"
                        variant="outline"
                        @click="openAuditLogs()"
                        class="flex h-10 items-center gap-2 rounded-lg border border-slate-200"
                        title="Tampilkan Log Aktivitas"
                    >
                        <History class="h-4 w-4 text-slate-500" />
                        <span>Log Aktivitas</span>
                    </Button>
                    <Button
                        v-if="!showTrash"
                        @click="openCreate"
                        class="h-10 rounded-lg bg-brand-600 text-white hover:bg-brand-700"
                    >
                        <Plus class="mr-2 h-4 w-4" />
                        Tambah {{ resourceName }}
                    </Button>
                </div>
            </div>

            <!-- Bulk Actions Toolbar -->
            <div
                v-if="selectedIds.length > 0"
                class="relative z-20 mb-4 flex animate-in items-center justify-between rounded-lg border border-brand-200 bg-brand-50 px-4 py-3 text-sm shadow-sm duration-300 fade-in slide-in-from-top-2"
            >
                <div
                    class="flex items-center gap-2 font-semibold text-brand-700"
                >
                    <Check class="h-4 w-4" />
                    {{ selectedIds.length }} item terpilih
                </div>
                <div class="flex items-center gap-2">
                    <template v-if="showTrash">
                        <Button
                            size="sm"
                            variant="outline"
                            class="h-8 border-emerald-200 bg-white text-emerald-600 hover:bg-emerald-50"
                            @click="executeBulkAction('restore')"
                            :disabled="bulkActionLoading"
                        >
                            <RotateCcw class="mr-1.5 h-3.5 w-3.5" />
                            Pulihkan Massal
                        </Button>
                        <Button
                            size="sm"
                            variant="destructive"
                            class="h-8"
                            @click="bulkForceDeleteDialogOpen = true"
                            :disabled="bulkActionLoading"
                        >
                            <Trash2 class="mr-1.5 h-3.5 w-3.5" />
                            Hapus Permanen Massal
                        </Button>
                    </template>
                    <template v-else>
                        <Button
                            size="sm"
                            variant="destructive"
                            class="h-8"
                            @click="bulkDeleteDialogOpen = true"
                            :disabled="bulkActionLoading"
                        >
                            <Trash2 class="mr-1.5 h-3.5 w-3.5" />
                            Hapus Massal
                        </Button>
                    </template>
                    <Button
                        size="sm"
                        variant="ghost"
                        class="h-8 text-muted-foreground"
                        @click="selectedIds = []"
                    >
                        Batal
                    </Button>
                </div>
            </div>

            <!-- Error -->
            <div
                v-if="error"
                class="flex items-center gap-2 rounded-lg border border-destructive/30 bg-destructive/10 px-4 py-3 text-sm text-destructive"
            >
                <AlertCircle class="h-4 w-4 shrink-0" />
                {{ error }}
                <button @click="error = ''" class="ml-auto">
                    <X class="h-4 w-4" />
                </button>
            </div>

            <!-- Table Card -->
            <div
                class="relative overflow-hidden rounded-xl border bg-card shadow-sm"
            >
                <!-- Loading overlay -->
                <div
                    v-if="loading"
                    class="absolute inset-0 z-10 flex items-center justify-center bg-background/60 backdrop-blur-sm"
                >
                    <Loader2 class="h-6 w-6 animate-spin text-brand-600" />
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b bg-muted/40">
                                <th
                                    v-if="!disableDelete"
                                    class="w-[40px] px-4 py-3"
                                >
                                    <Checkbox
                                        :checked="selectAllState"
                                        @update:checked="toggleSelectAll"
                                    />
                                </th>
                                <th
                                    v-for="col in activeColumns"
                                    :key="col.key"
                                    class="px-4 py-3 text-left font-semibold whitespace-nowrap text-muted-foreground"
                                >
                                    {{ col.label }}
                                </th>
                                <th
                                    class="px-4 py-3 text-right font-semibold text-muted-foreground"
                                >
                                    Aksi
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="rows.length === 0 && !loading">
                                <td
                                    :colspan="
                                        activeColumns.length +
                                        (disableDelete ? 1 : 2)
                                    "
                                    class="px-4 py-12 text-center text-muted-foreground"
                                >
                                    Tidak ada data ditemukan.
                                </td>
                            </tr>
                            <tr
                                v-for="row in rows"
                                :key="
                                    String(
                                        (row as Record<string, unknown>)[idKey],
                                    )
                                "
                                class="border-b transition-colors last:border-0 hover:bg-muted/30"
                                :class="
                                    selectedIds.includes(
                                        (row as Record<string, unknown>)[
                                            idKey
                                        ] as string | number,
                                    )
                                        ? 'bg-brand-50/20'
                                        : ''
                                "
                            >
                                <td v-if="!disableDelete" class="px-4 py-3">
                                    <Checkbox
                                        :checked="
                                            selectedIds.includes(
                                                (
                                                    row as Record<
                                                        string,
                                                        unknown
                                                    >
                                                )[idKey] as string | number,
                                            )
                                        "
                                        @update:checked="
                                            (checked) =>
                                                toggleSelect(row, checked)
                                        "
                                    />
                                </td>
                                <td
                                    v-for="col in activeColumns"
                                    :key="col.key"
                                    class="max-w-[200px] truncate px-4 py-3"
                                >
                                    <Badge
                                        v-if="isBadgeColumn(col, row)"
                                        :variant="
                                            badgeVariant(
                                                String(row[col.key] ?? ''),
                                            )
                                        "
                                        class="capitalize"
                                    >
                                        {{ displayValue(col, row) }}
                                    </Badge>
                                    <span v-else class="text-foreground/90">{{
                                        displayValue(col, row)
                                    }}</span>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <div
                                        class="flex items-center justify-end gap-1"
                                    >
                                        <template v-if="showTrash">
                                            <Button
                                                size="icon-sm"
                                                variant="ghost"
                                                class="text-emerald-600 hover:bg-emerald-50 hover:text-emerald-700"
                                                @click="restoreRow(row)"
                                                title="Pulihkan"
                                                :disabled="restoring"
                                            >
                                                <RotateCcw class="h-4 w-4" />
                                            </Button>
                                            <Button
                                                size="icon-sm"
                                                variant="ghost"
                                                class="text-destructive hover:bg-destructive/10 hover:text-destructive"
                                                @click="confirmForceDelete(row)"
                                                title="Hapus Permanen"
                                            >
                                                <Trash2 class="h-4 w-4" />
                                            </Button>
                                        </template>
                                        <template v-else>
                                            <slot name="actions" :row="row" />
                                            <Button
                                                v-if="auditableType && canViewAuditLogs"
                                                size="icon-sm"
                                                variant="ghost"
                                                class="text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200"
                                                @click="openAuditLogs(row)"
                                                title="Riwayat Perubahan"
                                            >
                                                <History class="h-4 w-4" />
                                            </Button>
                                            <Button
                                                v-if="!disableEdit"
                                                size="icon-sm"
                                                variant="ghost"
                                                @click="openEdit(row)"
                                                title="Edit"
                                            >
                                                <Pencil class="h-4 w-4" />
                                            </Button>
                                            <Button
                                                v-if="!disableDelete"
                                                size="icon-sm"
                                                variant="ghost"
                                                class="text-destructive hover:text-destructive"
                                                @click="confirmDelete(row)"
                                                title="Hapus"
                                            >
                                                <Trash2 class="h-4 w-4" />
                                            </Button>
                                        </template>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div
                    v-if="pagination.total > 0"
                    class="flex items-center justify-between border-t px-4 py-3"
                >
                    <p class="text-xs text-muted-foreground">
                        Menampilkan {{ pagination.from }}–{{
                            pagination.to
                        }}
                        dari {{ pagination.total }} data
                    </p>
                    <div class="flex items-center gap-1">
                        <Button
                            size="icon-sm"
                            variant="outline"
                            :disabled="page <= 1"
                            @click="page = 1"
                            ><ChevronsLeft class="h-4 w-4"
                        /></Button>
                        <Button
                            size="icon-sm"
                            variant="outline"
                            :disabled="page <= 1"
                            @click="page--"
                            ><ChevronLeft class="h-4 w-4"
                        /></Button>
                        <span
                            class="min-w-[3rem] text-center text-xs text-muted-foreground"
                        >
                            {{ page }} / {{ totalPages }}
                        </span>
                        <Button
                            size="icon-sm"
                            variant="outline"
                            :disabled="page >= totalPages"
                            @click="page++"
                            ><ChevronRight class="h-4 w-4"
                        /></Button>
                        <Button
                            size="icon-sm"
                            variant="outline"
                            :disabled="page >= totalPages"
                            @click="page = totalPages"
                            ><ChevronsRight class="h-4 w-4"
                        /></Button>
                    </div>
                </div>
            </div>
        </template>

        <!-- 📝 FORM VIEW (In-place transitions instead of modal) -->
        <template v-else>
            <slot
                v-if="$slots.form"
                name="form"
                :mode="dialogMode"
                :row="editingRow"
                :submitting="submitting"
                :cancel="cancelForm"
                :refresh="fetchData"
            />
            <div
                v-else
                class="animate-in rounded-xl border bg-card p-6 shadow-sm duration-300 fade-in slide-in-from-bottom-4"
            >
                <div class="mb-6 flex items-center gap-3 border-b pb-4">
                    <Button variant="ghost" size="icon-sm" @click="cancelForm">
                        <ArrowLeft class="h-4 w-4" />
                    </Button>
                    <div>
                        <h2 class="text-lg font-bold text-foreground">
                            {{ dialogMode === 'create' ? 'Tambah' : 'Edit' }}
                            {{ resourceName }}
                        </h2>
                        <p class="text-xs text-muted-foreground">
                            {{
                                dialogMode === 'create'
                                    ? 'Isi form di bawah untuk menambah data master baru.'
                                    : 'Perbarui field data yang ingin diubah.'
                            }}
                        </p>
                    </div>
                </div>

                <form
                    @submit.prevent="submitForm"
                    class="grid gap-6 md:grid-cols-2"
                >
                    <!-- Primary Fields (non-advanced) -->
                    <div
                        v-for="field in formFields.filter((f) => !f.advanced)"
                        :key="field.key"
                        class="flex flex-col gap-1.5"
                        :class="
                            ['textarea', 'image'].includes(field.type)
                                ? 'md:col-span-2'
                                : ''
                        "
                    >
                        <Label
                            v-if="field.type !== 'image'"
                            :for="`field-${field.key}`"
                            class="text-sm font-semibold text-foreground/80"
                        >
                            {{ field.label }}
                            <span v-if="field.required" class="text-destructive"
                                >*</span
                            >
                        </Label>

                        <ImageUploader
                            v-if="field.type === 'image'"
                            v-model="formData[field.key]"
                            :label="field.label"
                            :placeholder="field.placeholder"
                            :is-face-photo="field.key === 'face_photo_path'"
                        />

                        <!-- Textarea -->
                        <textarea
                            v-else-if="field.type === 'textarea'"
                            :id="`field-${field.key}`"
                            v-model="formData[field.key] as string"
                            :placeholder="field.placeholder"
                            rows="4"
                            class="flex w-full rounded-lg border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-brand-500 focus-visible:ring-offset-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                            :disabled="
                                dialogMode === 'edit' && field.disableOnEdit
                            "
                        />

                        <!-- Searchable Select -->
                        <SearchableSelect
                            v-else-if="field.type === 'select'"
                            :id="`field-${field.key}`"
                            v-model="formData[field.key]"
                            :options="field.options || []"
                            :placeholder="`Pilih ${field.label}`"
                            :disabled="
                                dialogMode === 'edit' && field.disableOnEdit
                            "
                        />

                        <!-- Default Input -->
                        <Input
                            v-else
                            :id="`field-${field.key}`"
                            :type="field.type === 'phone' || field.type === 'tel' ? 'tel' : (field.type === 'tags' ? 'text' : field.type)"
                            v-model="formData[field.key] as string"
                            :placeholder="field.placeholder"
                            class="h-10 rounded-lg focus-visible:ring-brand-500"
                            :pattern="field.type === 'phone' || field.type === 'tel' ? '[+]?[0-9]*' : undefined"
                            :disabled="
                                field.disabled ||
                                (dialogMode === 'edit' && field.disableOnEdit)
                            "
                        />

                        <p
                            v-if="formErrors[field.key]"
                            class="mt-0.5 text-xs text-destructive"
                        >
                            {{ formErrors[field.key] }}
                        </p>
                    </div>

                    <!-- Collapsible Advanced Fields Section -->
                    <div
                        v-if="formFields.some((f) => f.advanced)"
                        class="mt-2 overflow-hidden rounded-xl border bg-muted/10 md:col-span-2"
                    >
                        <button
                            type="button"
                            @click="showAdvanced = !showAdvanced"
                            class="flex w-full items-center justify-between px-5 py-4 text-sm font-semibold text-foreground/80 transition-colors hover:bg-muted/30"
                        >
                            <span class="flex items-center gap-2">
                                <Plus
                                    v-if="!showAdvanced"
                                    class="h-4 w-4 animate-in text-brand-600 fade-in"
                                />
                                <span
                                    v-else
                                    class="px-0.5 font-bold text-brand-600"
                                    >-</span
                                >
                                Pengaturan Tambahan (Opsional)
                            </span>
                            <span
                                class="text-xs font-normal text-muted-foreground"
                            >
                                {{ showAdvanced ? 'Sembunyikan' : 'Tampilkan' }}
                            </span>
                        </button>

                        <div
                            v-show="showAdvanced"
                            class="grid animate-in gap-6 border-t bg-background p-5 duration-300 fade-in md:grid-cols-2"
                        >
                            <div
                                v-for="field in formFields.filter(
                                    (f) => f.advanced,
                                )"
                                :key="field.key"
                                class="flex flex-col gap-1.5"
                                :class="
                                    ['textarea', 'image'].includes(field.type)
                                        ? 'md:col-span-2'
                                        : ''
                                "
                            >
                                <Label
                                    v-if="field.type !== 'image'"
                                    :for="`field-${field.key}`"
                                    class="text-xs font-semibold text-foreground/75"
                                >
                                    {{ field.label }}
                                    <span
                                        v-if="field.required"
                                        class="text-destructive"
                                        >*</span
                                    >
                                </Label>

                                <!-- Textarea inside advanced -->
                                <textarea
                                    v-if="field.type === 'textarea'"
                                    :id="`field-${field.key}`"
                                    v-model="formData[field.key] as string"
                                    :placeholder="field.placeholder"
                                    rows="3"
                                    class="flex w-full rounded-lg border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-brand-500 focus-visible:ring-offset-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                                    :disabled="
                                        dialogMode === 'edit' &&
                                        field.disableOnEdit
                                    "
                                />

                                <!-- Select inside advanced -->
                                <select
                                    v-else-if="field.type === 'select'"
                                    :id="`field-${field.key}`"
                                    v-model="formData[field.key]"
                                    class="flex h-10 w-full rounded-lg border border-input bg-background px-3 py-1 text-sm shadow-sm focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 focus:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                                    :disabled="
                                        dialogMode === 'edit' &&
                                        field.disableOnEdit
                                    "
                                >
                                    <option value="" disabled>
                                        Pilih {{ field.label }}
                                    </option>
                                    <option
                                        v-for="opt in field.options"
                                        :key="opt.value"
                                        :value="opt.value"
                                    >
                                        {{ opt.label }}
                                    </option>
                                </select>

                                <!-- Default Input inside advanced -->
                                <Input
                                    v-else
                                    :id="`field-${field.key}`"
                                    :type="
                                        field.type === 'tags'
                                            ? 'text'
                                            : field.type
                                    "
                                    v-model="formData[field.key] as string"
                                    :placeholder="
                                        field.placeholder ||
                                        (field.type === 'tags'
                                            ? 'Pisahkan dengan koma, contoh: gluten, dairy'
                                            : '')
                                    "
                                    class="h-10 rounded-lg text-sm focus-visible:ring-brand-500"
                                    :disabled="
                                        field.disabled ||
                                        (dialogMode === 'edit' &&
                                            field.disableOnEdit)
                                    "
                                />

                                <p
                                    v-if="formErrors[field.key]"
                                    class="mt-0.5 text-xs text-destructive"
                                >
                                    {{ formErrors[field.key] }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <div
                        class="mt-2 flex items-center justify-end gap-3 border-t pt-4 md:col-span-2"
                    >
                        <Button
                            type="button"
                            variant="outline"
                            @click="cancelForm"
                            :disabled="submitting"
                        >
                            Batal
                        </Button>
                        <Button
                            type="submit"
                            class="bg-brand-600 text-white hover:bg-brand-700"
                            :disabled="submitting"
                        >
                            <Loader2
                                v-if="submitting"
                                class="mr-2 h-4 w-4 animate-spin"
                            />
                            <Check v-else class="mr-2 h-4 w-4" />
                            {{
                                dialogMode === 'create'
                                    ? 'Simpan Data'
                                    : 'Simpan Perubahan'
                            }}
                        </Button>
                    </div>
                </form>
            </div>
        </template>

        <!-- Delete Confirmation Dialog -->
        <Dialog v-model:open="deleteDialogOpen">
            <DialogContent class="sm:max-w-sm">
                <DialogHeader>
                    <DialogTitle>Konfirmasi Hapus</DialogTitle>
                    <DialogDescription>
                        Data ini akan dipindahkan ke tong sampah.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <Button
                        variant="outline"
                        @click="deleteDialogOpen = false"
                        :disabled="deleting"
                    >
                        Batal
                    </Button>
                    <Button
                        variant="destructive"
                        @click="executeDelete"
                        :disabled="deleting"
                    >
                        <Loader2
                            v-if="deleting"
                            class="mr-2 h-4 w-4 animate-spin"
                        />
                        <Trash2 class="mr-2 h-4 w-4" />
                        Hapus
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Force Delete Confirmation Dialog -->
        <Dialog v-model:open="forceDeleteDialogOpen">
            <DialogContent class="sm:max-w-sm">
                <DialogHeader>
                    <DialogTitle
                        class="flex items-center gap-2 text-destructive"
                    >
                        <AlertCircle class="h-5 w-5" />
                        Hapus Permanen?
                    </DialogTitle>
                    <DialogDescription>
                        Tindakan ini tidak dapat dibatalkan. Data akan dihapus
                        selamanya dari database.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <Button
                        variant="outline"
                        @click="forceDeleteDialogOpen = false"
                        :disabled="forceDeleting"
                    >
                        Batal
                    </Button>
                    <Button
                        variant="destructive"
                        @click="executeForceDelete"
                        :disabled="forceDeleting"
                    >
                        <Loader2
                            v-if="forceDeleting"
                            class="mr-2 h-4 w-4 animate-spin"
                        />
                        <Trash2 class="mr-2 h-4 w-4" />
                        Hapus Selamanya
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Bulk Delete Dialog -->
        <Dialog v-model:open="bulkDeleteDialogOpen">
            <DialogContent class="sm:max-w-sm">
                <DialogHeader>
                    <DialogTitle
                        >Hapus {{ selectedIds.length }} Item?</DialogTitle
                    >
                    <DialogDescription>
                        Semua item yang terpilih akan dipindahkan ke tong
                        sampah.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <Button
                        variant="outline"
                        @click="bulkDeleteDialogOpen = false"
                        :disabled="bulkActionLoading"
                    >
                        Batal
                    </Button>
                    <Button
                        variant="destructive"
                        @click="executeBulkAction('delete')"
                        :disabled="bulkActionLoading"
                    >
                        <Loader2
                            v-if="bulkActionLoading"
                            class="mr-2 h-4 w-4 animate-spin"
                        />
                        Hapus Sekarang
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Bulk Force Delete Dialog -->
        <Dialog v-model:open="bulkForceDeleteDialogOpen">
            <DialogContent class="sm:max-w-sm">
                <DialogHeader>
                    <DialogTitle class="text-destructive"
                        >Hapus Permanen
                        {{ selectedIds.length }} Item?</DialogTitle
                    >
                    <DialogDescription>
                        Tindakan ini permanen dan tidak dapat dibatalkan. Semua
                        item akan hilang selamanya.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <Button
                        variant="outline"
                        @click="bulkForceDeleteDialogOpen = false"
                        :disabled="bulkActionLoading"
                    >
                        Batal
                    </Button>
                    <Button
                        variant="destructive"
                        @click="executeBulkAction('force')"
                        :disabled="bulkActionLoading"
                    >
                        <Loader2
                            v-if="bulkActionLoading"
                            class="mr-2 h-4 w-4 animate-spin"
                        />
                        Hapus Permanen
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Audit Log History Dialog -->
        <Dialog v-model:open="showAuditDialog">
            <DialogContent class="sm:max-w-2xl max-h-[85vh] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle class="flex items-center gap-2 text-xl font-bold">
                        <History class="h-5 w-5 text-brand-600" />
                        <span v-if="selectedAuditRow">Riwayat Perubahan: {{ resourceName }} #{{ selectedAuditRow[idKey] }}</span>
                        <span v-else>Log Aktivitas: {{ resourceName }}</span>
                    </DialogTitle>
                    <DialogDescription>
                        Daftar riwayat aksi, waktu, perubahan nilai, dan pelaku transaksi.
                    </DialogDescription>
                </DialogHeader>

                <div class="mt-4 space-y-4">
                    <div v-if="auditLoading" class="flex flex-col items-center justify-center py-12">
                        <Loader2 class="h-8 w-8 animate-spin text-brand-600 mb-2" />
                        <span class="text-xs font-semibold text-muted-foreground uppercase">Memuat data log...</span>
                    </div>

                    <div v-else-if="auditLogs.length === 0" class="flex flex-col items-center justify-center py-12 border border-dashed rounded-xl bg-muted/20">
                        <History class="h-8 w-8 text-muted-foreground/50 mb-2" />
                        <span class="text-xs font-black text-muted-foreground uppercase">Belum ada riwayat aktivitas tercatat</span>
                    </div>

                    <div v-else class="relative border-l border-border pl-6 ml-3 space-y-6">
                        <div v-for="log in auditLogs" :key="log.id" class="relative group">
                            <!-- Dot Indicator -->
                            <div 
                                class="absolute -left-[31px] top-1.5 size-4 rounded-full border-2 border-background flex items-center justify-center"
                                :class="{
                                    'bg-emerald-500': log.action === 'create',
                                    'bg-blue-500': log.action === 'update',
                                    'bg-rose-500': log.action === 'delete',
                                    'bg-amber-500': log.action === 'restore'
                                }"
                            >
                            </div>

                            <div class="space-y-1">
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <div class="flex items-center gap-2">
                                        <span class="font-bold text-sm text-foreground">{{ log.user?.name || 'Sistem' }}</span>
                                        <span 
                                            class="inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-black uppercase tracking-wider"
                                            :class="{
                                                'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/20 dark:text-emerald-400': log.action === 'create',
                                                'bg-blue-50 text-blue-700 dark:bg-blue-950/20 dark:text-blue-400': log.action === 'update',
                                                'bg-rose-50 text-rose-700 dark:bg-rose-950/20 dark:text-rose-400': log.action === 'delete',
                                                'bg-amber-50 text-amber-700 dark:bg-amber-950/20 dark:text-amber-400': log.action === 'restore'
                                            }"
                                        >
                                            {{ log.action }}
                                        </span>
                                    </div>
                                    <span class="text-xs text-muted-foreground font-semibold">
                                        {{ new Date(log.created_at).toLocaleString('id-ID', { dateStyle: 'short', timeStyle: 'short' }) }}
                                    </span>
                                </div>

                                <p class="text-xs text-muted-foreground font-medium flex items-center gap-1.5">
                                    <span>IP: {{ log.ip_address || '—' }}</span>
                                    <span>•</span>
                                    <span class="truncate max-w-[250px]" :title="log.user_agent">{{ log.user_agent || '—' }}</span>
                                </p>

                                <!-- Audit Value Differences -->
                                <div v-if="log.action === 'update' && log.old_values" class="mt-2 text-xs bg-muted/30 border border-border/50 rounded-lg overflow-hidden">
                                    <table class="w-full text-left border-collapse">
                                        <thead>
                                            <tr class="bg-muted/50 border-b border-border/50">
                                                <th class="px-3 py-1.5 font-bold text-muted-foreground uppercase text-[10px]">Kolom</th>
                                                <th class="px-3 py-1.5 font-bold text-muted-foreground uppercase text-[10px]">Sebelumnya</th>
                                                <th class="px-3 py-1.5 font-bold text-muted-foreground uppercase text-[10px]">Menjadi</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-border/30">
                                            <tr v-for="(newVal, key) in log.new_values" :key="key" class="font-medium">
                                                <td class="px-3 py-1.5 font-semibold text-slate-800 dark:text-slate-200 capitalize font-mono">{{ key.replace('_', ' ') }}</td>
                                                <td class="px-3 py-1.5 text-rose-600 bg-rose-50/10 dark:text-rose-400 truncate max-w-[150px]">{{ log.old_values[key] === null || log.old_values[key] === undefined ? '—' : log.old_values[key] }}</td>
                                                <td class="px-3 py-1.5 text-emerald-600 bg-emerald-50/10 dark:text-emerald-400 truncate max-w-[150px]">{{ newVal === null || newVal === undefined ? '—' : newVal }}</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>

                                <div v-else-if="log.action === 'create' && log.new_values" class="mt-2 text-xs bg-muted/20 border border-border/30 rounded-lg p-3">
                                    <span class="text-muted-foreground font-semibold block mb-1">Data Awal:</span>
                                    <div class="grid grid-cols-2 gap-x-4 gap-y-1 font-medium text-slate-600 dark:text-slate-400">
                                        <div v-for="(val, key) in log.new_values" :key="key" class="truncate">
                                            <span class="font-mono text-[11px] capitalize text-slate-400 mr-1">{{ key.replace('_', ' ') }}:</span>
                                            <span class="text-slate-700 dark:text-slate-300 font-semibold">{{ val === null || val === undefined ? '—' : val }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Audit Logs Pagination -->
                    <div v-if="auditPagination.last_page > 1" class="flex items-center justify-between border-t pt-4">
                        <span class="text-xs text-muted-foreground font-semibold">
                            Hal. {{ auditPagination.current_page }} dari {{ auditPagination.last_page }}
                        </span>
                        <div class="flex items-center gap-1.5">
                            <Button 
                                size="sm" 
                                variant="outline" 
                                class="h-8 rounded-lg cursor-pointer text-xs" 
                                :disabled="auditPagination.current_page === 1 || auditLoading" 
                                @click="fetchAuditLogs(auditPagination.current_page - 1)"
                            >
                                Sebelum
                            </Button>
                            <Button 
                                size="sm" 
                                variant="outline" 
                                class="h-8 rounded-lg cursor-pointer text-xs" 
                                :disabled="auditPagination.current_page === auditPagination.last_page || auditLoading" 
                                @click="fetchAuditLogs(auditPagination.current_page + 1)"
                            >
                                Sesudah
                            </Button>
                        </div>
                    </div>
                </div>

                <DialogFooter class="mt-6 border-t pt-4">
                    <Button variant="outline" @click="showAuditDialog = false">
                        Tutup
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
