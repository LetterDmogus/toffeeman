import { ref, watch } from "vue";

const handTrackingEnabled = ref(false);
const isHandModelLoading = ref(false);
const showHandCursor = ref(false);
const handCursorCoords = ref({ x: 0, y: 0 });
const isPinching = ref(false); // Used to represent active click/pinch visual feedback
const snappedElement = ref<HTMLElement | null>(null);

// Dwell click states
const dwellProgress = ref(0); // 0 to 1000 ms
const isPeaceSign = ref(false);
let lastDwellX = 0;
let lastDwellY = 0;
let clickCooldown = 0; // cooldown in ms to prevent double clicks

// Load state from localStorage on initialization
if (typeof window !== "undefined") {
	const saved = localStorage.getItem("hand_tracking_enabled");
	if (saved === "true") {
		handTrackingEnabled.value = true;
	}
}

watch(handTrackingEnabled, (val) => {
	if (typeof window !== "undefined") {
		localStorage.setItem("hand_tracking_enabled", String(val));
	}
});

// Magnetic Snap threshold in pixels
const SNAP_THRESHOLD = 90;
// Dwell movement tolerance in pixels
const DWELL_TOLERANCE = 22;

export function useHandTracking() {
	const toggleHandTracking = () => {
		handTrackingEnabled.value = !handTrackingEnabled.value;
		if (!handTrackingEnabled.value) {
			showHandCursor.value = false;
			isPinching.value = false;
			snappedElement.value = null;
			dwellProgress.value = 0;
			isPeaceSign.value = false;
		}
	};

	const calculateSnap = (rawX: number, rawY: number) => {
		if (typeof document === "undefined") {
			return { x: rawX, y: rawY, isSnapped: false };
		}

		const elements = document.querySelectorAll(
			"button, a, input, select, textarea, [role='button'], .cursor-pointer, [onclick]"
		);

		let closestEl: HTMLElement | null = null;
		let minDistance = SNAP_THRESHOLD;
		let targetX = rawX;
		let targetY = rawY;

		elements.forEach((node) => {
			const el = node as HTMLElement;
			const rect = el.getBoundingClientRect();
			if (rect.width === 0 || rect.height === 0) return;

			const centerX = rect.left + rect.width / 2;
			const centerY = rect.top + rect.height / 2;

			const dx = rawX - centerX;
			const dy = rawY - centerY;
			const distance = Math.sqrt(dx * dx + dy * dy);

			if (distance < minDistance) {
				minDistance = distance;
				closestEl = el;
				targetX = centerX;
				targetY = centerY;
			}
		});

		snappedElement.value = closestEl;

		return {
			x: targetX,
			y: targetY,
			isSnapped: closestEl !== null,
			element: closestEl as HTMLElement | null
		};
	};

	const checkPeaceSign = (landmarks: any[]) => {
		if (!landmarks || landmarks.length < 21) {
			isPeaceSign.value = false;
			return false;
		}

		// Index finger: tip (8) is above pip (6)
		const isIndexExtended = landmarks[8].y < landmarks[6].y - 0.04;
		
		// Middle finger: tip (12) is above pip (10)
		const isMiddleExtended = landmarks[12].y < landmarks[10].y - 0.04;
		
		// Ring finger: tip (16) is below pip (14)
		const isRingFolded = landmarks[16].y > landmarks[14].y;
		
		// Pinky finger: tip (20) is below pip (18)
		const isPinkyFolded = landmarks[20].y > landmarks[18].y;

		// Peace sign is index & middle extended, ring & pinky folded
		const peace = isIndexExtended && isMiddleExtended && isRingFolded && isPinkyFolded;
		isPeaceSign.value = peace;
		return peace;
	};

	const updateDwell = (x: number, y: number, deltaTimeMs: number) => {
		if (clickCooldown > 0) {
			clickCooldown -= deltaTimeMs;
			dwellProgress.value = 0;
			isPinching.value = false;
			return false;
		}

		if (!isPeaceSign.value) {
			dwellProgress.value = 0;
			isPinching.value = false;
			return false;
		}

		// Calculate distance from last dwell point
		const dx = x - lastDwellX;
		const dy = y - lastDwellY;
		const dist = Math.sqrt(dx * dx + dy * dy);

		if (dist < DWELL_TOLERANCE) {
			dwellProgress.value += deltaTimeMs;
			if (dwellProgress.value >= 1000) {
				dwellProgress.value = 1000;
				isPinching.value = true;
				clickCooldown = 1500; // 1.5 seconds cooldown after click
				return true; // Click triggered!
			}
		} else {
			// Moved too much, reset dwell point and progress
			dwellProgress.value = 0;
			lastDwellX = x;
			lastDwellY = y;
			isPinching.value = false;
		}

		return false;
	};

	return {
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
	};
}
