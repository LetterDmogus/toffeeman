<script setup lang="ts">
import { ref, onMounted, onUnmounted, watch } from "vue";
import { useHandTracking } from "@/composables/useHandTracking";
import { Camera, Eye, EyeOff, RefreshCw, X } from "lucide-vue-next";

const {
	handTrackingEnabled,
	isHandModelLoading,
	showHandCursor,
	handCursorCoords,
	isPinching,
	snappedElement,
	dwellProgress,
	isPeaceSign,
	toggleHandTracking,
	calculateSnap,
	checkPeaceSign,
	updateDwell
} = useHandTracking();

const videoEl = ref<HTMLVideoElement | null>(null);
const cameraActive = ref(false);
const showPreview = ref(true);

let stream: MediaStream | null = null;
let handLandmarker: any = null;
let lastVideoTime = -1;
let animationFrameId: number | null = null;
let lastTimestamp = performance.now();

const loadHandModel = async () => {
	if (handLandmarker) return;
	isHandModelLoading.value = true;
	try {
		const visionUrl = "https://cdn.jsdelivr.net/npm/@mediapipe/tasks-vision@0.10.8/vision_bundle.mjs";
		const { HandLandmarker, FilesetResolver } = await import(/* @vite-ignore */ visionUrl) as any;
		
		const vision = await FilesetResolver.forVisionTasks(
			"https://cdn.jsdelivr.net/npm/@mediapipe/tasks-vision@0.10.8/wasm"
		);
		handLandmarker = await HandLandmarker.createFromOptions(vision, {
			baseOptions: {
				modelAssetPath: "https://storage.googleapis.com/mediapipe-models/hand_landmarker/hand_landmarker/float16/1/hand_landmarker.task",
				delegate: "GPU"
			},
			runningMode: "VIDEO",
			numHands: 1
		});
	} catch (e) {
		console.error("Gagal memuat model hand landmark:", e);
	} finally {
		isHandModelLoading.value = false;
	}
};

const startCamera = async () => {
	try {
		cameraActive.value = false;
		if (stream) {
			stream.getTracks().forEach((track) => track.stop());
		}

		stream = await navigator.mediaDevices.getUserMedia({
			video: { width: 320, height: 240, facingMode: "user" },
			audio: false,
		});

		if (videoEl.value) {
			videoEl.value.srcObject = stream;
			videoEl.value.onloadedmetadata = () => {
				cameraActive.value = true;
			};
		}
	} catch (err) {
		console.error("Kamera error:", err);
		cameraActive.value = false;
	}
};

const stopCamera = () => {
	if (stream) {
		stream.getTracks().forEach((track) => track.stop());
		stream = null;
	}
	cameraActive.value = false;
};

const simulateClick = (x: number, y: number) => {
	const element = document.elementFromPoint(x, y);
	if (element) {
		const clickEvent = new MouseEvent("click", {
			view: window,
			bubbles: true,
			cancelable: true,
			clientX: x,
			clientY: y
		});
		element.dispatchEvent(clickEvent);
		
		// Wave visual effect
		const ripple = document.createElement("div");
		ripple.style.position = "absolute";
		ripple.style.left = `${x - 12}px`;
		ripple.style.top = `${y - 12}px`;
		ripple.style.width = "24px";
		ripple.style.height = "24px";
		ripple.style.border = "4px solid #f97316";
		ripple.style.borderRadius = "50%";
		ripple.style.pointerEvents = "none";
		ripple.style.transform = "scale(0.5)";
		ripple.style.transition = "transform 0.3s ease-out, opacity 0.3s ease-out";
		ripple.style.zIndex = "99999";
		document.body.appendChild(ripple);
		
		setTimeout(() => {
			ripple.style.transform = "scale(2)";
			ripple.style.opacity = "0";
		}, 10);
		setTimeout(() => {
			ripple.remove();
		}, 350);
	}
};

const runDetection = () => {
	if (!handTrackingEnabled.value || !videoEl.value || !handLandmarker || !cameraActive.value) {
		if (handTrackingEnabled.value) {
			animationFrameId = requestAnimationFrame(runDetection);
		}
		return;
	}

	try {
		const video = videoEl.value;
		const timestamp = performance.now();
		const deltaTimeMs = timestamp - lastTimestamp;
		lastTimestamp = timestamp;

		if (video.currentTime !== lastVideoTime) {
			lastVideoTime = video.currentTime;
			const results = handLandmarker.detectForVideo(video, timestamp);
			if (results && results.landmarks && results.landmarks.length > 0) {
				showHandCursor.value = true;
				const landmarks = results.landmarks[0];
				
				// Detect peace sign gesture
				checkPeaceSign(landmarks);

				const indexTip = landmarks[8];
				const targetX = (1 - indexTip.x) * window.innerWidth;
				const targetY = indexTip.y * window.innerHeight;

				// Smoothed tracking coordinates
				const smoothX = handCursorCoords.value.x + (targetX - handCursorCoords.value.x) * 0.35;
				const smoothY = handCursorCoords.value.y + (targetY - handCursorCoords.value.y) * 0.35;

				// Apply magnetic snap
				const snapped = calculateSnap(smoothX, smoothY);
				handCursorCoords.value.x = snapped.x;
				handCursorCoords.value.y = snapped.y;

				// Check dwell timing to trigger click
				const clickTriggered = updateDwell(handCursorCoords.value.x, handCursorCoords.value.y, deltaTimeMs);
				if (clickTriggered) {
					simulateClick(handCursorCoords.value.x, handCursorCoords.value.y);
				}
			} else {
				showHandCursor.value = false;
				isPeaceSign.value = false;
				dwellProgress.value = 0;
			}
		}
	} catch (err) {
		console.error("Error detecting hand landmarks:", err);
	}

	if (handTrackingEnabled.value) {
		animationFrameId = requestAnimationFrame(runDetection);
	}
};

const isKioskPage = () => {
	return typeof window !== "undefined" && window.location.pathname.includes("/attendance/kiosk");
};

const initTracking = async () => {
	if (isKioskPage()) {
		return;
	}
	await loadHandModel();
	await startCamera();
	runDetection();
};

const destroyTracking = () => {
	if (animationFrameId) {
		cancelAnimationFrame(animationFrameId);
		animationFrameId = null;
	}
	stopCamera();
	// Only reset overlay states if not on kiosk (so kiosk custom overlay cursor works)
	if (!isKioskPage()) {
		showHandCursor.value = false;
		isPinching.value = false;
	}
};

watch(handTrackingEnabled, async (enabled) => {
	if (enabled) {
		await initTracking();
	} else {
		destroyTracking();
	}
});

onMounted(async () => {
	if (handTrackingEnabled.value) {
		await initTracking();
	}
});

onUnmounted(() => {
	destroyTracking();
});
</script>

<template>
    <div>
        <!-- Cursor Overlay -->
        <div 
            v-if="handTrackingEnabled && showHandCursor"
            class="fixed w-8 h-8 rounded-full border-2 border-white pointer-events-none shadow-lg flex items-center justify-center transition-[transform,background-color] duration-75"
            :class="[
                isPinching ? 'bg-orange-600 scale-90 border-orange-300' : 'bg-orange-500 scale-100',
                snappedElement ? 'ring-4 ring-orange-400/50 scale-110' : ''
            ]"
            :style="{ 
                left: `${handCursorCoords.x - 16}px`, 
                top: `${handCursorCoords.y - 16}px`, 
                zIndex: 99999,
                boxShadow: snappedElement 
                    ? '0 0 15px rgba(249, 115, 22, 0.8)' 
                    : '0 0 10px rgba(249, 115, 22, 0.5)'
            }"
        >
            <!-- Circular Progress for Dwell Click -->
            <svg v-if="isPeaceSign && dwellProgress > 0" class="absolute inset-0 w-full h-full transform -rotate-90 scale-110">
                <circle
                    cx="16"
                    cy="16"
                    r="13"
                    stroke="rgba(255,255,255,0.3)"
                    stroke-width="1.5"
                    fill="transparent"
                />
                <circle
                    cx="16"
                    cy="16"
                    r="13"
                    stroke="#ffffff"
                    stroke-width="2.5"
                    fill="transparent"
                    :stroke-dasharray="2 * Math.PI * 13"
                    :stroke-dashoffset="2 * Math.PI * 13 * (1 - dwellProgress / 1000)"
                />
            </svg>
            <div class="w-1.5 h-1.5 bg-white rounded-full"></div>
        </div>

        <!-- Floating Camera Preview & Status Indicator -->
        <div 
            v-if="handTrackingEnabled && !isKioskPage()" 
            class="fixed bottom-4 right-4 z-50 flex flex-col items-end gap-2"
        >
            <div 
                v-if="showPreview"
                class="w-48 bg-white/85 dark:bg-neutral-900/85 backdrop-blur-md rounded-xl shadow-2xl border border-neutral-200 dark:border-neutral-800 p-2 overflow-hidden flex flex-col gap-2 transition-all"
            >
                <div class="relative w-full aspect-[4/3] bg-neutral-950 rounded-lg overflow-hidden flex items-center justify-center">
                    <video
                        ref="videoEl"
                        autoplay
                        muted
                        playsinline
                        class="w-full h-full object-cover scale-x-[-1]"
                    ></video>
                    
                    <div v-if="isHandModelLoading" class="absolute inset-0 bg-neutral-950/70 flex flex-col items-center justify-center gap-1.5 text-white">
                        <RefreshCw class="h-5 w-5 animate-spin text-orange-500" />
                        <span class="text-[9px] font-mono tracking-wider">LOADING AI...</span>
                    </div>

                    <div v-else-if="!cameraActive" class="absolute inset-0 bg-neutral-950/70 flex flex-col items-center justify-center gap-1.5 text-white">
                        <Camera class="h-5 w-5 animate-pulse text-neutral-400" />
                        <span class="text-[9px] font-mono tracking-wider">STARTING CAMERA...</span>
                    </div>
                </div>

                <div class="flex items-center justify-between text-[10px] text-neutral-500 dark:text-neutral-400 px-1 font-sans">
                    <span class="flex items-center gap-1">
                        <span class="h-1.5 w-1.5 rounded-full bg-emerald-500 animate-ping"></span>
                        Tracking Active
                    </span>
                    <button 
                        @click="showPreview = false"
                        class="p-1 hover:bg-neutral-100 dark:hover:bg-neutral-800 rounded text-neutral-500 hover:text-neutral-700 dark:hover:text-neutral-300 transition"
                        title="Hide Preview"
                    >
                        <EyeOff class="h-3 w-3" />
                    </button>
                </div>
            </div>

            <!-- Minimized button to show preview again -->
            <button 
                v-else
                @click="showPreview = true"
                class="bg-orange-500 hover:bg-orange-600 text-white rounded-full p-2.5 shadow-lg flex items-center gap-1.5 text-xs font-semibold border border-orange-400/25 transition-all"
                title="Show Hand Tracking Video Preview"
            >
                <Eye class="h-4 w-4" />
                <span>Show Preview</span>
            </button>
        </div>
    </div>
</template>
