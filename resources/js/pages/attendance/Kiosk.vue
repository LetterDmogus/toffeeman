<script setup lang="ts">
import { Head, useForm, router } from "@inertiajs/vue3";
import { onMounted, onUnmounted, ref, computed, watch } from "vue";
import { Camera, MapPin, RefreshCw, CheckCircle, AlertCircle, ShieldAlert, ArrowLeft, Users, UserCheck } from "lucide-vue-next";
import { Link } from "@inertiajs/vue3";
import { useHandTracking } from "@/composables/useHandTracking";

const props = defineProps<{
	positions: Array<{
		id: number;
		name: string;
	}>;
	selectedPositionId: number | null;
	employees: Array<{
		id: number;
		name: string;
		position: string;
		face_photo_url: string | null;
		has_clock_in: boolean;
		has_clock_out: boolean;
		can_clock_in: boolean;
		can_clock_out: boolean;
	}>;
	settings: {
		restaurant_ip: string | null;
		latitude: number | null;
		longitude: number | null;
		radius_meters: number;
	};
}>();

// Video & Canvas refs
const videoEl = ref<HTMLVideoElement | null>(null);
const canvasEl = ref<HTMLCanvasElement | null>(null);

// Status States
const isModelLoading = ref(true);
const loadingProgress = ref("Memuat AI Model...");
const cameraActive = ref(false);
const matchedEmployee = ref<any>(null);
const matchScore = ref(0);
const locationChecking = ref(false);
const coords = ref<{ latitude: number | null; longitude: number | null }>({ latitude: null, longitude: null });
const isWithinRadius = ref<boolean | null>(null);
const scanType = ref<"in" | "out">("in");

// face-api elements
let faceMatcher: any = null;
let stream: MediaStream | null = null;
let detectionInterval: any = null;

// Form submit helper
const form = useForm({
	employee_id: "",
	type: "in",
	photo: "",
	latitude: null as number | null,
	longitude: null as number | null,
});

// Load external CDN script helper
const loadScript = (url: string) => {
	return new Promise((resolve, reject) => {
		const script = document.createElement("script");
		script.src = url;
		script.onload = () => resolve((window as any).faceapi);
		script.onerror = (err) => reject(err);
		document.body.appendChild(script);
	});
};

// Calculate Distance (Haversine formula) in frontend for immediate feedback
const calculateDistance = (lat1: number, lon1: number, lat2: number, lon2: number) => {
	const R = 6371000; // meters
	const dLat = ((lat2 - lat1) * Math.PI) / 180;
	const dLon = ((lon2 - lon1) * Math.PI) / 180;
	const a =
		Math.sin(dLat / 2) * Math.sin(dLat / 2) +
		Math.cos((lat1 * Math.PI) / 180) *
			Math.cos((lat2 * Math.PI) / 180) *
			Math.sin(dLon / 2) *
			Math.sin(dLon / 2);
	const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
	return R * c;
};

// Initialize GPS Geolocation
const getGPSLocation = () => {
	locationChecking.value = true;
	if (navigator.geolocation) {
		navigator.geolocation.getCurrentPosition(
			(position) => {
				coords.value.latitude = position.coords.latitude;
				coords.value.longitude = position.coords.longitude;
				locationChecking.value = false;

				// Verify radius match
				if (props.settings.latitude && props.settings.longitude) {
					const dist = calculateDistance(
						props.settings.latitude,
						props.settings.longitude,
						position.coords.latitude,
						position.coords.longitude
					);
					isWithinRadius.value = dist <= props.settings.radius_meters;
				}
			},
			(err) => {
				console.error("Geolocation error:", err);
				locationChecking.value = false;
				isWithinRadius.value = false; // default to verification fallback
			},
			{ enableHighAccuracy: true, timeout: 8000 }
		);
	} else {
		locationChecking.value = false;
	}
};

// Selection function
const selectPosition = (id: number) => {
	router.get("/attendance/kiosk", { position_id: id });
};

const resetPosition = () => {
	if (detectionInterval) clearInterval(detectionInterval);
	if (stream) {
		stream.getTracks().forEach((track) => track.stop());
		stream = null;
	}
	cameraActive.value = false;
	matchedEmployee.value = null;
	matchScore.value = 0;
	router.get("/attendance/kiosk");
};

// ── Hand Tracking Cursor States (MediaPipe) ──────────────────────────────────
const {
	handTrackingEnabled,
	isHandModelLoading,
	handCursorCoords,
	showHandCursor,
	isPinching,
	snappedElement,
	dwellProgress,
	isPeaceSign,
	toggleHandTracking,
	calculateSnap,
	checkPeaceSign,
	updateDwell
} = useHandTracking();

let handLandmarker: any = null;
let lastVideoTime = -1;
let lastTimestamp = performance.now();

const loadHandModel = async () => {
	if (handLandmarker) return;
	isHandModelLoading.value = true;
	try {
		// Import MediaPipe vision task package
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

watch(handTrackingEnabled, async (enabled) => {
	if (enabled && cameraActive.value) {
		await loadHandModel();
		startHandTrackingLoop();
	} else {
		showHandCursor.value = false;
	}
});

const startHandTrackingLoop = () => {
	const runDetection = () => {
		if (!handTrackingEnabled.value || !videoEl.value || !handLandmarker || !cameraActive.value) {
			if (handTrackingEnabled.value) requestAnimationFrame(runDetection);
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
					
					// Detect peace sign
					checkPeaceSign(landmarks);

					// Index finger tip (landmark 8)
					const indexTip = landmarks[8];
					
					// Mirror X coordinate
					const targetX = (1 - indexTip.x) * window.innerWidth;
					const targetY = indexTip.y * window.innerHeight;

					// Smooth mouse movements (lerp)
					const smoothX = handCursorCoords.value.x + (targetX - handCursorCoords.value.x) * 0.3;
					const smoothY = handCursorCoords.value.y + (targetY - handCursorCoords.value.y) * 0.3;

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
			requestAnimationFrame(runDetection);
		}
	};
	requestAnimationFrame(runDetection);
};

const simulateClick = (x: number, y: number) => {
	// Find element under hand cursor coordinates
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
		
		// Add brief click wave visual effect
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

// Initialize Kiosk models & profiles
onMounted(async () => {
	if (!props.selectedPositionId) {
		isModelLoading.value = false;
		return;
	}

	try {
		// Load face-api from jsdelivr CDN
		loadingProgress.value = "Mengunduh library face-api.js...";
		const faceapi = await loadScript("https://cdn.jsdelivr.net/npm/@vladmandic/face-api/dist/face-api.js") as any;
		
		loadingProgress.value = "Memuat model deteksi wajah (20%)...";
		await faceapi.nets.tinyFaceDetector.loadFromUri("/models");
		loadingProgress.value = "Memuat model titik wajah (60%)...";
		await faceapi.nets.faceLandmark68Net.loadFromUri("/models");
		loadingProgress.value = "Memuat model pengenalan wajah (100%)...";
		await faceapi.nets.faceRecognitionNet.loadFromUri("/models");

		// Fetch and parse all employee profiles to create base descriptors
		loadingProgress.value = "Mempersiapkan data wajah karyawan...";
		const labeledDescriptors = [];

		for (const emp of props.employees) {
			if (!emp.face_photo_url) continue;
			
			try {
				loadingProgress.value = `Memproses wajah: ${emp.name}...`;
				const img = await faceapi.fetchImage(emp.face_photo_url);
				const detections = await faceapi
					.detectSingleFace(img, new faceapi.TinyFaceDetectorOptions({ inputSize: 224, scoreThreshold: 0.6 }))
					.withFaceLandmarks()
					.withFaceDescriptor();

				if (detections) {
					labeledDescriptors.push(
						new faceapi.LabeledFaceDescriptors(emp.id.toString(), [detections.descriptor])
					);
				}
			} catch (e) {
				console.error(`Gagal memproses wajah karyawan ${emp.name}:`, e);
			}
		}

		if (labeledDescriptors.length > 0) {
			faceMatcher = new faceapi.FaceMatcher(labeledDescriptors, 0.45); // distance threshold (lower is stricter, 0.45 is highly accurate)
		} else {
			console.warn("Tidak ada data wajah karyawan yang berhasil diproses.");
		}

		isModelLoading.value = false;
		
		// Init GPS and Camera
		getGPSLocation();
		startCamera();
	} catch (err) {
		console.error("Gagal memuat face-api:", err);
		loadingProgress.value = "Gagal memuat AI Engine. Pastikan internet terhubung.";
	}
});

// Start live camera stream
const startCamera = async () => {
	try {
		cameraActive.value = false;
		if (stream) {
			stream.getTracks().forEach((track) => track.stop());
		}

		stream = await navigator.mediaDevices.getUserMedia({
			video: { width: 640, height: 480, facingMode: "user" },
			audio: false,
		});

		if (videoEl.value) {
			videoEl.value.srcObject = stream;
			videoEl.value.onloadedmetadata = () => {
				cameraActive.value = true;
				startDetectionLoop();
				if (handTrackingEnabled.value) {
					loadHandModel().then(() => {
						startHandTrackingLoop();
					});
				}
			};
		}
	} catch (err) {
		console.error("Kamera error:", err);
		cameraActive.value = false;
	}
};

// Start face matching detection interval
const startDetectionLoop = () => {
	if (detectionInterval) clearInterval(detectionInterval);
	const faceapi = (window as any).faceapi;

	detectionInterval = setInterval(async () => {
		if (!videoEl.value || !cameraActive.value || isModelLoading.value || !faceMatcher) return;

		try {
			const detection = await faceapi
				.detectSingleFace(videoEl.value, new faceapi.TinyFaceDetectorOptions({ inputSize: 224, scoreThreshold: 0.6 }))
				.withFaceLandmarks()
				.withFaceDescriptor();

			if (detection) {
				const bestMatch = faceMatcher.findBestMatch(detection.descriptor);
				if (bestMatch && bestMatch.label !== "unknown") {
					const empId = parseInt(bestMatch.label);
					const found = props.employees.find((e) => e.id === empId) || null;
					matchedEmployee.value = found;
					matchScore.value = Math.round((1 - bestMatch.distance) * 100);

					// Auto-set scanType based on employee's eligibility
					if (found) {
						if (found.can_clock_in) {
							scanType.value = "in";
						} else if (found.can_clock_out) {
							scanType.value = "out";
						}
					}
				} else {
					matchedEmployee.value = null;
					matchScore.value = 0;
				}
			} else {
				matchedEmployee.value = null;
				matchScore.value = 0;
			}
		} catch (e) {
			console.error("Detection loop error:", e);
		}
	}, 400); // scan frequency (400ms)
};

// Trigger capture & save attendance
const submitAttendance = () => {
	if (!matchedEmployee.value || !videoEl.value || !canvasEl.value) return;

	// Check eligibility
	if (scanType.value === "in" && !matchedEmployee.value.can_clock_in) {
		alert("Anda sudah melakukan absen masuk hari ini!");
		return;
	}
	if (scanType.value === "out" && !matchedEmployee.value.can_clock_out) {
		alert("Anda tidak dapat melakukan absen keluar sekarang!");
		return;
	}

	// Draw current video frame to canvas to export base64 string
	const context = canvasEl.value.getContext("2d");
	if (context) {
		canvasEl.value.width = videoEl.value.videoWidth;
		canvasEl.value.height = videoEl.value.videoHeight;
		context.drawImage(videoEl.value, 0, 0, videoEl.value.videoWidth, videoEl.value.videoHeight);
		
		const base64Image = canvasEl.value.toDataURL("image/jpeg");
		
		form.employee_id = matchedEmployee.value.id.toString();
		form.type = scanType.value;
		form.photo = base64Image;
		form.latitude = coords.value.latitude;
		form.longitude = coords.value.longitude;

		form.post("/attendance", {
			onSuccess: () => {
				// Flash message is thrown, reset matching detection temporarily
				matchedEmployee.value = null;
				matchScore.value = 0;
			},
		});
	}
};

onUnmounted(() => {
	if (detectionInterval) clearInterval(detectionInterval);
	if (stream) {
		stream.getTracks().forEach((track) => track.stop());
	}
});
</script>

<template>
    <Head title="Kios Absensi Restoran" />

    <!-- Clean bright theme fullscreen layout -->
    <div class="min-h-screen bg-slate-50 text-slate-800 flex flex-col justify-between font-sans">
        <!-- Topbar -->
        <header class="px-6 py-4 bg-white border-b border-slate-200 flex items-center justify-between shadow-sm">
            <div class="flex items-center gap-3">
                <button 
                    v-if="selectedPositionId" 
                    @click="resetPosition" 
                    class="p-2 hover:bg-slate-100 rounded-lg text-slate-500 hover:text-slate-900 transition cursor-pointer"
                >
                    <ArrowLeft class="h-5 w-5" />
                </button>
                <Link 
                    v-else 
                    :href="route('dashboard')" 
                    class="p-2 hover:bg-slate-100 rounded-lg text-slate-500 hover:text-slate-900 transition cursor-pointer"
                >
                    <ArrowLeft class="h-5 w-5" />
                </Link>
                <div>
                    <h1 class="text-lg font-bold tracking-tight text-slate-900">Kios Absensi Wajah</h1>
                    <p class="text-[10px] text-slate-500 uppercase tracking-widest font-mono">Terminal Kios Pintar</p>
                </div>
            </div>

            <div class="flex items-center gap-4">
                <!-- MediaPipe Hand Tracking Control Switch -->
                <div v-if="selectedPositionId" class="flex items-center gap-2">
                    <button
                        type="button"
                        @click="toggleHandTracking"
                        class="inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-xs font-semibold border transition cursor-pointer"
                        :class="[
                            handTrackingEnabled 
                                ? 'bg-orange-500 border-orange-600 text-white hover:bg-orange-600' 
                                : 'bg-slate-100 border-slate-200 text-slate-600 hover:bg-slate-200'
                        ]"
                    >
                        <RefreshCw v-if="isHandModelLoading" class="h-3.5 w-3.5 animate-spin" />
                        <span v-else class="h-2 w-2 rounded-full" :class="handTrackingEnabled ? 'bg-white animate-ping' : 'bg-slate-400'"></span>
                        Hand Tracking: {{ handTrackingEnabled ? 'Aktif' : 'Nonaktif' }}
                    </button>
                </div>

                <!-- GPS Status -->
                <div class="flex items-center gap-2 text-xs bg-slate-100 px-3 py-1.5 rounded-full border border-slate-200 font-mono text-slate-700">
                    <MapPin class="h-3.5 w-3.5" :class="isWithinRadius ? 'text-emerald-600' : 'text-amber-600'" />
                    <span v-if="locationChecking" class="text-slate-500 animate-pulse">Menghubungkan GPS...</span>
                    <span v-else-if="isWithinRadius === true" class="text-emerald-600 font-semibold">Restoran (GPS Match)</span>
                    <span v-else class="text-amber-600 font-semibold">Luar Area (Verifikasi Manual)</span>
                </div>
            </div>
        </header>

        <!-- Hand Cursor Overlay -->
        <div 
            v-if="handTrackingEnabled && showHandCursor"
            class="fixed w-8 h-8 rounded-full border-2 border-white pointer-events-none transition-all duration-75 shadow-lg flex items-center justify-center"
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

        <!-- Main Screen -->
        <main class="flex-1 flex flex-col items-center justify-center p-6 gap-6">
            
            <!-- Step 1: Position Selection Screen -->
            <div v-if="!selectedPositionId" class="w-full max-w-4xl text-center space-y-8 animate-in fade-in duration-300">
                <div class="space-y-2">
                    <h2 class="text-3xl font-extrabold text-slate-950 tracking-tight">Pilih Jabatan Anda</h2>
                    <p class="text-slate-500 text-sm max-w-md mx-auto">
                        Silakan pilih Jabatan terlebih dahulu untuk memuat data pengenal wajah Anda.
                    </p>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4">
                    <button
                        v-for="pos in positions"
                        :key="pos.id"
                        @click="selectPosition(pos.id)"
                        class="group flex items-center justify-between p-6 bg-white border border-slate-200 rounded-2xl shadow-sm text-left transition-all duration-300 hover:-translate-y-1 hover:border-brand-500 hover:shadow-md cursor-pointer"
                    >
                        <div class="flex items-center gap-4">
                            <div class="p-3 bg-brand-50 rounded-xl text-brand-600 group-hover:bg-brand-500 group-hover:text-white transition-colors duration-300">
                                <Users class="h-6 w-6" />
                            </div>
                            <div>
                                <h3 class="font-bold text-slate-800 text-lg group-hover:text-slate-950">{{ pos.name }}</h3>
                                <p class="text-xs text-slate-400">Pilih untuk masuk kios</p>
                            </div>
                        </div>
                    </button>
                </div>
            </div>

            <!-- Loading Model View -->
            <div v-else-if="isModelLoading" class="max-w-md w-full text-center space-y-4 animate-in fade-in duration-300">
                <RefreshCw class="h-10 w-10 text-brand-500 animate-spin mx-auto" />
                <p class="text-sm font-mono text-slate-600">{{ loadingProgress }}</p>
                <div class="w-full bg-slate-200 rounded-full h-1 overflow-hidden">
                    <div class="bg-brand-500 h-full w-2/3 animate-pulse"></div>
                </div>
            </div>

            <!-- Step 2: Scanner Interface -->
            <div v-else class="w-full max-w-4xl grid grid-cols-1 md:grid-cols-12 gap-8 items-center animate-in fade-in duration-300">
                <!-- Camera View Column (Left) -->
                <div class="md:col-span-7 flex flex-col items-center gap-4">
                    <!-- Toggle Absen Masuk / Keluar -->
                    <div class="bg-slate-200 p-1 rounded-full flex w-72 border border-slate-300 shadow-inner">
                        <button
                            type="button"
                            @click="scanType = 'in'"
                            class="flex-1 text-center py-2 text-sm font-medium rounded-full transition-all duration-300"
                            :class="scanType === 'in' ? 'bg-brand-500 text-white shadow font-semibold' : 'text-slate-500 hover:text-slate-800'"
                        >
                            Absen Masuk
                        </button>
                        <button
                            type="button"
                            @click="scanType = 'out'"
                            class="flex-1 text-center py-2 text-sm font-medium rounded-full transition-all duration-300"
                            :class="scanType === 'out' ? 'bg-orange-500 text-white shadow font-semibold' : 'text-slate-500 hover:text-slate-800'"
                        >
                            Absen Keluar
                        </button>
                    </div>

                    <!-- Video Container -->
                    <div class="relative w-full aspect-[4/3] max-w-lg bg-slate-900 rounded-2xl overflow-hidden border-4 shadow-xl flex items-center justify-center"
                         :class="[
                             matchedEmployee 
                                 ? (scanType === 'in' ? 'border-emerald-500 shadow-emerald-100' : 'border-orange-500 shadow-orange-100') 
                                 : 'border-white'
                         ]"
                    >
                        <!-- Camera Feed -->
                        <video
                            ref="videoEl"
                            autoplay
                            muted
                            playsinline
                            class="w-full h-full object-cover scale-x-[-1]"
                        ></video>

                        <!-- Invisible Capture Canvas -->
                        <canvas ref="canvasEl" class="hidden"></canvas>

                        <!-- Static Overlay Guides -->
                        <div class="absolute inset-0 pointer-events-none border-[30px] border-slate-950/40 flex items-center justify-center">
                            <!-- Target Outline -->
                            <div class="w-64 h-64 border-2 border-dashed border-white/30 rounded-full flex items-center justify-center">
                                <div class="w-60 h-60 border border-white/10 rounded-full"></div>
                            </div>
                        </div>

                        <!-- Face detection loading status -->
                        <div v-if="!cameraActive" class="absolute inset-0 bg-slate-950/90 flex flex-col items-center justify-center gap-3">
                            <Camera class="h-8 w-8 text-slate-400 animate-pulse" />
                            <p class="text-xs text-slate-300 font-mono">Mengaktifkan kamera...</p>
                        </div>
                    </div>
                </div>

                <!-- Match Info Column (Right) -->
                <div class="md:col-span-5 space-y-6">
                    <div class="border border-slate-200 bg-white shadow-sm rounded-2xl p-6 space-y-6">
                        <!-- Detected Employee Card -->
                        <div v-if="matchedEmployee" class="space-y-4 animate-in zoom-in-95 duration-200">
                            <div class="text-center space-y-2">
                                <span class="inline-flex items-center gap-1.5 px-3 py-1 bg-emerald-50 border border-emerald-200 text-emerald-700 text-xs font-mono rounded-full font-semibold">
                                    <CheckCircle class="h-3 w-3 animate-pulse" />
                                    Wajah Cocok {{ matchScore }}%
                                </span>
                                <h3 class="text-2xl font-bold tracking-tight text-slate-900 mt-1">{{ matchedEmployee.name }}</h3>
                                <p class="text-xs text-slate-500 uppercase tracking-widest">{{ matchedEmployee.position }}</p>
                            </div>

                            <hr class="border-slate-100" />

                            <!-- Daily Attendance Status Limit Check -->
                            <div v-if="!matchedEmployee.can_clock_in && !matchedEmployee.can_clock_out" class="p-4 bg-amber-50 border border-amber-200 rounded-xl text-center">
                                <UserCheck class="h-8 w-8 text-amber-600 mx-auto mb-2 animate-bounce" />
                                <h4 class="font-bold text-amber-800 text-sm">Absensi Selesai</h4>
                                <p class="text-xs text-amber-600 mt-1">Anda sudah melakukan absen masuk dan keluar hari ini. Tidak perlu absen lagi.</p>
                            </div>

                            <div v-else class="space-y-2.5">
                                <div class="flex items-center justify-between text-xs font-mono p-2.5 bg-slate-50 rounded-lg border border-slate-100">
                                    <span class="text-slate-500">Status Hari Ini</span>
                                    <span class="text-slate-800 font-semibold">
                                        <template v-if="matchedEmployee.has_clock_in && !matchedEmployee.has_clock_out">
                                            Sudah Masuk (Belum Keluar)
                                        </template>
                                        <template v-else-if="!matchedEmployee.has_clock_in">
                                            Belum Absen Masuk
                                        </template>
                                    </span>
                                </div>
                                <div class="flex items-center justify-between text-xs font-mono p-2.5 bg-slate-50 rounded-lg border border-slate-100">
                                    <span class="text-slate-500">Radius Lokasi</span>
                                    <span :class="isWithinRadius ? 'text-emerald-700 font-semibold' : 'text-amber-700 font-semibold'">
                                        {{ isWithinRadius ? 'Dalam Area Restoran' : 'Di Luar Area Restoran' }}
                                    </span>
                                </div>

                                <!-- Submit Action Button -->
                                <button
                                    v-if="(scanType === 'in' && matchedEmployee.can_clock_in) || (scanType === 'out' && matchedEmployee.can_clock_out)"
                                    type="button"
                                    @click="submitAttendance"
                                    class="w-full text-sm font-semibold py-3 px-4 rounded-xl shadow transition duration-300 cursor-pointer"
                                    :class="[
                                        scanType === 'in' 
                                            ? 'bg-emerald-600 hover:bg-emerald-700 text-white' 
                                            : 'bg-orange-500 hover:bg-orange-600 text-white'
                                    ]"
                                >
                                    Konfirmasi & Kirim Absen {{ scanType === 'in' ? 'Masuk' : 'Keluar' }}
                                </button>
                                
                                <div v-else class="p-3 bg-rose-50 border border-rose-100 rounded-xl text-center text-xs text-rose-700 font-medium">
                                    Pilihan tipe absen ({{ scanType === 'in' ? 'Masuk' : 'Keluar' }}) tidak sesuai dengan riwayat absensi Anda hari ini.
                                </div>
                            </div>
                        </div>

                        <!-- No Face Detected / Idle View -->
                        <div v-else class="text-center py-8 space-y-4">
                            <div class="w-16 h-16 bg-slate-100 rounded-full flex items-center justify-center border border-slate-200 mx-auto text-slate-400">
                                <Camera class="h-7 w-7" />
                            </div>
                            <div>
                                <h3 class="text-lg font-bold text-slate-800">Silakan Hadap ke Kamera</h3>
                                <p class="text-xs text-slate-500 leading-relaxed max-w-xs mx-auto mt-1">
                                    Posisikan wajah Anda di dalam lingkaran panduan. Sistem akan mengenali identitas Anda secara otomatis.
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Kiosk Instructions -->
                    <div class="bg-white border border-slate-200 rounded-xl p-4 flex gap-3 text-xs text-slate-500 shadow-xs">
                        <AlertCircle class="h-5 w-5 text-slate-400 shrink-0" />
                        <div>
                            <p class="font-semibold text-slate-700 mb-0.5">Catatan Penting:</p>
                            <p class="leading-relaxed">
                                Pastikan wajah Anda mendapatkan pencahayaan yang cukup. Lepaskan kacamata hitam atau topi jika sistem kesulitan mengenali wajah Anda.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </main>

        <!-- Footer -->
        <footer class="px-6 py-4 bg-white text-center border-t border-slate-200 text-[10px] text-slate-400 font-mono shadow-inner">
            LARAVEL RESTAURANT &bull; ATTENDANCE SYSTEM KIOSK V1.0.0
        </footer>
    </div>
</template>

<style scoped>
.border-emerald-500 {
    box-shadow: 0 0 20px rgba(16, 185, 129, 0.25);
}
.border-orange-500 {
    box-shadow: 0 0 20px rgba(249, 115, 22, 0.25);
}
</style>

