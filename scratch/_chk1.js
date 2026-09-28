
// â•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گ
// ظپط­طµ ط§ظ„ظˆط¬ظ‡ Client-Side ط¹ط¨ط± face-api.js
// â•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گ
let _faceModelsLoaded = false;

async function loadFaceModels() {
  if (_faceModelsLoaded) return true;

  // ط§ظ„طھط­ظ‚ظ‚ ظ…ظ† ط£ظ† ظ…ظƒطھط¨ط© face-api.js ظ…ط­ظ…ظ„ط©
  if (typeof faceapi === 'undefined') {
    console.error('[FaceCheck] ظ…ظƒطھط¨ط© face-api.js ط؛ظٹط± ظ…ط­ظ…ظ„ط©');
    return false;
  }

  try {
    // طھظ‡ظٹط¦ط© TensorFlow.js ظ…ط¹ WebGL (طھط¬ظ†ط¨ WASM)
    await faceapi.tf.setBackend('webgl');
    await faceapi.tf.ready();
    console.log('[FaceCheck] طھظ… طھظ‡ظٹط¦ط© TensorFlow.js ط¨ظ€ WebGL backend');
  } catch (e) {
    console.warn('[FaceCheck] WebGL ط؛ظٹط± ظ…طھط§ط­طŒ ظ…ط­ط§ظˆظ„ط© WASM...');
    try {
      await faceapi.tf.setBackend('wasm');
      await faceapi.tf.ready();
      console.log('[FaceCheck] طھظ… طھظ‡ظٹط¦ط© TensorFlow.js ط¨ظ€ WASM backend');
    } catch (e2) {
      console.warn('[FaceCheck] WASM ط؛ظٹط± ظ…طھط§ط­طŒ ظ…ط­ط§ظˆظ„ط© CPU...');
      try {
        await faceapi.tf.setBackend('cpu');
        await faceapi.tf.ready();
        console.log('[FaceCheck] طھظ… طھظ‡ظٹط¦ط© TensorFlow.js ط¨ظ€ CPU backend (ط£ط¨ط·ط£)');
      } catch (e3) {
        console.error('[FaceCheck] ظپط´ظ„ طھظ‡ظٹط¦ط© TensorFlow.js:', e3);
        return false;
      }
    }
  }

  // ظ‚ط§ط¦ظ…ط© ط§ظ„ظ…طµط§ط¯ط±: ط£ظˆظ„ط§ظ‹ ظ…ط­ظ„ظٹ (ط£ط³ط±ط¹)طŒ ط«ظ… CDN ظƒظ€ fallback
  const MODEL_SOURCES = [
    '{{ asset("scripts/models") }}',
    'https://cdn.jsdelivr.net/npm/@vladmandic/face-api@1.7.15/model'
  ];

  for (const MODEL_URL of MODEL_SOURCES) {
    try {
      await faceapi.nets.ssdMobilenetv1.loadFromUri(MODEL_URL);
      await faceapi.nets.faceLandmark68Net.loadFromUri(MODEL_URL);
      _faceModelsLoaded = true;
      console.log('[FaceCheck] طھظ… طھط­ظ…ظٹظ„ ظ†ظ…ط§ط°ط¬ ظپط­طµ ط§ظ„ظˆط¬ظ‡ ظ…ظ†:', MODEL_URL);
      return true;
    } catch (err) {
      console.warn('[FaceCheck] ظپط´ظ„ ط§ظ„طھط­ظ…ظٹظ„ ظ…ظ†:', MODEL_URL, err.message);
    }
  }

  console.error('[FaceCheck] ظپط´ظ„ طھط­ظ…ظٹظ„ ط§ظ„ظ†ظ…ط§ط°ط¬ ظ…ظ† ط¬ظ…ظٹط¹ ط§ظ„ظ…طµط§ط¯ط±');
  return false;
}

async function checkFaceOnCanvas(canvas) {
  try {
    // â•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گ
    // Two-Pass Detection: ظƒط´ظپ ط§ظ„ظˆط¬ظ‡ ط£ظˆظ„ط§ظ‹طŒ ط«ظ… طھظƒط¨ظٹط± ظ…ظ†ط·ظ‚ط© ط§ظ„ظˆط¬ظ‡ ظ„ظپط­طµ ط§ظ„ظ…ط¹ط§ظ„ظ…
    // â•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گ

    // Pass 1: ظƒط´ظپ ط§ظ„ظˆط¬ظ‡ ط¹ظ„ظ‰ ط§ظ„طµظˆط±ط© ط§ظ„ظƒط§ظ…ظ„ط©
    const detections = await faceapi
      .detectAllFaces(canvas, new faceapi.SsdMobilenetv1Options({ minConfidence: 0.50 }))
      .withFaceLandmarks();

    console.log('[FaceCheck] Pass 1: طھظ… ظƒط´ظپ', detections.length, 'ظˆط¬ظ‡(ظˆط¬ظˆظ‡)');

    if (!detections || detections.length === 0) {
      return { valid: false, reason: 'ظ„ظ… ظٹطھظ… ط§ظ„ط¹ط«ظˆط± ط¹ظ„ظ‰ ظˆط¬ظ‡ ظˆط§ط¶ط­ ظپظٹ ط§ظ„طµظˆط±ط©' };
    }

    if (detections.length > 1) {
      return { valid: false, reason: 'ط§ظ„طµظˆط±ط© طھط­طھظˆظٹ ط¹ظ„ظ‰ ط£ظƒط«ط± ظ…ظ† ظˆط¬ظ‡ - ظٹط±ط¬ظ‰ ط¥ط±ط³ط§ظ„ طµظˆط±ط© ظ„ط´ط®طµ ظˆط§ط­ط¯ ظپظ‚ط·' };
    }

    const face = detections[0];
    const box = face.detection.box;
    console.log('[FaceCheck] Confidence Score:', face.detection.score.toFixed(3));

    // ظپط­طµ ط­ط¬ظ… ط§ظ„ظˆط¬ظ‡ ظپظٹ ط§ظ„طµظˆط±ط© ط§ظ„ط£طµظ„ظٹط©
    const relativeWidth = box.width / canvas.width;
    console.log('[FaceCheck] ط­ط¬ظ… ط§ظ„ظˆط¬ظ‡:', (relativeWidth * 100).toFixed(1) + '% ظ…ظ† ط¹ط±ط¶ ط§ظ„طµظˆط±ط©');

    if (relativeWidth < 0.06) {
      return { valid: false, reason: 'ط­ط¬ظ… ط§ظ„ظˆط¬ظ‡ طµط؛ظٹط± ط¬ط¯ط§ظ‹ ط¨ط§ظ„ظ†ط³ط¨ط© ظ„ظ„طµظˆط±ط© - ظٹظ‚ط±ظ‘ط¨ ط§ظ„طµظˆط±ط©' };
    }

    // Pass 2: ظ‚طµ ظ…ظ†ط·ظ‚ط© ظ…ظƒط¨ظ‘ط±ط© ط­ظˆظ„ ط§ظ„ظˆط¬ظ‡ ظ„ظپط­طµ ط§ظ„ظ…ط¹ط§ظ„ظ… ط¨ط¯ظ‚ط©
    const padding = Math.max(box.width, box.height) * 0.5;
    const sx = Math.max(0, box.x - padding);
    const sy = Math.max(0, box.y - padding);
    const sw = Math.min(canvas.width - sx, box.width + padding * 2);
    const sh = Math.min(canvas.height - sy, box.height + padding * 2);

    console.log('[FaceCheck] Pass 2: طھظƒط¨ظٹط± ظ…ظ†ط·ظ‚ط© ط§ظ„ظˆط¬ظ‡ ظ…ظ†', sw.toFixed(0), 'x', sh.toFixed(0), 'ط¥ظ„ظ‰ 300px');

    const zoomCanvas = document.createElement('canvas');
    const ZOOM_WIDTH = 300;
    const ZOOM_HEIGHT = Math.round(ZOOM_WIDTH * (sh / sw));
    zoomCanvas.width = ZOOM_WIDTH;
    zoomCanvas.height = ZOOM_HEIGHT;

    const ctx = zoomCanvas.getContext('2d');
    ctx.drawImage(canvas, sx, sy, sw, sh, 0, 0, ZOOM_WIDTH, ZOOM_HEIGHT);

    // ظپط­طµ ط§ظ„ظ…ط¹ط§ظ„ظ… ط¹ظ„ظ‰ ط§ظ„ظ…ظ†ط·ظ‚ط© ط§ظ„ظ…ظƒط¨ظ‘ط±ط©
    const detailed = await faceapi
      .detectSingleFace(zoomCanvas, new faceapi.SsdMobilenetv1Options({ minConfidence: 0.50 }))
      .withFaceLandmarks();

    if (!detailed) {
      console.warn('[FaceCheck] Pass 2 ظپط´ظ„طŒ ط§ظ„ط§ط¹طھظ…ط§ط¯ ط¹ظ„ظ‰ ظ†طھط§ط¦ط¬ Pass 1');
      const pass1Landmarks = face.landmarks;
      if (pass1Landmarks && pass1Landmarks.positions && pass1Landmarks.positions.length >= 68) {
        // ظ†ظƒظ…ظ„ ط§ظ„طھط­ظ‚ظ‚ ط¹ظ„ظ‰ ط¨ظٹط§ظ†ط§طھ Pass 1
      } else {
        zoomCanvas.width = 0;
        zoomCanvas.height = 0;
        return { valid: false, reason: 'طھط¹ط°ط± طھط­ظ„ظٹظ„ طھظپط§طµظٹظ„ ط§ظ„ظˆط¬ظ‡ - ظٹط±ط¬ظ‰ ط§ط³طھط®ط¯ط§ظ… طµظˆط±ط© ظˆط¬ظ‡ ظˆط§ط¶ط­ط©' };
      }
    }

    const positions = detailed ? detailed.landmarks.positions : face.landmarks.positions;
    if (!positions || positions.length < 68) {
      zoomCanvas.width = 0;
      zoomCanvas.height = 0;
      return { valid: false, reason: 'ظ…ط¹ط§ظ„ظ… ط§ظ„ظˆط¬ظ‡ ط؛ظٹط± ظ…ظƒطھظ…ظ„ط© - ظٹط±ط¬ظ‰ ط§ط³طھط®ط¯ط§ظ… طµظˆط±ط© ظˆط¬ظ‡ ظˆط§ط¶ط­ط©' };
    }

    const isValidGroup = (pts) => pts.every(p => p && typeof p.x === 'number' && typeof p.y === 'number' && !isNaN(p.x) && isFinite(p.x) && !isNaN(p.y) && isFinite(p.y));

    const jaw = positions.slice(0, 17);
    if (jaw.length !== 17 || !isValidGroup(jaw)) {
      zoomCanvas.width = 0;
      zoomCanvas.height = 0;
      return { valid: false, reason: 'ط§ظ„ظپظƒ ط؛ظٹط± ظˆط§ط¶ط­ - ظٹط±ط¬ظ‰ ط§ط³طھط®ط¯ط§ظ… طµظˆط±ط© ظˆط¬ظ‡ ظˆط§ط¶ط­ط© ظ…ظ† ط§ظ„ط£ظ…ط§ظ…' };
    }

    const rightEyebrow = positions.slice(17, 22);
    const leftEyebrow = positions.slice(22, 27);
    if (rightEyebrow.length !== 5 || leftEyebrow.length !== 5 || !isValidGroup(rightEyebrow) || !isValidGroup(leftEyebrow)) {
      zoomCanvas.width = 0;
      zoomCanvas.height = 0;
      return { valid: false, reason: 'ظ…ط¹ط§ظ„ظ… ط§ظ„ط­ط§ط¬ط¨ظٹظ† ط؛ظٹط± ظˆط§ط¶ط­ط© - ظٹط±ط¬ظ‰ ط§ط³طھط®ط¯ط§ظ… طµظˆط±ط© ظˆط¬ظ‡ ظˆط§ط¶ط­ط© ظ…ظ† ط§ظ„ط£ظ…ط§ظ…' };
    }

    const nose = positions.slice(27, 36);
    if (nose.length !== 9 || !isValidGroup(nose)) {
      zoomCanvas.width = 0;
      zoomCanvas.height = 0;
      return { valid: false, reason: 'ظ…ط¹ط§ظ„ظ… ط§ظ„ط£ظ†ظپ ط؛ظٹط± ظˆط§ط¶ط­ط©' };
    }

    const leftEye = positions.slice(36, 42);
    const rightEye = positions.slice(42, 48);
    if (leftEye.length !== 6 || rightEye.length !== 6 || !isValidGroup(leftEye) || !isValidGroup(rightEye)) {
      zoomCanvas.width = 0;
      zoomCanvas.height = 0;
      return { valid: false, reason: 'ظ…ط¹ط§ظ„ظ… ط§ظ„ط¹ظٹظ†ظٹظ† ط؛ظٹط± ظˆط§ط¶ط­ط©' };
    }

    const mouth = positions.slice(48, 68);
    if (mouth.length !== 20 || !isValidGroup(mouth)) {
      zoomCanvas.width = 0;
      zoomCanvas.height = 0;
      return { valid: false, reason: 'ظ…ط¹ط§ظ„ظ… ط§ظ„ظپظ… ط؛ظٹط± ظˆط§ط¶ط­ط©' };
    }

    zoomCanvas.width = 0;
    zoomCanvas.height = 0;
    console.log('[FaceCheck] âœ… طھظ… ط§ظ„طھط­ظ‚ظ‚ ظ…ظ† ط§ظ„ظˆط¬ظ‡ ط¨ظ†ط¬ط§ط­ (Two-Pass)');
    return { valid: true };
  } catch (err) {
    console.error('[FaceCheck] ط®ط·ط£ ظپظٹ ظپط­طµ ط§ظ„ظˆط¬ظ‡:', err);
    return { valid: false, reason: 'طھط¹ط°ط± ظپط­طµ ط§ظ„طµظˆط±ط© ط§ظ„ط´ط®طµظٹط©' };
  }
}

// ط¯ط§ظ„ط© طھط­ظˆظٹظ„ HEIC ط¥ظ„ظ‰ JPEG
async function convertHeicToJpeg(file) {
  const isHeic = file.type === 'image/heic' || file.type === 'image/heif' ||
                 file.name.toLowerCase().endsWith('.heic') || file.name.toLowerCase().endsWith('.heif');

  if (!isHeic) return file;

  console.log('[convertHeicToJpeg] طھط­ظˆظٹظ„ ظ…ظ„ظپ HEIC ط¥ظ„ظ‰ JPEG:', file.name);

  // ط§ظ†طھط¸ط§ط± طھط­ظ…ظٹظ„ heic2any ط¥ط°ط§ ظ„ظ… ظٹظƒظ† ظ…ط­ظ…ظ„ط§ظ‹ ط¨ط¹ط¯
  if (typeof heic2any === 'undefined') {
    console.log('[convertHeicToJpeg] ط§ظ†طھط¸ط§ط± طھط­ظ…ظٹظ„ heic2any...');
    await new Promise((resolve, reject) => {
      let attempts = 0;
      const check = setInterval(() => {
        attempts++;
        if (typeof heic2any !== 'undefined') {
          clearInterval(check);
          resolve();
        } else if (attempts > 50) { // 5 ط«ظˆط§ظ†ظچ
          clearInterval(check);
          reject(new Error('ظ…ظƒطھط¨ط© heic2any ظ„ظ… طھظڈط­ظ…ظ‘ظ„'));
        }
      }, 100);
    });
  }

  try {
    const jpegBlob = await heic2any({
      blob: file,
      toType: 'image/jpeg',
      quality: 0.92
    });

    // heic2any ظ‚ط¯ ظٹظڈط±ط¬ط¹ ظ…طµظپظˆظپط© blobs
    const blob = Array.isArray(jpegBlob) ? jpegBlob[0] : jpegBlob;

    const newName = file.name.replace(/\.(heic|heif)$/i, '.jpg');
    const convertedFile = new File([blob], newName, {
      type: 'image/jpeg',
      lastModified: Date.now()
    });

    console.log('[convertHeicToJpeg] طھظ… ط§ظ„طھط­ظˆظٹظ„ ط¨ظ†ط¬ط§ط­:', convertedFile.name, convertedFile.size);
    return convertedFile;

  } catch (error) {
    console.error('[convertHeicToJpeg] ظپط´ظ„ ط§ظ„طھط­ظˆظٹظ„:', error);
    throw new Error('ظپط´ظ„ ظپظٹ طھط­ظˆظٹظ„ طµظˆط±ط© HEIC');
  }
}
window.showCropperModal = async function(file, callback) {
  console.log('[showCropperModal] ط¨ط¯ط، ظپط­طµ ط§ظ„ظ…ظ„ظپ:', file);

  // ط§ظ„طھط­ظ‚ظ‚ ظ…ظ† طµط­ط© ط§ظ„ظ…ظ„ظپ
  if (!file || !(file instanceof File) && !(file instanceof Blob)) {
    console.error('[showCropperModal] ظ…ظ„ظپ ط؛ظٹط± طµط§ظ„ط­:', file);
    if (callback) callback(null, 'ظ…ظ„ظپ ط؛ظٹط± طµط§ظ„ط­');
    return;
  }

  // ظپط­طµ: ظ‡ظ„ ط§ظ„ظ…ظ„ظپ طµظˆط±ط© (ط¨ظ…ط§ ظپظٹظ‡ط§ HEIC)?
  const isImage = (file.type && file.type.startsWith('image/')) ||
                  file.name.toLowerCase().endsWith('.heic') ||
                  file.name.toLowerCase().endsWith('.heif');

  if (!isImage) {
    console.error('[showCropperModal] ط§ظ„ظ…ظ„ظپ ظ„ظٹط³ طµظˆط±ط©:', file.type);
    if (callback) callback(null, 'ط§ظ„ظ…ظ„ظپ ظ„ظٹط³ طµظˆط±ط©');
    return;
  }

  // طھط­ظˆظٹظ„ HEIC ط¥ظ„ظ‰ JPEG ط¥ط°ط§ ظ„ط²ظ… ط§ظ„ط£ظ…ط±
  try {
    file = await convertHeicToJpeg(file);
  } catch (heicError) {
    console.error('[showCropperModal] ط®ط·ط£ ظپظٹ طھط­ظˆظٹظ„ HEIC:', heicError);
    if (callback) callback(null, 'ظپط´ظ„ ظپظٹ طھط­ظˆظٹظ„ طµظˆط±ط© HEIC');
    return;
  }

  if (file.size === 0 || file.size > 50 * 1024 * 1024) {
    console.error('[showCropperModal] ط­ط¬ظ… ط§ظ„ظ…ظ„ظپ ط؛ظٹط± طµط§ظ„ط­:', file.size);
    if (callback) callback(null, 'ط­ط¬ظ… ط§ظ„ظ…ظ„ظپ ط؛ظٹط± طµط§ظ„ط­');
    return;
  }

  // ط§ظ„ط­طµظˆظ„ ط¹ظ„ظ‰ ط§ظ„ط¹ظ†ط§طµط± ط§ظ„ظ…ط·ظ„ظˆط¨ط©
  const elements = {
    modal: document.getElementById('cropperModal'),
    image: document.getElementById('cropperImage'),
    cropBtn: document.getElementById('cropperCropBtn'),
    loaderModal: document.getElementById('compressLoaderModal')
  };

  // ط§ظ„طھط­ظ‚ظ‚ ظ…ظ† ظˆط¬ظˆط¯ ط§ظ„ط¹ظ†ط§طµط±
  for (const [key, element] of Object.entries(elements)) {
    if (!element) {
      console.error(`[showCropperModal] ط¹ظ†طµط± ${key} ظ…ظپظ‚ظˆط¯`);
      if (callback) callback(null, `ط¹ظ†طµط± ${key} ظ…ظپظ‚ظˆط¯`);
      return;
    }
  }

  console.log('[showCropperModal] âœ… ط¬ظ…ظٹط¹ ط§ظ„ط¹ظ†ط§طµط± ظ…ظˆط¬ظˆط¯ط©');

  // طھظ†ط¸ظٹظپ ط£ظٹ cropper ظ‚ط¯ظٹظ… ظ…ظ† ط§ط³طھط¯ط¹ط§ط، ط³ط§ط¨ظ‚
  if (elements.image.cropperInstance) {
    try { elements.image.cropperInstance.destroy(); } catch(e) {}
    elements.image.cropperInstance = null;
  }
  // طھظ†ط¸ظٹظپ ط£ظٹ cropper container ظ…طھط¨ظ‚ظچ ظ…ظ† Cropper.js
  const cropArea = elements.image.parentElement;
  if (cropArea) {
    cropArea.querySelectorAll('.cropper-container, .cropper-bg').forEach(el => el.remove());
    // ط¥ط¹ط§ط¯ط© ط§ظ„طµظˆط±ط© ط§ظ„ط£طµظ„ظٹط©
    const originalImg = cropArea.querySelector('#cropperImage');
    if (originalImg) {
      originalImg.className = '';
      originalImg.style.cssText = '';
    }
  }

  // ط§ظ„ظ…طھط؛ظٹط±ط§طھ ط§ظ„ط±ط¦ظٹط³ظٹط©
  let cropper = null;
  let cropperReady = false;
  let timeoutTimer = null;
  let objectUrl = null;

  // ظˆط¸ط§ط¦ظپ ظ…ط³ط§ط¹ط¯ط©
  function isMobileDevice() {
    return /Android|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
  }

  function enableControls(enable = true) {
    elements.cropBtn.disabled = !enable;
    ['MoveUp', 'MoveDown', 'MoveLeft', 'MoveRight', 'ZoomIn', 'ZoomOut', 'RotateRight'].forEach(action => {
      const btn = document.getElementById('cropper' + action);
      if (btn) btn.disabled = !enable;
    });
  }

  function setupCropperControls() {
    if (!cropper) return;

    const controls = {
      MoveUp: () => cropper.move(0, -10),
      MoveDown: () => cropper.move(0, 10),
      MoveLeft: () => cropper.move(-10, 0),
      MoveRight: () => cropper.move(10, 0),
      ZoomIn: () => cropper.zoom(0.1),
      ZoomOut: () => cropper.zoom(-0.1),
      RotateRight: () => cropper.rotate(90)
    };

    Object.entries(controls).forEach(([action, handler]) => {
      const btn = document.getElementById('cropper' + action);
      if (btn) btn.onclick = handler;
    });
  }

  function cleanup() {
    if (cropper) {
      cropper.destroy();
      cropper = null;
    }
    elements.cropBtn.onclick = null;
    if (objectUrl) {
      URL.revokeObjectURL(objectUrl);
      objectUrl = null;
    }
    if (timeoutTimer) {
      clearTimeout(timeoutTimer);
      timeoutTimer = null;
    }
  }

  // ط²ط± ط§ظ„ط¥ظ„ط؛ط§ط،
  const cancelBtn = document.getElementById('cropperCancelBtn');
  if (cancelBtn) {
    cancelBtn.onclick = function() {
      cleanup();
      handleAttachmentCancelAndCleanup(file);
      callback(null);
      bootstrap.Modal.getInstance(elements.modal)?.hide();
    };
  }

  // طھظ†ط¸ظٹظپ ط¹ظ†ط¯ ط¥ط؛ظ„ط§ظ‚ ط§ظ„ظ…ظˆط¯ط§ظ„
  elements.modal.addEventListener('hidden.bs.modal', cleanup, { once: true });

  // ظ…ظ‡ظ„ط© ط²ظ…ظ†ظٹط© (5 ط¯ظ‚ط§ط¦ظ‚)
  timeoutTimer = setTimeout(() => {
    cleanup();
    bootstrap.Modal.getInstance(elements.loaderModal)?.hide();
    Swal.fire({
      icon: 'error',
      title: 'ط§ظ†طھظ‡ظ‰ ط§ظ„ظˆظ‚طھ',
      text: 'ظ„ظ… ظٹطھظ… ط§ط³طھظƒظ…ط§ظ„ ظ‚طµ ط§ظ„طµظˆط±ط© ط®ظ„ط§ظ„ ط§ظ„ظˆظ‚طھ ط§ظ„ظ…ط­ط¯ط¯'
    });
     handleAttachmentCancelAndCleanup(file);
    callback(null);
  }, 300000);

  // ط¥ظ†ط´ط§ط، Object URL ظ…ط¨ط§ط´ط±ط© (ط£ط³ط±ط¹ ظˆط£ط®ظپ ظ…ظ† FileReader + data URL)
  try {
    objectUrl = URL.createObjectURL(file);
  } catch (urlError) {
    console.error('[showCropperModal] ط®ط·ط£ ظپظٹ ط¥ظ†ط´ط§ط، URL ظ„ظ„ظ…ظ„ظپ:', urlError);
    cleanup();
    handleAttachmentCancelAndCleanup(file);
    callback(null, 'ط®ط·ط£ ظپظٹ ظ‚ط±ط§ط،ط© ط§ظ„ظ…ظ„ظپ');
    return;
  }

  elements.image.src = objectUrl;

  // طھط­ط³ظٹظ† ط­ط¬ظ… ط§ظ„طµظˆط±ط©
  elements.image.onload = () => {
    const container = elements.image.parentElement;
    const containerWidth = container.clientWidth || 800;
    const containerHeight = container.clientHeight || 600;

    elements.image.style.maxWidth = '95%';
    elements.image.style.maxHeight = '95%';

    console.log('[showCropperModal] طھظ… طھط­ظ…ظٹظ„ ط§ظ„طµظˆط±ط© ط¨ظ†ط¬ط§ط­');
    initializeCropper();
  };

  elements.image.onerror = () => {
    console.error('[showCropperModal] ط®ط·ط£ ظپظٹ طھط­ظ…ظٹظ„ ط§ظ„طµظˆط±ط©');
    cleanup();
    handleAttachmentCancelAndCleanup(file);
    callback(null, 'ط®ط·ط£ ظپظٹ طھط­ظ…ظٹظ„ ط§ظ„طµظˆط±ط©');
  };

  function initializeCropper() {
    if (cropper) {
      cropper.destroy();
      cropper = null;
    }

    // ط¥ط¹ط¯ط§ط¯ ط§ظ„طµظˆط±ط© ظ„ظ„ط¹ط±ط¶ ط§ظ„ظ…ط«ظ„ظ‰
    const isMobile = window.innerWidth <= 991;

    // طھظ†ط¸ظٹظپ ط§ظ„ط£ظ†ظ…ط§ط· ط§ظ„ط³ط§ط¨ظ‚ط©
    elements.image.style.width = '';
    elements.image.style.height = '';
    elements.image.style.minWidth = '';
    elements.image.style.minHeight = '';
    elements.image.style.maxWidth = '';
    elements.image.style.maxHeight = '';

    try {
      cropper = new Cropper(elements.image, {
        aspectRatio: NaN,
        viewMode: 1,
        responsive: true,
        restore: false,
        autoCropArea: 0.9,
        movable: true,
        zoomable: true,
        rotatable: true,
        scalable: true,
        background: false,
        guides: true,
        center: true,
        highlight: true,
        cropBoxMovable: true,
        cropBoxResizable: true,
        toggleDragModeOnDblclick: false,
        minCropBoxWidth: 100,
        minCropBoxHeight: 100,
        modal: true,
        ready() {
          console.log('[showCropperModal] âœ… طھظ… طھظ‡ظٹط¦ط© ط£ط¯ط§ط© ط§ظ„ظ‚طµ ط¨ظ†ط¬ط§ط­');
          cropperReady = true;
          enableControls(true);
          setupCropperControls();

          // طھط­ط³ظٹظ† ط§ظ„ط¹ط±ط¶ ط§ظ„ط£ظˆظ„ظٹ
          setTimeout(() => {
            try {
              const containerData = cropper.getContainerData();
              const canvasData = cropper.getCanvasData();
              const imageData = cropper.getImageData();

              console.log('Container:', containerData);
              console.log('Canvas:', canvasData);
              console.log('Image:', imageData);

              // طھط¹ظٹظٹظ† ظ…ظ†ط·ظ‚ط© ط§ظ„ظ‚طµ ط§ظ„ظ…ط«ظ„ظ‰
              const cropWidth = Math.min(canvasData.width * 0.8, containerData.width * 0.8);
              const cropHeight = Math.min(canvasData.height * 0.8, containerData.height * 0.8);

              cropper.setCropBoxData({
                left: canvasData.left + (canvasData.width - cropWidth) / 2,
                top: canvasData.top + (canvasData.height - cropHeight) / 2,
                width: cropWidth,
                height: cropHeight
              });

              // طھط£ظƒط¯ ظ…ظ† ط¸ظ‡ظˆط± ط§ظ„طµظˆط±ط© ط¨ط§ظ„ظƒط§ظ…ظ„
              if (canvasData.width < containerData.width || canvasData.height < containerData.height) {
                const scaleX = containerData.width / imageData.naturalWidth;
                const scaleY = containerData.height / imageData.naturalHeight;
                const scale = Math.min(scaleX, scaleY) * 0.8;
                cropper.zoomTo(scale);
              }

            } catch (e) {
              console.warn('طھط­ط°ظٹط± ظپظٹ ط¥ط¹ط¯ط§ط¯ ظ…ظ†ط·ظ‚ط© ط§ظ„ظ‚طµ:', e);
            }
          }, 500);
        },
        error(err) {
          console.error('[showCropperModal] ط®ط·ط£ ظپظٹ طھظ‡ظٹط¦ط© ط£ط¯ط§ط© ط§ظ„ظ‚طµ:', err);
          cleanup();
          callback(null, 'ط®ط·ط£ ظپظٹ طھظ‡ظٹط¦ط© ط£ط¯ط§ط© ط§ظ„ظ‚طµ');
        }
      });

      elements.image.cropperInstance = cropper;

    } catch (error) {
      console.error('[showCropperModal] ط§ط³طھط«ظ†ط§ط، ظپظٹ ط¥ظ†ط´ط§ط، ط£ط¯ط§ط© ط§ظ„ظ‚طµ:', error);
      cleanup();
      callback(null, 'ط§ط³طھط«ظ†ط§ط، ظپظٹ ط£ط¯ط§ط© ط§ظ„ظ‚طµ');
    }
  }

  // â•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گ
  // طھط±ظ…ظٹظ… ط§ظ„طµظˆط±ط© ط¹ظ„ظ‰ ط§ظ„ط®ط§ط¯ظ… ط¹ظ†ط¯ ظپط´ظ„ ط§ظ„ظپط­طµ (ط§ظ„طµظˆط± ط§ظ„ط´ط®طµظٹط© file_type = 12)
  // ظٹط¹ظٹط¯ canvas ط¬ط§ظ‡ط² ظ„ط¥ط¹ط§ط¯ط© ط§ظ„ظپط­طµ ظپظٹ ط§ظ„ظ…طھطµظپط­ ط¹ط¨ط± checkFaceOnCanvas
  // â•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گ
  async function restoreFaceOnServer(sourceCanvas, fileType) {
    try {
      const blob = await new Promise((resolve, reject) => {
        sourceCanvas.toBlob((b) => (b ? resolve(b) : reject(new Error('toBlob failed'))), 'image/jpeg', 0.95);
      });
      if (!blob) return null;

      const form = new FormData();
      form.append('image', blob, 'face_restore_input.jpg');
      form.append('file_type', String(fileType));

      const controller = new AbortController();
      const timer = setTimeout(() => controller.abort(), 130000);
      const response = await fetch('/api/face/restore', {
        method: 'POST',
        body: form,
        signal: controller.signal
      });
      clearTimeout(timer);

      if (!response.ok) {
        console.warn('[FaceRestore] ط§ط³طھط¬ط§ط¨ط© ط؛ظٹط± ظ†ط§ط¬ط­ط©:', response.status);
        return null;
      }

      const data = await response.json();
      if (!data || data.ok !== true || !data.image) {
        console.warn('[FaceRestore] ظ„ظ… ظٹطھظ… ط§ظ„طھط±ظ…ظٹظ…:', data && data.reason);
        return null;
      }

      const img = await new Promise((resolve, reject) => {
        const el = new Image();
        el.onload = () => resolve(el);
        el.onerror = () => reject(new Error('image load failed'));
        el.src = data.image;
      });

      const outCanvas = document.createElement('canvas');
      outCanvas.width = img.naturalWidth || img.width;
      outCanvas.height = img.naturalHeight || img.height;
      const ctx = outCanvas.getContext('2d');
      if (!ctx) return null;
      ctx.drawImage(img, 0, 0);

      console.log('[FaceRestore] âœ… طھظ… ط§ط³طھظ„ط§ظ… طµظˆط±ط© ظ…ط±ظ…ظ‘ظ…ط©:', outCanvas.width + 'x' + outCanvas.height, data.restore);
      return outCanvas;
    } catch (e) {
      console.warn('[FaceRestore] ظپط´ظ„ ط§ظ„طھط±ظ…ظٹظ…:', e && (e.name === 'AbortError' ? 'timeout' : e.message));
      return null;
    }
  }

  // ظ…ط¹ط§ظ„ط¬ ط²ط± ط§ظ„ظ‚طµ - ط§ظ„ظ†ط³ط®ط© ط§ظ„ظ…طµط­ط­ط©
  elements.cropBtn.onclick = async function() {
    if (!cropperReady || !cropper) {
      Swal.fire({
        icon: 'error',
        title: 'ط®ط·ط£',
        text: 'ط£ط¯ط§ط© ط§ظ„ظ‚طµ ط؛ظٹط± ط¬ط§ظ‡ط²ط©'
      });
      return;
    }

    console.log('[CropButton] ط¨ط¯ط، ط¹ظ…ظ„ظٹط© ط§ظ„ظ‚طµ ظˆط§ظ„ظ…ط¹ط§ظ„ط¬ط© ظ„ظ„ظ…ظ„ظپ:', file.name);

    // ط¹ط±ط¶ ط´ط±ظٹط· ط§ظ„طھظ‚ط¯ظ… ط§ظ„ظ…ط­ط¯ط«
    Swal.fire({
      title: 'ط¬ط§ط±ظٹ ظ…ط¹ط§ظ„ط¬ط© ط§ظ„طµظˆط±ط©...',
      html: `
        <div class="progress mb-3" style="height: 20px;">
          <div class="progress-bar progress-bar-striped progress-bar-animated"
               role="progressbar"
               style="width: 10%"
               id="processingProgress">10%</div>
        </div>
        <div class="text-muted" id="statusText">
          <small>ط¨ط¯ط، ط¹ظ…ظ„ظٹط© ط§ظ„ظ‚طµ...</small>
        </div>
      `,
      allowOutsideClick: false,
      showConfirmButton: false
    });

    bootstrap.Modal.getInstance(elements.modal).hide();

    try {
      // طھط­ط¯ظٹط« ط´ط±ظٹط· ط§ظ„طھظ‚ط¯ظ… - ط¨ط¯ط، ط§ظ„ظ‚طµ
      const progressBar = document.getElementById('processingProgress');
      const statusText = document.getElementById('statusText');

      if (progressBar && statusText) {
        progressBar.style.width = '30%';
        progressBar.textContent = '30%';
        statusText.innerHTML = '<small>ط¬ط§ط±ظٹ ظ‚طµ ط§ظ„طµظˆط±ط©...</small>';
      }

      let canvas = cropper.getCroppedCanvas({
        imageSmoothingQuality: 'high',
        fillColor: '#ffffff'
      });

      if (!canvas) {
        throw new Error('ظپط´ظ„ ظپظٹ ظ‚طµ ط§ظ„طµظˆط±ط©');
      }

      console.log('[CropButton] طھظ… ط¥ظ†ط´ط§ط، canvas ظ…ظ‚طµظˆطµ ط¨ظ†ط¬ط§ط­');

      // â•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گ
      // ظپط­طµ ط§ظ„ظˆط¬ظ‡ Client-Side ط¨ط¹ط¯ ط§ظ„ظ‚طµ ظ…ط¨ط§ط´ط±ط© (ظپظ‚ط· ظ„ظ„طµظˆط± ط§ظ„ط´ط®طµظٹط©)
      // â•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گ
      const fileType = file._fileType || 'image';
      if (fileType === 'document') {
        console.log('[FaceCheck] âڈ­ï¸ڈ طھط®ط·ظٹ ظپط­طµ ط§ظ„ظˆط¬ظ‡ - ط§ظ„ظˆط«ظٹظ‚ط© ظ„ط§ طھطھط·ظ„ط¨ ظپط­طµ:', file.name);
      } else {
        if (progressBar && statusText) {
          progressBar.style.width = '40%';
          progressBar.textContent = '40%';
          statusText.innerHTML = '<small>ط¬ط§ط±ظٹ ظپط­طµ ط§ظ„ظˆط¬ظ‡...</small>';
        }

        const modelsLoaded = await loadFaceModels();
        if (modelsLoaded) {
          const faceResult = await checkFaceOnCanvas(canvas);
          if (!faceResult.valid) {
            // ظ…ط­ط§ظˆظ„ط© طھط±ظ…ظٹظ… ط§ظ„طµظˆط±ط© ط¹ظ„ظ‰ ط§ظ„ط®ط§ط¯ظ… ط«ظ… ط¥ط¹ط§ط¯ط© ط§ظ„ظپط­طµ ظ‡ظ†ط§
            // (file_type = 12 طھط®ط±ط¬ 400x600طŒ ظˆط¨ظ‚ظٹط© ط§ظ„ط£ظ†ظˆط§ط¹ طھط®ط±ط¬ ط¨ظ…ظ‚ط§ط³ظ‡ط§ ط§ظ„ط£طµظ„ظٹ)
            let restoredCanvas = null;
            if (progressBar && statusText) {
              progressBar.style.width = '45%';
              progressBar.textContent = '45%';
              statusText.innerHTML = '<small>ط¬ط§ط±ظٹ طھط­ط³ظٹظ† ط§ظ„طµظˆط±ط© ظˆط¥ط¹ط§ط¯ط© ط§ظ„ظپط­طµ...</small>';
            }

            restoredCanvas = await restoreFaceOnServer(canvas, fileType);
            if (restoredCanvas) {
              const modelsAgain = await loadFaceModels();
              const retryResult = modelsAgain
                ? await checkFaceOnCanvas(restoredCanvas)
                : { valid: false, reason: 'طھط¹ط°ط± ط¥ط¹ط§ط¯ط© طھط­ظ…ظٹظ„ ظ†ط¸ط§ظ… ظپط­طµ ط§ظ„ظˆط¬ظ‡' };

              if (retryResult.valid) {
                canvas = restoredCanvas;
                console.log('[FaceCheck] âœ… طھظ… ظ‚ط¨ظˆظ„ ط§ظ„طµظˆط±ط© ط¨ط¹ط¯ ط§ظ„طھط±ظ…ظٹظ…');
              } else {
                console.warn('[FaceCheck] âڑ ï¸ڈ ط§ظ„طµظˆط±ط© ط§ظ„ظ…ط±ظ…ظ‘ظ…ط© ظ„ظ… طھط¬طھط² ط§ظ„ظپط­طµ:', retryResult.reason);
                restoredCanvas = null;
              }
            }

            if (!restoredCanvas) {
              Swal.close();
              Swal.fire({
                icon: 'warning',
                title: 'طµظˆط±ط© ط؛ظٹط± ظ…ظ‚ط¨ظˆظ„ط©',
                text: faceResult.reason,
                confirmButtonText: 'ط§ط®طھط± طµظˆط±ط© ط£ط®ط±ظ‰',
                allowOutsideClick: false
              }).then(() => {
                cleanup();
                // ظپطھط­ ط­ظˆط§ط± ط§ط®طھظٹط§ط± ظ…ظ„ظپ ط¬ط¯ظٹط¯ ط¹ط¨ط± ظ…ظˆط¯ط§ظ„ ط§ظ„ظ†ط¸ط§ظ… ط§ظ„ظ…ط®طµطµ ط¨ط¯ظ„ ظ†ط§ظپط°ط© ط£ظ†ط¯ط±ظˆظٹط¯
                if (window.DeviceImageSource && typeof window.DeviceImageSource.showModal === 'function') {
                  window.DeviceImageSource.showModal(function(newFile) {
                    if (newFile) {
                      newFile._fileType = fileType;
                      window.showCropperModal(newFile, callback);
                    }
                  });
                } else {
                  const fileInput = document.createElement('input');
                  fileInput.type = 'file';
                  fileInput.accept = 'image/*';
                  fileInput.style.display = 'none';
                  fileInput.onchange = (e) => {
                    const newFile = e.target.files[0];
                    fileInput.remove();
                    if (newFile) {
                      newFile._fileType = fileType;
                      window.showCropperModal(newFile, callback);
                    }
                  };
                  document.body.appendChild(fileInput);
                  fileInput.click();
                }
              });
              return;
            }
          }
          console.log('[FaceCheck] âœ… طھظ… ط§ظ„طھط­ظ‚ظ‚ ظ…ظ† ط§ظ„ظˆط¬ظ‡ ط¨ظ†ط¬ط§ط­');
        } else {
          // ظپط´ظ„ طھط­ظ…ظٹظ„ ط§ظ„ظ†ظ…ط§ط°ط¬ = ط±ظپط¶ ط§ظ„ط±ظپط¹ ط¨ط¯ظ„ط§ظ‹ ظ…ظ† ط§ظ„طھط®ط·ظٹ ط§ظ„طµط§ظ…طھ
          Swal.close();
          Swal.fire({
            icon: 'error',
            title: 'ط®ط·ط£ ظپظٹ ط§ظ„طھط­ظ‚ظ‚',
            text: 'طھط¹ط°ط± طھط­ظ…ظٹظ„ ظ†ط¸ط§ظ… ظپط­طµ ط§ظ„ظˆط¬ظ‡. ظٹط±ط¬ظ‰ ط§ظ„طھط­ظ‚ظ‚ ظ…ظ† ط§طھطµط§ظ„ ط§ظ„ط¥ظ†طھط±ظ†طھ ظˆ ط§ظ„ظ…ط­ط§ظˆظ„ط© ظ…ط±ط© ط£ط®ط±ظ‰.',
            confirmButtonText: 'ط­ط³ظ†ط§ظ‹'
          });
          cleanup();
          callback(null, 'ظپط´ظ„ طھط­ظ…ظٹظ„ ظ†ط¸ط§ظ… ظپط­طµ ط§ظ„ظˆط¬ظ‡');
          return;
        }
      }

      // طھط­ط¯ظٹط« ط´ط±ظٹط· ط§ظ„طھظ‚ط¯ظ… - طھط­ظˆظٹظ„ ط¥ظ„ظ‰ blob
      if (progressBar && statusText) {
        progressBar.style.width = '50%';
        progressBar.textContent = '50%';
        statusText.innerHTML = '<small>ط¬ط§ط±ظٹ طھط­ظˆظٹظ„ ط§ظ„طµظˆط±ط©...</small>';
      }

      // طھط­ظˆظٹظ„ canvas ط¥ظ„ظ‰ blob
      const blob = await new Promise((resolve, reject) => {
        canvas.toBlob((blob) => {
          if (blob) {
            resolve(blob);
          } else {
            reject(new Error('ظپط´ظ„ ظپظٹ طھط­ظˆظٹظ„ ط§ظ„طµظˆط±ط©'));
          }
        }, file.type, 0.9);
      });

      console.log('[CropButton] طھظ… طھط­ظˆظٹظ„ canvas ط¥ظ„ظ‰ blob:', blob.size, 'bytes');

      // ط¥ظ†ط´ط§ط، ط§ط³ظ… ظ…ظ„ظپ ط¬ط¯ظٹط¯ ظ…ط¹ ط¥ط´ط§ط±ط© ط£ظ†ظ‡ ظ…ظ‚طµظˆطµ
      const originalName = file.name.replace(/\.[^/.]+$/, '');
      const extension = file.name.match(/\.[^/.]+$/)?.[0] || '.jpg';
      const croppedFileName = `${originalName}_cropped${extension}`;

      // ط¥ظ†ط´ط§ط، ظ…ظ„ظپ ط¬ط¯ظٹط¯
      const croppedFile = new File([blob], croppedFileName, {
        type: file.type,
        lastModified: Date.now()
      });
      // ظ†ظ‚ظ„ _fileType ظ…ظ† ط§ظ„ظ…ظ„ظپ ط§ظ„ط£طµظ„ظٹ ظ„ظ„ظ…ظ„ظپ ط§ظ„ظ…ظ‚طµظˆطµ
      croppedFile._fileType = fileType;

      console.log('[CropButton] طھظ… ط¥ظ†ط´ط§ط، ظ…ظ„ظپ ظ…ظ‚طµظˆطµ:', {
        name: croppedFile.name,
        size: croppedFile.size,
        type: croppedFile.type
      });

      // طھط­ط¯ظٹط« ط´ط±ظٹط· ط§ظ„طھظ‚ط¯ظ… - ظپط­طµ ط§ظ„ط­ط§ط¬ط© ظ„ظ„ط¶ط؛ط·
      if (progressBar && statusText) {
        progressBar.style.width = '70%';
        progressBar.textContent = '70%';
        statusText.innerHTML = '<small>ظپط­طµ ط§ظ„ط­ط§ط¬ط© ظ„ظ„ط¶ط؛ط·...</small>';
      }

      let finalFile = croppedFile;

      // ظپط­طµ: ظ‡ظ„ ط§ظ„ط¶ط؛ط· ظ…ظپط¹ظ‘ظ„ ظ…ظ† ط§ظ„ط¥ط¹ط¯ط§ط¯طں
      const shouldCompress = window._compressAttachmentsEnabled !== false;

      // ط¶ط؛ط· ط§ظ„ظ…ظ„ظپ ط§ظ„ظ…ظ‚طµظˆطµ ظپظ‚ط· ط¥ط°ط§ ظƒط§ظ† ط£ظƒط¨ط± ظ…ظ† 100KB ظˆط§ظ„ط¶ط؛ط· ظ…ظپط¹ظ‘ظ„
      if (shouldCompress && croppedFile.size > 100 * 1024) {
        console.log('[CropButton] ط§ظ„ظ…ظ„ظپ ظٹط­طھط§ط¬ ظ„ظ„ط¶ط؛ط·:', croppedFile.size, 'bytes');

        if (statusText) {
          statusText.innerHTML = '<small>ط¬ط§ط±ظٹ ط¶ط؛ط· ط§ظ„طµظˆط±ط©...</small>';
        }

        try {
          // ط¶ط؛ط· ط¨ط¯ظˆظ† ط¹ط±ط¶ ظˆط§ط¬ظ‡ط© ط¥ط¶ط§ظپظٹط©
          const options = {
            maxSizeMB: 0.1, // 100KB
            maxWidthOrHeight: 1920,
            useWebWorker: true,
            fileType: croppedFile.type,
            initialQuality: 0.8,
            alwaysKeepResolution: false
          };

          finalFile = await imageCompression(croppedFile, options);
          console.log('[CropButton] طھظ… ط¶ط؛ط· ط§ظ„ظ…ظ„ظپ ط¨ظ†ط¬ط§ط­:', finalFile.size, 'bytes');

          // طھط­ط¯ظٹط« ط§ط³ظ… ط§ظ„ظ…ظ„ظپ ظ„ظٹطھط¶ظ…ظ† ط£ظ†ظ‡ ظ…ط¶ط؛ظˆط· ط£ظٹط¶ط§ظ‹
          const compressedName = finalFile.name.replace('_cropped', '_cropped_compressed');
          finalFile = new File([finalFile], compressedName, {
            type: finalFile.type,
            lastModified: Date.now()
          });

        } catch (compressionError) {
          console.warn('[CropButton] ظپط´ظ„ ظپظٹ ط¶ط؛ط· ط§ظ„ظ…ظ„ظپطŒ ط³ظٹطھظ… ط§ط³طھط®ط¯ط§ظ… ط§ظ„ظ†ط³ط®ط© ط§ظ„ظ…ظ‚طµظˆطµط©:', compressionError);
          // ط§ط³طھط®ط¯ظ… ط§ظ„ظ…ظ„ظپ ط§ظ„ظ…ظ‚طµظˆطµ ط¨ط¯ظˆظ† ط¶ط؛ط· ظپظٹ ط­ط§ظ„ط© ظپط´ظ„ ط§ظ„ط¶ط؛ط·
        }
      } else {
        if (!shouldCompress) {
          console.log('[CropButton] ط§ظ„ط¶ط؛ط· ظ…ط¹ط·ظ‘ظ„ ظ…ظ† ط¥ط¹ط¯ط§ط¯ط§طھ ط§ظ„ط¬ظ…ط¹ظٹط©');
        } else {
          console.log('[CropButton] ط§ظ„ظ…ظ„ظپ طµط؛ظٹط± ط¨ظ…ط§ ظپظٹظ‡ ط§ظ„ظƒظپط§ظٹط©طŒ ظ„ط§ ط­ط§ط¬ط© ظ„ظ„ط¶ط؛ط·');
        }
      }

      // طھط­ط¯ظٹط« ط´ط±ظٹط· ط§ظ„طھظ‚ط¯ظ… - ط§ظ†طھظ‡ط§ط، ط§ظ„ظ…ط¹ط§ظ„ط¬ط©
      if (progressBar && statusText) {
        progressBar.style.width = '100%';
        progressBar.textContent = '100%';
        statusText.innerHTML = '<small>طھظ…طھ ط§ظ„ظ…ط¹ط§ظ„ط¬ط© ط¨ظ†ط¬ط§ط­!</small>';
      }

      console.log('[CropButton] ط§ظ„ظ…ظ„ظپ ط§ظ„ظ†ظ‡ط§ط¦ظٹ:', {
        name: finalFile.name,
        size: finalFile.size,
        type: finalFile.type
      });

      // ط¥ط®ظپط§ط، ط´ط±ظٹط· ط§ظ„طھظ‚ط¯ظ… ط¨ط¹ط¯ ظپطھط±ط© ظ‚طµظٹط±ط©
      setTimeout(() => {
        Swal.close();
        cleanup();
        console.log('[CropButton] طھظ… ط¥ظ†ط¬ط§ط² ط§ظ„ظ…ط¹ط§ظ„ط¬ط© ط¨ظ†ط¬ط§ط­طŒ ط§ط³طھط¯ط¹ط§ط، callback');
        callback(finalFile);
      }, 800);

    } catch (error) {
      console.error('[CropButton] ط®ط·ط£ ظپظٹ ط§ظ„ظ…ط¹ط§ظ„ط¬ط©:', error);
      Swal.fire({
        icon: 'error',
        title: 'ط®ط·ط£ ظپظٹ ط§ظ„ظ…ط¹ط§ظ„ط¬ط©',
        text: 'ظپط´ظ„ ظپظٹ ظ…ط¹ط§ظ„ط¬ط© ط§ظ„طµظˆط±ط©: ' + error.message
      });
      cleanup();
      handleAttachmentCancelAndCleanup(file);
      callback(null);
    }
  };

  // ط¹ط±ط¶ ط§ظ„ظ…ظˆط¯ط§ظ„ ظ…ط¹ ط¶ظ…ط§ظ† ط§ظ„ط­ط¬ظ… ط§ظ„طµط­ظٹط­
  const modal = new bootstrap.Modal(elements.modal, {
    backdrop: 'static',
    keyboard: false,
    focus: true
  });

  // ط¶ظ…ط§ظ† ط¥ط¹ط§ط¯ط© طھظ‡ظٹط¦ط© Cropper ط¹ظ†ط¯ ط¹ط±ط¶ ط§ظ„ظ…ظˆط¯ط§ظ„
  elements.modal.addEventListener('shown.bs.modal', function modalShownHandler() {
    console.log('[showCropperModal] طھظ… ط¹ط±ط¶ ط§ظ„ظ…ظˆط¯ط§ظ„');

    setTimeout(() => {
      if (elements.image.naturalWidth > 0) {
        if (cropper) {
          cropper.destroy();
          cropper = null;
        }
        initializeCropper();
      } else {
        console.warn('[showCropperModal] ط§ظ„طµظˆط±ط© ظ„ظ… طھط­ظ…ظ„ ط¨ط¹ط¯طŒ ط¥ط¹ط§ط¯ط© ظ…ط­ط§ظˆظ„ط©...');
        setTimeout(() => {
          if (elements.image.naturalWidth > 0) {
            initializeCropper();
          }
        }, 500);
      }
    }, 200);

    // ط¥ط²ط§ظ„ط© ط§ظ„ظ…ط³طھظ…ط¹ ط¨ط¹ط¯ ط£ظˆظ„ ط§ط³طھط®ط¯ط§ظ…
    elements.modal.removeEventListener('shown.bs.modal', modalShownHandler);
  });

  modal.show();
};

// ظ…ط¹ط§ظ„ط¬ط© ط§ظ„ظ…ط³ط§ظپط© ط§ظ„ط¨ظٹط¶ط§ط، ط£ط³ظپظ„ ط§ظ„ط£ط²ط±ط§ط± ظپظٹ ط§ظ„ط¬ظˆط§ظ„
function fixCropperAreaHeight() {
  const modal = document.getElementById('cropperModal');
  const cropperBody = document.querySelector('.cropper-modal-body');
  const cropArea = document.querySelector('.crop-area');
  const actionBtns = document.querySelector('.action-buttons');
  const header = modal ? modal.querySelector('.modal-header') : null;

  if (
    window.innerWidth <= 767.98 &&
    modal && cropperBody && cropArea && actionBtns && header
  ) {
    // ط­ط³ط§ط¨ ط§ظ„ظ…ط³ط§ط­ط© ط§ظ„ظ…طھط§ط­ط©
    const headerHeight = header.offsetHeight;
    const actionBtnsHeight = actionBtns.offsetHeight;
    const total = window.innerHeight;
    const available = total - headerHeight - actionBtnsHeight;

    cropperBody.style.height = (total - headerHeight) + 'px';
    cropArea.style.height = available + 'px';
    cropArea.style.minHeight = '0';
    cropArea.style.maxHeight = 'none';
    cropArea.style.margin = '0';
    cropArea.style.padding = '0';
  } else if (cropArea) {
    // ط¥ط¹ط§ط¯ط© طھط¹ظٹظٹظ† ظپظٹ ط§ظ„ط´ط§ط´ط§طھ ط§ظ„ظƒط¨ظٹط±ط©
    cropArea.style.height = '';
    cropArea.style.minHeight = '';
    cropArea.style.maxHeight = '';
    cropArea.style.margin = '';
    cropArea.style.padding = '';
  }
}

// ط§ط³طھط¯ط¹ط§ط، ط¹ظ†ط¯ ظپطھط­ ط§ظ„ظ…ظˆط¯ط§ظ„ ظˆط¹ظ†ط¯ طھط؛ظٹظٹط± ط§ظ„ط­ط¬ظ…
window.addEventListener('resize', fixCropperAreaHeight);
document.addEventListener('shown.bs.modal', function(e) {
  if (e.target && e.target.id === 'cropperModal') {
    setTimeout(fixCropperAreaHeight, 100);
  }
});
// ط¥ط¶ط§ظپط© ظ‡ط°ط§ ط§ظ„ط³ط·ط± ظ„ط¶ظ…ط§ظ† ط§ظ„ط¶ط¨ط· ط¹ظ†ط¯ ط£ظˆظ„ طھط­ظ…ظٹظ„ ظ„ظ„طµظپط­ط©
document.addEventListener('DOMContentLoaded', fixCropperAreaHeight);

// FileUploadHandler ظ„ظ„ظ…ظ†ط§ط·ظ‚ ط§ظ„ظ…طھط¹ط¯ط¯ط©
document.addEventListener('DOMContentLoaded', function() {
  const uploadZones = document.querySelectorAll('[data-upload-role="zone"]');

  uploadZones.forEach(zone => {
    const fileInput = zone.querySelector('[data-file-role="input"]');
    const selectBtn = zone.querySelector('[data-file-role="select"]');
    const preview = zone.querySelector('[data-file-role="preview"]');
    const hiddenFileId = zone.querySelector('[data-file-role="file_id"]');
    const hiddenTempName = zone.querySelector('[data-file-role="temp_file_name"]');
    const hiddenDocType = zone.querySelector('[data-file-role="document_type_value"]');

    if (selectBtn && fileInput) {
      selectBtn.addEventListener('click', (e) => {
        e.preventDefault();
        fileInput.click();
      });
    }

    if (fileInput) {
      fileInput.addEventListener('change', async (e) => {
        const file = fileInput.files[0];
        if (!file) return;

        console.log('[FileInput] طھظ… ط§ط®طھظٹط§ط± ظ…ظ„ظپ:', {
          name: file.name,
          size: file.size,
          type: file.type
        });

        try {
          if (file.type.startsWith('image/')) {
            console.log('[FileInput] ط¨ط¯ط، ط¹ط±ط¶ ط£ط¯ط§ط© ط§ظ„ظ‚طµ ظ„ظ„ظ…ظ„ظپ:', file.name);

            // ط¹ط±ط¶ ط£ط¯ط§ط© ط§ظ„ظ‚طµ ظ…ط¨ط§ط´ط±ط© ط¨ط¯ظˆظ† ط¶ط؛ط· ظ…ط³ط¨ظ‚
            window.showCropperModal(file, (processedFile) => {
              if (processedFile) {
                console.log('[FileInput] طھظ… ط§ط³طھظ„ط§ظ… ط§ظ„ظ…ظ„ظپ ط§ظ„ظ…ط¹ط§ظ„ط¬:', {
                  name: processedFile.name,
                  size: processedFile.size,
                  isCropped: processedFile.name.includes('_cropped')
                });
                uploadFileAJAX(processedFile, zone, preview, hiddenFileId, hiddenTempName, hiddenDocType);
              } else {
                console.error('[FileInput] ظ„ظ… ظٹطھظ… ط§ط³طھظ„ط§ظ… ظ…ظ„ظپ ظ…ط¹ط§ظ„ط¬');
                fileInput.value = '';
                Swal.fire({
                  icon: 'warning',
                  title: 'طھظ… ط§ظ„ط¥ظ„ط؛ط§ط،',
                  text: 'طھظ… ط¥ظ„ط؛ط§ط، ط¹ظ…ظ„ظٹط© ظ…ط¹ط§ظ„ط¬ط© ط§ظ„طµظˆط±ط©'
                });
              }
            });
          } else {
            // ظ„ظ„ظ…ظ„ظپط§طھ ط؛ظٹط± ط§ظ„طµظˆط±طŒ ط±ظپط¹ ظ…ط¨ط§ط´ط±
            console.log('[FileInput] ظ…ظ„ظپ ط؛ظٹط± طµظˆط±ط©طŒ ط±ظپط¹ ظ…ط¨ط§ط´ط±');
            uploadFileAJAX(file, zone, preview, hiddenFileId, hiddenTempName, hiddenDocType);
          }
        } catch (error) {
          console.error('[FileInput] ط®ط·ط£ ظپظٹ ظ…ط¹ط§ظ„ط¬ط© ط§ظ„ظ…ظ„ظپ:', error);
          Swal.fire({
            icon: 'error',
            title: 'ط®ط·ط£',
            text: 'ط­ط¯ط« ط®ط·ط£ ط£ط«ظ†ط§ط، ظ…ط¹ط§ظ„ط¬ط© ط§ظ„ظ…ظ„ظپ'
          });
          fileInput.value = '';
        }
      });
    }
  });

  function uploadFileAJAX(file, zone, preview, hiddenFileId, hiddenTempName, hiddenDocType) {
    console.log('[uploadFileAJAX] ط¨ط¯ط، ط±ظپط¹ ط§ظ„ظ…ظ„ظپ:', {
      fileName: file.name,
      fileSize: file.size,
      fileType: file.type,
      docType: hiddenDocType?.value,
      isCropped: file.name.includes('_cropped'),
      isCompressed: file.name.includes('_compressed')
    });

    // ط§ظ„طھط­ظ‚ظ‚ ظ…ظ† ط£ظ† ط§ظ„ظ…ظ„ظپ طµط§ظ„ط­
    if (!file || !(file instanceof File)) {
      console.error('[uploadFileAJAX] ط§ظ„ظ…ظ„ظپ ط؛ظٹط± طµط§ظ„ط­:', file);
      Swal.fire({
        icon: 'error',
        title: 'ط®ط·ط£',
        text: 'ط§ظ„ظ…ظ„ظپ ط؛ظٹط± طµط§ظ„ط­'
      });
      return;
    }

    const loaderModal = bootstrap.Modal.getOrCreateInstance(document.getElementById('compressLoaderModal'));
    loaderModal?.show();

    // ط¥ظ†ط´ط§ط، FormData ط¬ط¯ظٹط¯ ظ…ط¹ ط§ظ„طھط­ظ‚ظ‚ ظ…ظ† ط§ظ„ط¨ظٹط§ظ†ط§طھ
    const formData = new FormData();

    // ط¥ط¶ط§ظپط© ط§ظ„ظ…ظ„ظپ ظ…ط¹ ط§ظ„طھط£ظƒط¯ ظ…ظ† ط§ظ„ط§ط³ظ… ط§ظ„طµط­ظٹط­
    formData.append('file', file, file.name);

    // ط¥ط¶ط§ظپط© ظ…ط¹ط±ظپ CSRF
    const csrf = document.querySelector('meta[name="csrf-token"]');
    if (csrf) {
      formData.append('_token', csrf.getAttribute('content'));
    }

    // ط¥ط¶ط§ظپط© ظ†ظˆط¹ ط§ظ„ظ…ط³طھظ†ط¯
    if (hiddenDocType?.value) {
      formData.append('document_type_value', hiddenDocType.value);
      console.log('[uploadFileAJAX] ظ†ظˆط¹ ط§ظ„ظ…ط³طھظ†ط¯:', hiddenDocType.value);
    }

    // ط¥ط¶ط§ظپط© ظ…ط¹ظ„ظˆظ…ط§طھ ط¥ط¶ط§ظپظٹط© ظ„ظ„طھطھط¨ط¹
    formData.append('is_cropped', file.name.includes('_cropped') ? '1' : '0');
    formData.append('is_compressed', file.name.includes('_compressed') ? '1' : '0');
    formData.append('original_size', file.size.toString());
    formData.append('file_type', file.type);

    // ط¹ط±ط¶ ظ…ط­طھظˆظٹط§طھ FormData ظ„ظ„طھط£ظƒط¯
    console.log('[uploadFileAJAX] ظ…ط­طھظˆظٹط§طھ FormData:');
    for (let [key, value] of formData.entries()) {
      if (value instanceof File) {
        console.log(`${key}: [File] ${value.name} (${value.size} bytes, ${value.type})`);
      } else {
        console.log(`${key}: ${value}`);
      }
    }

    fetch('/ajax/file-upload', {
      method: 'POST',
      body: formData,
      headers: {
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest'
      }
    })
    .then(response => {
      console.log('[uploadFileAJAX] ط§ط³طھط¬ط§ط¨ط© ط§ظ„ط®ط§ط¯ظ… - ط§ظ„ط­ط§ظ„ط©:', response.status);
      if (!response.ok) {
        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
      }
      return response.json();
    })
    .then(data => {
      loaderModal?.hide();
      console.log('[uploadFileAJAX] ط¨ظٹط§ظ†ط§طھ ط§ظ„ط§ط³طھط¬ط§ط¨ط©:', data);

      if (data.success && data.file_id && data.temp_file_name) {
        console.log('[uploadFileAJAX] طھظ… ط±ظپط¹ ط§ظ„ظ…ظ„ظپ ط¨ظ†ط¬ط§ط­:', {
          fileId: data.file_id,
          tempFileName: data.temp_file_name,
          originalFileName: file.name
        });

        if (hiddenFileId) hiddenFileId.value = data.file_id;
        if (hiddenTempName) hiddenTempName.value = data.temp_file_name;

        if (preview && file.type.startsWith('image/')) {
          const reader = new FileReader();
          reader.onload = (e) => {
            preview.src = e.target.result;
            preview.style.display = '';
            console.log('[uploadFileAJAX] طھظ… ط¹ط±ط¶ ظ…ط¹ط§ظٹظ†ط© ط§ظ„طµظˆط±ط©');
          };
          reader.readAsDataURL(file);
        } else if (preview) {
          preview.textContent = file.name;
          preview.style.display = '';
          console.log('[uploadFileAJAX] طھظ… ط¹ط±ط¶ ط§ط³ظ… ط§ظ„ظ…ظ„ظپ');
        }

        // ط¥ط¸ظ‡ط§ط± ط±ط³ط§ظ„ط© ظ†ط¬ط§ط­
        Swal.fire({
          icon: 'success',
          title: 'طھظ… ط§ظ„ط±ظپط¹ ط¨ظ†ط¬ط§ط­',
          text: `طھظ… ط±ظپط¹ ظˆظ…ط¹ط§ظ„ط¬ط© ط§ظ„ظ…ظ„ظپ ط¨ظ†ط¬ط§ط­`,
          timer: 2000,
          showConfirmButton: false
        });

      } else {
        console.error('[uploadFileAJAX] ظپط´ظ„ ط±ظپط¹ ط§ظ„ظ…ظ„ظپ:', data);
        Swal.fire({
          icon: 'error',
          title: 'ط®ط·ط£ ظپظٹ ط§ظ„ط±ظپط¹',
          text: data.message || 'ظپط´ظ„ ط±ظپط¹ ط§ظ„ظ…ظ„ظپ'
        });
        resetFields();
      }
    })
    .catch(err => {
      loaderModal?.hide();
      console.error('[uploadFileAJAX] ط®ط·ط£ ظپظٹ ط§ظ„ط´ط¨ظƒط©:', err);
      Swal.fire({
        icon: 'error',
        title: 'ط®ط·ط£ ظپظٹ ط§ظ„ط´ط¨ظƒط©',
        text: 'ط­ط¯ط« ط®ط·ط£ ط£ط«ظ†ط§ط، ط±ظپط¹ ط§ظ„ظ…ظ„ظپ. ظٹط±ط¬ظ‰ ط§ظ„ظ…ط­ط§ظˆظ„ط© ظ…ط±ط© ط£ط®ط±ظ‰.'
      });
      resetFields();
    });

    function resetFields() {
      if (hiddenFileId) hiddenFileId.value = '';
      if (hiddenTempName) hiddenTempName.value = '';
      if (preview) preview.style.display = 'none';
      console.log('[uploadFileAJAX] طھظ… ط¥ط¹ط§ط¯ط© طھط¹ظٹظٹظ† ط§ظ„ط­ظ‚ظˆظ„');
    }
  }

  // ...existing code...
});

