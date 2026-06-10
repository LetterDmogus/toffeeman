<script setup lang="ts">
import {
	Check,
	Crop,
	Loader2,
	RotateCw,
	UploadCloud,
	X,
	Camera,
} from "lucide-vue-next";
import { ref } from "vue";
import { Button } from "@/components/ui/button";
import {
	Dialog,
	DialogContent,
	DialogDescription,
	DialogFooter,
	DialogHeader,
	DialogTitle,
} from "@/components/ui/dialog";

const cameraOpen = ref(false);
const videoElement = ref<HTMLVideoElement | null>(null);
let webcamStream: MediaStream | null = null;

async function openWebcam() {
	cameraOpen.value = true;
	error.value = "";
	try {
		webcamStream = await navigator.mediaDevices.getUserMedia({
			video: { width: 640, height: 480, facingMode: "user" },
			audio: false,
		});
		setTimeout(() => {
			if (videoElement.value) {
				videoElement.value.srcObject = webcamStream;
			}
		}, 100);
	} catch (err) {
		console.error("Camera access failed:", err);
		error.value = "Gagal mengakses kamera perangkat.";
		cameraOpen.value = false;
	}
}

function closeWebcam() {
	if (webcamStream) {
		webcamStream.getTracks().forEach((track) => track.stop());
		webcamStream = null;
	}
	cameraOpen.value = false;
}

function capturePhoto() {
	if (!videoElement.value) {
		return;
	}
	const canvas = document.createElement("canvas");
	canvas.width = videoElement.value.videoWidth || 640;
	canvas.height = videoElement.value.videoHeight || 480;
	const ctx = canvas.getContext("2d");
	if (ctx) {
		ctx.drawImage(videoElement.value, 0, 0, canvas.width, canvas.height);
		const base64 = canvas.toDataURL("image/jpeg", 0.9);
		editorImageSrc.value = base64;
		rotation.value = 0;
		cropBox.value = { x: 10, y: 10, w: 80, h: 80 };
		editorOpen.value = true;
	}
	closeWebcam();
}

const loadScript = (url: string) => {
	return new Promise((resolve, reject) => {
		if ((window as any).faceapi) {
			resolve((window as any).faceapi);
			return;
		}
		const script = document.createElement("script");
		script.src = url;
		script.onload = () => resolve((window as any).faceapi);
		script.onerror = (err) => reject(err);
		document.body.appendChild(script);
	});
};

const props = withDefaults(
	defineProps<{
		modelValue?: string;
		label?: string;
		placeholder?: string;
		maxSizeMb?: number;
		isFacePhoto?: boolean;
	}>(),
	{
		modelValue: "",
		label: "Gambar",
		placeholder: "Drag and drop gambar Anda di sini, atau klik untuk memilih",
		maxSizeMb: 5,
		isFacePhoto: false,
	},
);

const emit = defineEmits<(e: "update:modelValue", value: string) => void>();

const fileInput = ref<HTMLInputElement | null>(null);
const isDragActive = ref(false);
const uploading = ref(false);
const error = ref("");

// Editor Modal State
const editorOpen = ref(false);
const editorImageSrc = ref("");
const rotation = ref(0); // 0, 90, 180, 270

// Crop selector state
const cropBox = ref({ x: 10, y: 10, w: 80, h: 80 }); // Percentage-based selection box
const imageElement = ref<HTMLImageElement | null>(null);
const containerElement = ref<HTMLDivElement | null>(null);

// Interactive dragging & resizing state
const isDragging = ref(false);
const isResizing = ref(false);
const startMousePos = ref({ x: 0, y: 0 });
const startCropBox = ref({ x: 0, y: 0, w: 0, h: 0 });

function triggerFileInput() {
	fileInput.value?.click();
}

function handleDrop(e: DragEvent) {
	isDragActive.value = false;
	const files = e.dataTransfer?.files;

	if (files && files.length > 0) {
		processFile(files[0]);
	}
}

function handleFileSelect(e: Event) {
	const target = e.target as HTMLInputElement;

	if (target.files && target.files.length > 0) {
		processFile(target.files[0]);
	}
}

function processFile(file: File) {
	error.value = "";

	if (!file.type.startsWith("image/")) {
		error.value =
			"Hanya file gambar (.jpg, .jpeg, .png, .webp) yang diperbolehkan.";

		return;
	}

	if (file.size > props.maxSizeMb * 1024 * 1024) {
		error.value = `Ukuran file maksimal adalah ${props.maxSizeMb}MB.`;

		return;
	}

	uploading.value = true;

	const reader = new FileReader();
	reader.onload = (e) => {
		const base64 = e.target?.result as string;
		editorImageSrc.value = base64;
		rotation.value = 0;
		cropBox.value = { x: 10, y: 10, w: 80, h: 80 };
		editorOpen.value = true;
		uploading.value = false;
	};
	reader.onerror = () => {
		error.value = "Gagal membaca file gambar.";
		uploading.value = false;
	};
	reader.readAsDataURL(file);
}

function removeImage() {
	emit("update:modelValue", "");

	if (fileInput.value) {
		fileInput.value.value = "";
	}

	error.value = "";
}

function rotateImage() {
	rotation.value = (rotation.value + 90) % 360;
}

// ─── Direct Interactive Mouse Dragging & Resizing ──────────────────────────────
function startDrag(e: MouseEvent) {
	e.preventDefault();
	isDragging.value = true;
	startMousePos.value = { x: e.clientX, y: e.clientY };
	startCropBox.value = { ...cropBox.value };
	document.addEventListener("mousemove", onDrag);
	document.addEventListener("mouseup", stopDragOrResize);
}

function onDrag(e: MouseEvent) {
	if (!isDragging.value || !containerElement.value) {
		return;
	}

	const rect = containerElement.value.getBoundingClientRect();
	const deltaX = ((e.clientX - startMousePos.value.x) / rect.width) * 100;
	const deltaY = ((e.clientY - startMousePos.value.y) / rect.height) * 100;

	let newX = startCropBox.value.x + deltaX;
	let newY = startCropBox.value.y + deltaY;

	// Bounds check
	newX = Math.max(0, Math.min(100 - cropBox.value.w, newX));
	newY = Math.max(0, Math.min(100 - cropBox.value.h, newY));

	cropBox.value.x = Math.round(newX);
	cropBox.value.y = Math.round(newY);
}

function startResize(e: MouseEvent) {
	e.stopPropagation();
	e.preventDefault();
	isResizing.value = true;
	startMousePos.value = { x: e.clientX, y: e.clientY };
	startCropBox.value = { ...cropBox.value };
	document.addEventListener("mousemove", onResize);
	document.addEventListener("mouseup", stopDragOrResize);
}

function onResize(e: MouseEvent) {
	if (!isResizing.value || !containerElement.value) {
		return;
	}

	const rect = containerElement.value.getBoundingClientRect();
	const deltaX = ((e.clientX - startMousePos.value.x) / rect.width) * 100;
	const deltaY = ((e.clientY - startMousePos.value.y) / rect.height) * 100;

	let newW = startCropBox.value.w + deltaX;
	let newH = startCropBox.value.h + deltaY;

	// Minimum size 10%
	newW = Math.max(10, Math.min(100 - cropBox.value.x, newW));
	newH = Math.max(10, Math.min(100 - cropBox.value.y, newH));

	cropBox.value.w = Math.round(newW);
	cropBox.value.h = Math.round(newH);
}

function stopDragOrResize() {
	isDragging.value = false;
	isResizing.value = false;
	document.removeEventListener("mousemove", onDrag);
	document.removeEventListener("mousemove", onResize);
	document.removeEventListener("mouseup", stopDragOrResize);
}

// Applies crop and rotation using HTML5 Canvas
function applyEdits() {
	const img = new Image();
	// Enable CORS to avoid tainted canvas error when editing external image URLs (e.g. from seeders)
	img.crossOrigin = "anonymous";
	img.src = editorImageSrc.value;
	img.onload = async () => {
		const canvas = document.createElement("canvas");
		const ctx = canvas.getContext("2d");

		if (!ctx) {
			return;
		}

		const isLandscapeRotated = rotation.value === 90 || rotation.value === 270;
		const origWidth = img.naturalWidth;
		const origHeight = img.naturalHeight;

		const cropX = (cropBox.value.x / 100) * origWidth;
		const cropY = (cropBox.value.y / 100) * origHeight;
		const cropW = (cropBox.value.w / 100) * origWidth;
		const cropH = (cropBox.value.h / 100) * origHeight;

		if (isLandscapeRotated) {
			canvas.width = cropH;
			canvas.height = cropW;
		} else {
			canvas.width = cropW;
			canvas.height = cropH;
		}

		ctx.translate(canvas.width / 2, canvas.height / 2);
		ctx.rotate((rotation.value * Math.PI) / 180);

		ctx.drawImage(
			img,
			cropX,
			cropY,
			cropW,
			cropH,
			-cropW / 2,
			-cropH / 2,
			cropW,
			cropH,
		);

		error.value = "";

		// Face verification validation
		if (props.isFacePhoto) {
			uploading.value = true;
			error.value = "Memverifikasi wajah manusia...";
			
			let faceapi = (window as any).faceapi;
			if (!faceapi) {
				try {
					faceapi = await loadScript("https://cdn.jsdelivr.net/npm/@vladmandic/face-api/dist/face-api.js");
				} catch (e) {
					error.value = "Gagal memuat library deteksi wajah AI.";
					uploading.value = false;
					return;
				}
			}

			try {
				if (!faceapi.nets.tinyFaceDetector.params) {
					await faceapi.nets.tinyFaceDetector.loadFromUri("/models");
				}
				
				const detection = await faceapi.detectSingleFace(canvas, new faceapi.TinyFaceDetectorOptions());
				if (!detection) {
					error.value = "Wajah manusia tidak terdeteksi! Harap pastikan foto wajah jelas dan menghadap kamera.";
					uploading.value = false;
					return;
				}
			} catch (e) {
				console.error("Gagal melakukan verifikasi wajah:", e);
			}
			
			uploading.value = false;
			error.value = "";
		}

		const editedBase64 = canvas.toDataURL("image/jpeg", 0.9);
		emit("update:modelValue", editedBase64);
		editorOpen.value = false;
	};
}
</script>

<template>
    <div class="flex flex-col gap-2">
        <label class="text-sm font-semibold text-foreground/80">{{
            label
        }}</label>

        <input
            ref="fileInput"
            type="file"
            accept="image/*"
            class="hidden"
            @change="handleFileSelect"
        />

        <!-- Image Preview State -->
        <div
            v-if="modelValue"
            class="group relative flex aspect-video max-h-[220px] items-center justify-center overflow-hidden rounded-xl border bg-muted/20"
        >
            <img
                :src="modelValue"
                alt="Preview"
                class="h-full w-full object-cover transition-transform duration-300"
            />
            <div
                class="absolute inset-0 flex items-center justify-center gap-2 bg-black/40 opacity-0 transition-opacity duration-200 group-hover:opacity-100"
            >
                <Button
                    type="button"
                    variant="destructive"
                    size="sm"
                    class="rounded-lg shadow"
                    @click="removeImage"
                >
                    <X class="mr-1.5 h-4 w-4" /> Hapus
                </Button>
                <Button
                    type="button"
                    variant="secondary"
                    size="sm"
                    class="rounded-lg border-none bg-white text-slate-900 shadow hover:bg-white/90"
                    @click="
                        editorImageSrc = modelValue;
                        rotation = 0;
                        cropBox = { x: 10, y: 10, w: 80, h: 80 };
                        editorOpen = true;
                    "
                >
                    <Crop class="mr-1.5 h-4 w-4" /> Edit
                </Button>
            </div>
        </div>

        <!-- Drag & Drop Uploader Area -->
        <div
            v-else
            @dragover.prevent="isDragActive = true"
            @dragleave.prevent="isDragActive = false"
            @drop.prevent="handleDrop"
            class="flex aspect-video max-h-[220px] flex-col items-center justify-center gap-3 rounded-xl border-2 border-dashed bg-muted/5 p-6 text-center transition-all duration-200"
            :class="[
                isDragActive
                    ? 'border-brand-500 bg-brand-500/5 ring-2 ring-brand-500/10'
                    : 'border-muted-foreground/20 hover:border-brand-500/50',
            ]"
        >
            <div
                class="flex h-12 w-12 items-center justify-center rounded-xl bg-brand-500/10 text-brand-600"
            >
                <Loader2 v-if="uploading" class="h-6 w-6 animate-spin" />
                <UploadCloud v-else class="h-6 w-6" />
            </div>

            <div class="flex flex-col gap-2 px-4 w-full items-center">
                <p class="text-sm font-medium text-foreground">
                    {{ uploading ? 'Memproses gambar...' : 'Pilih atau Ambil Foto' }}
                </p>
                <p class="text-[10px] text-muted-foreground max-w-[200px] mb-1">
                    {{ placeholder }}
                </p>
                <div v-if="!uploading" class="flex gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        @click="triggerFileInput"
                        class="text-xs h-8"
                    >
                        Pilih File
                    </Button>
                    <Button
                        type="button"
                        variant="secondary"
                        size="sm"
                        @click="openWebcam"
                        class="text-xs h-8 gap-1.5"
                    >
                        <Camera class="h-3.5 w-3.5" /> Kamera
                    </Button>
                </div>
            </div>
        </div>

        <p v-if="error" class="mt-1 text-xs text-destructive">{{ error }}</p>

        <!-- ✂️ IMAGE EDITOR DIALOG (Crop & Rotate) -->
        <Dialog v-model:open="editorOpen">
            <DialogContent class="sm:max-w-xl">
                <DialogHeader>
                    <DialogTitle>Sesuaikan Gambar</DialogTitle>
                    <DialogDescription>
                        Klik dan seret (drag) kotak seleksi merah muda untuk
                        memindahkannya. Seret bulatan kecil di pojok kanan bawah
                        kotak untuk mengubah ukurannya.
                    </DialogDescription>
                </DialogHeader>

                <div class="flex flex-col items-center gap-4 py-2 w-full">
                    <p v-if="error" class="text-xs text-destructive text-center w-full font-semibold bg-destructive/10 border border-destructive/20 rounded p-2 select-none">
                        {{ error }}
                    </p>
                    <!-- Image Work Area Container -->
                    <div
                        ref="containerElement"
                        class="relative flex aspect-video max-h-[300px] w-full max-w-full items-center justify-center overflow-hidden rounded-lg border bg-slate-950 select-none"
                    >
                        <!-- Selected Image under crop view -->
                        <img
                            ref="imageElement"
                            :src="editorImageSrc"
                            alt="To Edit"
                            class="pointer-events-none max-h-[300px] max-w-full object-contain transition-transform duration-200"
                            :style="{ transform: `rotate(${rotation}deg)` }"
                        />

                        <!-- Overlay Selection Box for cropping (now fully draggable via mouse events) -->
                        <div
                            class="absolute cursor-move border-2 border-dashed border-blue-500 bg-blue-400/30 select-none"
                            :style="{
                                left: `${cropBox.x}%`,
                                top: `${cropBox.y}%`,
                                width: `${cropBox.w}%`,
                                height: `${cropBox.h}%`,
                            }"
                            @mousedown="startDrag"
                        >
                            <!-- Resizer handle at bottom right -->
                            <div
                                class="absolute -right-2 -bottom-2 z-20 h-4 w-4 cursor-se-resize rounded-full border-2 border-white bg-blue-600 shadow-md"
                                @mousedown="startResize"
                            ></div>
                        </div>
                    </div>

                    <!-- Crop range selectors (Simple and robust sliders for full client-side control) -->
                    <div class="grid w-full grid-cols-2 gap-4 text-xs">
                        <div class="flex flex-col gap-1">
                            <span class="font-semibold text-muted-foreground"
                                >Posisi Horisontal (Geser X & Lebar)</span
                            >
                            <div class="flex items-center gap-2">
                                <input
                                    type="range"
                                    min="0"
                                    :max="100 - cropBox.w"
                                    v-model.number="cropBox.x"
                                    class="w-full accent-brand-600"
                                />
                                <input
                                    type="range"
                                    min="10"
                                    :max="100 - cropBox.x"
                                    v-model.number="cropBox.w"
                                    class="w-full accent-brand-600"
                                />
                            </div>
                        </div>
                        <div class="flex flex-col gap-1">
                            <span class="font-semibold text-muted-foreground"
                                >Posisi Vertikal (Geser Y & Tinggi)</span
                            >
                            <div class="flex items-center gap-2">
                                <input
                                    type="range"
                                    min="0"
                                    :max="100 - cropBox.h"
                                    v-model.number="cropBox.y"
                                    class="w-full accent-brand-600"
                                />
                                <input
                                    type="range"
                                    min="10"
                                    :max="100 - cropBox.y"
                                    v-model.number="cropBox.h"
                                    class="w-full accent-brand-600"
                                />
                            </div>
                        </div>
                    </div>
                </div>

                <DialogFooter class="items-center gap-3 sm:justify-between">
                    <Button
                        variant="outline"
                        type="button"
                        @click="rotateImage"
                        class="gap-1.5 self-start"
                    >
                        <RotateCw class="h-4 w-4" /> Putar 90°
                    </Button>
                    <div class="flex gap-2">
                        <Button variant="ghost" @click="editorOpen = false"
                            >Batal</Button
                        >
                        <Button
                            @click="applyEdits"
                            class="gap-1.5 bg-brand-600 text-white hover:bg-brand-700"
                        >
                            <Check class="h-4 w-4" /> Terapkan
                        </Button>
                    </div>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- 📷 CAMERA WEBCAM DIALOG -->
        <Dialog :open="cameraOpen" @update:open="val => !val && closeWebcam()">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Ambil Foto Wajah</DialogTitle>
                    <DialogDescription>
                        Hadapkan wajah Anda ke kamera, lalu klik tombol "Ambil Foto" di bawah.
                    </DialogDescription>
                </DialogHeader>

                <div class="flex flex-col items-center justify-center overflow-hidden rounded-lg border bg-slate-950 aspect-video max-h-[300px] w-full relative">
                    <video
                        ref="videoElement"
                        autoplay
                        playsinline
                        muted
                        class="w-full h-full object-cover scale-x-[-1]"
                    ></video>
                    <!-- Guideline overlay -->
                    <div class="absolute inset-0 pointer-events-none flex items-center justify-center border-[20px] border-slate-950/40">
                        <div class="w-40 h-40 border-2 border-dashed border-white/25 rounded-full"></div>
                    </div>
                </div>

                <DialogFooter class="flex gap-2 justify-end sm:justify-end">
                    <Button variant="ghost" type="button" @click="closeWebcam">Batal</Button>
                    <Button
                        type="button"
                        @click="capturePhoto"
                        class="gap-1.5 bg-brand-600 text-white hover:bg-brand-700"
                    >
                        <Camera class="h-4 w-4" /> Ambil Foto
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
