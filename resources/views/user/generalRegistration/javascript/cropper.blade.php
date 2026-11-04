@include('user.generalRegistration.layout.cropperStyle')
@include('user.generalRegistration.layout.cropperHtml')

<script src="{{ asset('js/browser-image-compression.js') }}"></script>
<script src="{{ asset('js/heic2any.min.js') }}"></script>
<script src="{{ asset('js/face-api.js') }}"></script>

<script>
// ═══════════════════════════════════════════════════════════════
// فحص الوجه Client-Side عبر face-api.js — محرك مسح متعدّد المقاييس
// ═══════════════════════════════════════════════════════════════
// الأرقام هنا مطابقة لـ CFG في scripts/face-lib.js (فحص الخادم) — غيّر في المكانين معاً.
const FACE_CFG = {
  maxWorkSide: 1600,          // توحيد كانفس العمل (حفاظاً على النسبة وعلى حد النسيج في GPU)
  tileOverlap: 0.2,
  timeBudgetMs: 9000,         // ميزانية الفحص في المتصفح (أقل من الخادم لأنه على CPU/Mobile GPU)
  minFaceRelWidth: 0.06,
  minFaceAbsWidth: 40,
  significantFaceRelWidth: 0.10,
  eyeSpanOverBoxWidth: [0.22, 0.78],
  eyeRollMax: 0.25,
  eyeYRange: [0.18, 0.72],
  mouthYawMax: 0.26,
  jawOverEyeSpanMin: 0.90,
  sweeps: {
    full: [
      { kind: 'frame', confidence: 0.50 },
      { kind: 'tiles', scale: 0.72, confidence: 0.50, maxTiles: 4 },
      { kind: 'frame', confidence: 0.35 },
      { kind: 'tiles', scale: 0.50, confidence: 0.42, maxTiles: 6 },
      { kind: 'tiles', scale: 0.34, confidence: 0.35, maxTiles: 8 }
    ],
    coarse: [
      { kind: 'frame', confidence: 0.50 },
      { kind: 'tiles', scale: 0.72, confidence: 0.50, maxTiles: 4 }
    ],
    frameOnly: [
      { kind: 'frame', confidence: 0.50 }
    ]
  },
  rotationPlan: [
    { deg: 0, sweep: 'full' },
    { deg: 90, sweep: 'coarse' },
    { deg: 270, sweep: 'coarse' },
    { deg: 180, sweep: 'frameOnly' }
  ],
  // نجاح الفحص على الصورة المرمَّمة (من الذكاء الاصطناعي) يُقبل دليلاً على وجود وجه،
  // لكن الصورة المؤرشفة تبقى دائماً الأصلَ بدون أي توليد أو تعديل.
  restoreAsProof: true,
  serverVerify: { url: @json(route('face.verify')) },
  serverRestore: { url: @json(route('face.restore')) },
  report: { url: @json(route('face.report')) }
};

const FACE_REASONS = {
  'no-face': 'لم يتم العثور على وجه واضح — اجعل الوجه أمامياً وكاملاً وغير مغطّى',
  'multi-face': 'الصورة تحتوي على أكثر من وجه — يرجى إرسال صورة لشخص واحد فقط',
  'face-too-small': 'حجم الوجه صغير جداً بالنسبة للصورة — قرّب الصورة من الوجه',
  'face-turned-side': 'الوجه غير مواجه للكاميرا — استخدم صورة أمامية',
  'face-tilted': 'الوجه مائل كثيراً — استخدم صورة مستقيمة للوجه',
  'face-framing-off': 'الوجه غير مؤطَّر داخل الصورة — اجعل العينين قرب الثلث العلوي',
  'eyes-unresolved': 'العينان غير واضحتين — أزل ما يحجبهما أو استخدم صورة أوضح',
  'jaw-unresolved': 'أجزاء من الوجه غير ظاهرة (غطاء أو قصّ) — أكشف الوجه كاملاً',
  'landmarks-incomplete': 'تعذر تحليل تفاصيل الوجه — استخدم صورة وجه واضحة',
  'landmarks-invalid': 'معالم الوجه غير واضحة — استخدم صورة وجه واضحة',
  'face-ratio-unusual': 'لم تظهر ملامح الوجه كاملة (غطاء أو وضعية غير واضحة) — استخدم صورة أمامية مكشوفة الوجه',
  'busy': 'الخادم مشغول حالياً',
  'timeout': 'انتهت مهلة الفحص',
  'engine-error': 'تعذّر تشغيل نظام الفحص'
};

// أكواد تخص جودة الصورة فقط — الترميم المساعد لا يُجرَّب إلا عليها.
// face-ratio-unusual مستثنى عمداً: هو بصمة الوجه المغطّى (نقاب/كمامة)، والترميم
// قد يولّد ملامح غير موجودة فيقبل صورة لا وجه مكشوف فيها.
const FACE_QUALITY_CODES = ['no-face', 'face-too-small', 'eyes-unresolved', 'jaw-unresolved', 'landmarks-incomplete', 'landmarks-invalid'];

function setFaceProgress(progressBar, statusText, pct, label) {
  if (!progressBar || !statusText) return;
  progressBar.style.width = pct + '%';
  progressBar.textContent = pct + '%';
  statusText.innerHTML = '<small>' + label + '</small>';
}

let _faceModelsLoaded = false;
const FACE_ENGINE = { backend: null };

async function loadFaceModels() {
  if (_faceModelsLoaded) return true;

  if (typeof faceapi === 'undefined') {
    console.error('[FaceCheck] مكتبة face-api.js غير محملة');
    return false;
  }

  // دقة fp16 على بعض GPUs تُنتج قيماً عديمة المعنى → ثقة صفر بلا أي خطأ ظاهر
  try {
    faceapi.tf.env().set('WEBGL_F16_MATMUL_ENABLE', false);
  } catch (e) { /* نسخة أقدم من tfjs */ }

  const backends = ['webgl', 'wasm', 'cpu'];
  let ready = false;

  for (const name of backends) {
    try {
      await faceapi.tf.setBackend(name);
      await faceapi.tf.ready();
      FACE_ENGINE.backend = name;
      ready = true;
      console.log('[FaceCheck] backend =', name);
      break;
    } catch (e) {
      console.warn('[FaceCheck] backend غير متاح:', name);
    }
  }

  if (!ready) return false;

  const MODEL_SOURCES = [
    '{{ asset("scripts/models") }}',
    'https://cdn.jsdelivr.net/npm/@vladmandic/face-api@1.7.15/model'
  ];

  for (const MODEL_URL of MODEL_SOURCES) {
    try {
      await faceapi.nets.ssdMobilenetv1.loadFromUri(MODEL_URL);
      await faceapi.nets.faceLandmark68Net.loadFromUri(MODEL_URL);
      _faceModelsLoaded = true;
      console.log('[FaceCheck] تم تحميل نماذج فحص الوجه من:', MODEL_URL);
      return true;
    } catch (err) {
      console.warn('[FaceCheck] فشل التحميل من:', MODEL_URL, err.message);
    }
  }

  return false;
}

// ───────── أدوات الكانفس ─────────

/** تحجيم مع الحفاظ على النسبة إلى maxWorkSide (يحلّ حدّ النسيج في GPU والضوضاء عند السحق) */
function toWorkCanvas(canvas, maxSide) {
  const longest = Math.max(canvas.width, canvas.height);

  if (longest <= maxSide) return canvas;

  const scale = maxSide / longest;
  const w = Math.max(1, Math.round(canvas.width * scale));
  const h = Math.max(1, Math.round(canvas.height * scale));
  const out = document.createElement('canvas');
  out.width = w;
  out.height = h;
  const ctx = out.getContext('2d');
  ctx.imageSmoothingQuality = 'high';
  ctx.fillStyle = '#ffffff';
  ctx.fillRect(0, 0, w, h);
  ctx.drawImage(canvas, 0, 0, w, h);

  return out;
}

function cropCanvas(src, x, y, size) {
  const out = document.createElement('canvas');
  out.width = size;
  out.height = size;
  const ctx = out.getContext('2d');
  ctx.imageSmoothingQuality = 'high';
  ctx.drawImage(src, x, y, size, size, 0, 0, size, size);

  return out;
}

function rotateCanvas(src, deg) {
  if (!deg) return src;

  const out = document.createElement('canvas');
  out.width = deg === 180 ? src.width : src.height;
  out.height = deg === 180 ? src.height : src.width;
  const ctx = out.getContext('2d');
  ctx.translate(out.width / 2, out.height / 2);
  ctx.rotate(deg * Math.PI / 180);
  ctx.drawImage(src, -src.width / 2, -src.height / 2);

  return out;
}

function releaseCanvas(canvas) {
  if (!canvas) return;
  canvas.width = 0;
  canvas.height = 0;
}

// ───────── الكشف ─────────

async function detectAllIn(canvas, minConfidence) {
  const detections = await faceapi
    .detectAllFaces(canvas, new faceapi.SsdMobilenetv1Options({ minConfidence }))
    .withFaceLandmarks();

  if (!detections || !detections.length) return [];

  return detections.map((d) => ({
    x: d.detection.box.x,
    y: d.detection.box.y,
    width: d.detection.box.width,
    height: d.detection.box.height,
    score: d.detection.score,
    landmarks: (d.landmarks && d.landmarks.positions ? d.landmarks.positions : []).map((p) => ({ x: p.x, y: p.y }))
  }));
}

function tileAxis(fullSide, tileSide, overlap) {
  const step = Math.max(1, Math.round(tileSide * (1 - overlap)));
  const raw = [];

  for (let o = 0; o < fullSide; o += step) {
    raw.push(Math.max(0, Math.min(o, fullSide - tileSide)));
    if (o + tileSide >= fullSide) break;
  }

  return [...new Set(raw)];
}

function centerOutTiles(w, h, tileSide, maxTiles) {
  const xs = tileAxis(w, tileSide, FACE_CFG.tileOverlap);
  const ys = tileAxis(h, tileSide, FACE_CFG.tileOverlap);
  const cx = w / 2, cy = h / 2;
  const list = [];

  for (const tx of xs) {
    for (const ty of ys) list.push([tx, ty]);
  }

  list.sort((a, b) => Math.hypot(a[0] + tileSide / 2 - cx, a[1] + tileSide / 2 - cy)
    - Math.hypot(b[0] + tileSide / 2 - cx, b[1] + tileSide / 2 - cy));

  return list.slice(0, maxTiles);
}

function faceIou(a, b) {
  const x1 = Math.max(a.x, b.x), y1 = Math.max(a.y, b.y);
  const x2 = Math.min(a.x + a.width, b.x + b.width);
  const y2 = Math.min(a.y + a.height, b.y + b.height);
  const inter = Math.max(0, x2 - x1) * Math.max(0, y2 - y1);
  const union = a.width * a.height + b.width * b.height - inter;

  return union > 0 ? inter / union : 0;
}

function dedupeFaces(list) {
  const sorted = [...list].sort((a, b) => b.score - a.score);
  const kept = [];

  for (const d of sorted) {
    if (!kept.some((k) => faceIou(k, d) > 0.35)) kept.push(d);
  }

  return kept;
}

/** مرحلة مسح واحدة (إطار كامل أو مربعات من المركز للأطراف) */
async function runSweep(canvas, sweep, deadline, sink) {
  const w = canvas.width, h = canvas.height;

  if (sweep.kind === 'frame') {
    if (Date.now() > deadline) return null;

    const found = await detectAllIn(canvas, sweep.confidence);
    sink.push(...found);
    if (!found.length) return null;

    return found.sort((a, b) => b.score - a.score)[0];
  }

  const tileSide = Math.max(64, Math.min(Math.min(w, h), Math.round(Math.min(w, h) * sweep.scale)));

  if (tileSide >= Math.max(w, h)) {
    return runSweep(canvas, { kind: 'frame', confidence: sweep.confidence }, deadline, sink);
  }

  for (const [tx, ty] of centerOutTiles(w, h, tileSide, sweep.maxTiles || 4)) {
    if (Date.now() > deadline) return null;

    const tile = cropCanvas(canvas, tx, ty, tileSide);
    const found = await detectAllIn(tile, sweep.confidence);
    releaseCanvas(tile);

    for (const d of found) {
      d.x += tx; d.y += ty;
      if (d.landmarks) d.landmarks = d.landmarks.map((p) => ({ x: p.x + tx, y: p.y + ty }));
      sink.push(d);
    }

    if (found.length) return found.sort((a, b) => b.score - a.score)[0];
  }

  return null;
}

function avgPoints(lm, a, b) {
  let x = 0, y = 0;
  for (let i = a; i < b; i++) { x += lm[i].x; y += lm[i].y; }

  return { x: x / (b - a), y: y / (b - a) };
}

/** تحقّق هندسي حقيقي بدل عدّ النقاط فقط */
function validateFaceGeometry(face, w, h) {
  const lm = face.landmarks;

  if (!lm || lm.length < 68) return { ok: false, code: 'landmarks-incomplete' };

  if (!lm.every((p) => p && isFinite(p.x) && isFinite(p.y))) return { ok: false, code: 'landmarks-invalid' };

  const boxW = Math.max(1, face.width);
  const boxH = Math.max(1, face.height);
  const leftEye = avgPoints(lm, 36, 42);
  const rightEye = avgPoints(lm, 42, 48);
  const mouth = avgPoints(lm, 48, 68);
  const eyeDist = Math.hypot(rightEye.x - leftEye.x, rightEye.y - leftEye.y);

  if (eyeDist < 2) return { ok: false, code: 'eyes-unresolved' };

  const spanRatio = eyeDist / boxW;
  if (spanRatio < FACE_CFG.eyeSpanOverBoxWidth[0] || spanRatio > FACE_CFG.eyeSpanOverBoxWidth[1]) {
    return { ok: false, code: 'face-ratio-unusual' };
  }

  const roll = Math.abs(rightEye.y - leftEye.y) / eyeDist;
  if (roll > FACE_CFG.eyeRollMax) return { ok: false, code: 'face-tilted' };

  const relEyeY = ((leftEye.y + rightEye.y) / 2 - face.y) / boxH;
  if (relEyeY < FACE_CFG.eyeYRange[0] || relEyeY > FACE_CFG.eyeYRange[1]) {
    return { ok: false, code: 'face-framing-off' };
  }

  const yaw = Math.abs(mouth.x - (leftEye.x + rightEye.x) / 2) / boxW;
  if (yaw > FACE_CFG.mouthYawMax) return { ok: false, code: 'face-turned-side' };

  const jaw = lm.slice(0, 17);
  const jawWidth = Math.max(...jaw.map((p) => p.x)) - Math.min(...jaw.map((p) => p.x));
  if (jawWidth / eyeDist < FACE_CFG.jawOverEyeSpanMin) return { ok: false, code: 'jaw-unresolved' };

  if (boxW < FACE_CFG.minFaceAbsWidth || boxW / w < FACE_CFG.minFaceRelWidth) {
    return { ok: false, code: 'face-too-small' };
  }

  return { ok: true, metrics: { faceWidthPx: Math.round(boxW), relWidth: +(boxW / w).toFixed(3), score: +face.score.toFixed(3) } };
}

/**
 * الفحص الكامل على كانفس.
 * يُرجع { valid, code, detail, score, rotatedDeg, rotatedCanvas, work, ms, engineError }
 */
async function checkFaceOnCanvas(rawCanvas) {
  const t0 = Date.now();
  const deadline = t0 + FACE_CFG.timeBudgetMs;
  const work = toWorkCanvas(rawCanvas, FACE_CFG.maxWorkSide);
  let lastCode = 'no-face';

  try {
    for (const step of FACE_CFG.rotationPlan) {
      if (Date.now() > deadline) break;

      const frame = rotateCanvas(work, step.deg);
      // الصناديق بإحداثيات الإطار المُدار، فالنسب تُحسب على عرضه لا على عرض الصورة الأصلية
      const frameW = frame.width;
      const frameH = frame.height;
      const plan = FACE_CFG.sweeps[step.sweep] || FACE_CFG.sweeps.coarse;
      const sink = [];
      let face = null;

      for (const sweep of plan) {
        if (Date.now() > deadline) break;
        face = await runSweep(frame, sweep, deadline, sink);
        if (face) break;
      }

      if (frame !== work) releaseCanvas(frame);

      if (!face) {
        lastCode = 'no-face';
        continue;
      }

      const candidates = dedupeFaces(sink);
      const significant = candidates.filter((d) => d.width / frameW >= FACE_CFG.significantFaceRelWidth);

      if (significant.length > 1) {
        return { valid: false, code: 'multi-face', detail: FACE_REASONS['multi-face'], faces: significant.length, ms: Date.now() - t0 };
      }

      const v = validateFaceGeometry(face, frameW, frameH);

      if (!v.ok) {
        lastCode = v.code;
        // وجه جانبي/مائل عند 0° قد يعني أن الصورة مقلوبة: جرّب الاتجاهات الأخرى
        if (step.deg === 0 && ['face-turned-side', 'face-tilted', 'face-framing-off'].includes(v.code)) continue;

        return {
          valid: false, code: v.code, detail: FACE_REASONS[v.code] || 'وجه غير واضح',
          score: +face.score.toFixed(3), ms: Date.now() - t0
        };
      }

      let outCanvas = null;

      if (step.deg) {
        // الصورة مقلوبة (EXIF غير مخبوز): تدويرها يجعل الفحص والأرشيف صحيحين
        outCanvas = rotateCanvas(rawCanvas, step.deg);
      }

      return {
        valid: true,
        code: 'ok',
        score: +face.score.toFixed(3),
        rotatedDeg: step.deg,
        rotatedCanvas: outCanvas,
        box: face.box || { x: face.x, y: face.y, width: face.width, height: face.height },
        metrics: v.metrics,
        ms: Date.now() - t0
      };
    }

    return { valid: false, code: lastCode, detail: FACE_REASONS[lastCode] || FACE_REASONS['no-face'], ms: Date.now() - t0 };
  } catch (err) {
    console.error('[FaceCheck] خطأ المحرك:', err && err.message);
    // خطأ محرك (سياق WebGL مفقود/نسيج كبير) ليس دليلاً على غياب الوجه
    return { valid: false, code: 'engine-error', detail: 'تعذّر تشغيل فحص الوجه في المتصفح', engineError: true, ms: Date.now() - t0 };
  } finally {
    if (work !== rawCanvas) releaseCanvas(work);
  }
}

// ───────── تقارير + فحوص خادمية ─────────

// المسار العام يحتوي وسم csrf-token، أما نسخة لوحة المستخدم فقد لا تحتويه — رمزه من Blade مباشرة
const FACE_CSRF = @json(csrf_token());

function faceCsrfForm() {
  const meta = document.querySelector('meta[name="csrf-token"]');
  const token = meta ? meta.getAttribute('content') : '';
  return token || FACE_CSRF;
}

function reportFaceCheck(payload) {
  try {
    const body = Object.assign({}, payload, { _token: faceCsrfForm() });
    fetch(FACE_CFG.report.url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': body._token },
      body: JSON.stringify(body),
      keepalive: true
    }).catch(() => {});
  } catch (e) { /* القياس لا يجب أن يعطّل الرفع */ }
}

async function canvasToJpegBlob(canvas, quality) {
  return new Promise((resolve) => {
    canvas.toBlob((b) => resolve(b || null), 'image/jpeg', quality || 0.92);
  });
}

/** رأي الخادم النهائي: محرك Node مطابق للعتبات، غير متأثر بضعف GPU */
async function verifyFaceOnServer(canvas, fileType, clientCode) {
  try {
    const blob = await canvasToJpegBlob(canvas, 0.92);
    if (!blob) return { ok: false, code: 'engine-error' };

    const form = new FormData();
    form.append('image', blob, 'face_verify.jpg');
    form.append('file_type', String(fileType || ''));
    form.append('client_code', String(clientCode || ''));
    form.append('_token', faceCsrfForm());

    const controller = new AbortController();
    const timer = setTimeout(() => controller.abort(), 45000);
    const response = await fetch(FACE_CFG.serverVerify.url, { method: 'POST', body: form, signal: controller.signal, headers: { 'Accept': 'application/json' } });
    clearTimeout(timer);

    if (!response.ok) return { ok: false, code: 'http-' + response.status };

    const data = await response.json();
    return {
      ok: !!data.ok,
      pass: !!data.pass,
      code: data.code,
      detail: data.detail,
      score: data.score,
      rotated: data.rotated || 0,
      ms: data.ms || 0,
      busy: data.code === 'busy'
    };
  } catch (e) {
    console.warn('[FaceVerify] فشل:', e && (e.name === 'AbortError' ? 'timeout' : e.message));
    return { ok: false, code: e && e.name === 'AbortError' ? 'timeout' : 'engine-error' };
  }
}

// دالة تحويل HEIC إلى JPEG
async function convertHeicToJpeg(file) {
  const isHeic = file.type === 'image/heic' || file.type === 'image/heif' ||
                 file.name.toLowerCase().endsWith('.heic') || file.name.toLowerCase().endsWith('.heif');

  if (!isHeic) return file;

  console.log('[convertHeicToJpeg] تحويل ملف HEIC إلى JPEG:', file.name);

  // انتظار تحميل heic2any إذا لم يكن محملاً بعد
  if (typeof heic2any === 'undefined') {
    console.log('[convertHeicToJpeg] انتظار تحميل heic2any...');
    await new Promise((resolve, reject) => {
      let attempts = 0;
      const check = setInterval(() => {
        attempts++;
        if (typeof heic2any !== 'undefined') {
          clearInterval(check);
          resolve();
        } else if (attempts > 50) { // 5 ثوانٍ
          clearInterval(check);
          reject(new Error('مكتبة heic2any لم تُحمّل'));
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

    // heic2any قد يُرجع مصفوفة blobs
    const blob = Array.isArray(jpegBlob) ? jpegBlob[0] : jpegBlob;

    const newName = file.name.replace(/\.(heic|heif)$/i, '.jpg');
    const convertedFile = new File([blob], newName, {
      type: 'image/jpeg',
      lastModified: Date.now()
    });

    console.log('[convertHeicToJpeg] تم التحويل بنجاح:', convertedFile.name, convertedFile.size);
    return convertedFile;

  } catch (error) {
    console.error('[convertHeicToJpeg] فشل التحويل:', error);
    throw new Error('فشل في تحويل صورة HEIC');
  }
}
window.showCropperModal = async function(file, callback) {
  console.log('[showCropperModal] بدء فحص الملف:', file);

  // التحقق من صحة الملف
  if (!file || !(file instanceof File) && !(file instanceof Blob)) {
    console.error('[showCropperModal] ملف غير صالح:', file);
    if (callback) callback(null, 'ملف غير صالح');
    return;
  }

  // فحص: هل الملف صورة (بما فيها HEIC)?
  const isImage = (file.type && file.type.startsWith('image/')) ||
                  file.name.toLowerCase().endsWith('.heic') ||
                  file.name.toLowerCase().endsWith('.heif');

  if (!isImage) {
    console.error('[showCropperModal] الملف ليس صورة:', file.type);
    if (callback) callback(null, 'الملف ليس صورة');
    return;
  }

  // تحويل HEIC إلى JPEG إذا لزم الأمر
  const preservedFileType = file._fileType;
  try {
    file = await convertHeicToJpeg(file);
    if (preservedFileType && !file._fileType) {
      file._fileType = preservedFileType;
    }
  } catch (heicError) {
    console.error('[showCropperModal] خطأ في تحويل HEIC:', heicError);
    if (callback) callback(null, 'فشل في تحويل صورة HEIC');
    return;
  }

  if (file.size === 0 || file.size > 50 * 1024 * 1024) {
    console.error('[showCropperModal] حجم الملف غير صالح:', file.size);
    if (callback) callback(null, 'حجم الملف غير صالح');
    return;
  }

  // الحصول على العناصر المطلوبة
  const elements = {
    modal: document.getElementById('cropperModal'),
    image: document.getElementById('cropperImage'),
    cropBtn: document.getElementById('cropperCropBtn'),
    loaderModal: document.getElementById('compressLoaderModal')
  };

  // التحقق من وجود العناصر
  for (const [key, element] of Object.entries(elements)) {
    if (!element) {
      console.error(`[showCropperModal] عنصر ${key} مفقود`);
      if (callback) callback(null, `عنصر ${key} مفقود`);
      return;
    }
  }

  console.log('[showCropperModal] ✅ جميع العناصر موجودة');

  // تنظيف أي cropper قديم من استدعاء سابق
  if (elements.image.cropperInstance) {
    try { elements.image.cropperInstance.destroy(); } catch(e) {}
    elements.image.cropperInstance = null;
  }
  // تنظيف أي cropper container متبقٍ من Cropper.js
  const cropArea = elements.image.parentElement;
  if (cropArea) {
    cropArea.querySelectorAll('.cropper-container, .cropper-bg').forEach(el => el.remove());
    // إعادة الصورة الأصلية
    const originalImg = cropArea.querySelector('#cropperImage');
    if (originalImg) {
      originalImg.className = '';
      originalImg.style.cssText = '';
    }
  }

  // المتغيرات الرئيسية
  let cropper = null;
  let cropperReady = false;
  let timeoutTimer = null;
  let objectUrl = null;

  // وظائف مساعدة
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

  // زر الإلغاء
  const cancelBtn = document.getElementById('cropperCancelBtn');
  if (cancelBtn) {
    cancelBtn.onclick = function() {
      cleanup();
      handleAttachmentCancelAndCleanup(file);
      callback(null);
      bootstrap.Modal.getInstance(elements.modal)?.hide();
    };
  }

  // تنظيف عند إغلاق المودال
  elements.modal.addEventListener('hidden.bs.modal', cleanup, { once: true });

  // مهلة زمنية (5 دقائق)
  timeoutTimer = setTimeout(() => {
    cleanup();
    bootstrap.Modal.getInstance(elements.loaderModal)?.hide();
    Swal.fire({
      icon: 'error',
      title: 'انتهى الوقت',
      text: 'لم يتم استكمال قص الصورة خلال الوقت المحدد'
    });
     handleAttachmentCancelAndCleanup(file);
    callback(null);
  }, 300000);

  // إنشاء Object URL مباشرة (أسرع وأخف من FileReader + data URL)
  try {
    objectUrl = URL.createObjectURL(file);
  } catch (urlError) {
    console.error('[showCropperModal] خطأ في إنشاء URL للملف:', urlError);
    cleanup();
    handleAttachmentCancelAndCleanup(file);
    callback(null, 'خطأ في قراءة الملف');
    return;
  }

  elements.image.src = objectUrl;

  // تحسين حجم الصورة
  elements.image.onload = () => {
    const container = elements.image.parentElement;
    const containerWidth = container.clientWidth || 800;
    const containerHeight = container.clientHeight || 600;

    elements.image.style.maxWidth = '95%';
    elements.image.style.maxHeight = '95%';

    console.log('[showCropperModal] تم تحميل الصورة بنجاح');
    initializeCropper();
  };

  elements.image.onerror = () => {
    console.error('[showCropperModal] خطأ في تحميل الصورة');
    cleanup();
    handleAttachmentCancelAndCleanup(file);
    callback(null, 'خطأ في تحميل الصورة');
  };

  function initializeCropper() {
    if (cropper) {
      cropper.destroy();
      cropper = null;
    }

    // إعداد الصورة للعرض المثلى
    const isMobile = window.innerWidth <= 991;

    // تنظيف الأنماط السابقة
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
          console.log('[showCropperModal] ✅ تم تهيئة أداة القص بنجاح');
          cropperReady = true;
          enableControls(true);
          setupCropperControls();

          // تحسين العرض الأولي
          setTimeout(() => {
            try {
              const containerData = cropper.getContainerData();
              const canvasData = cropper.getCanvasData();
              const imageData = cropper.getImageData();

              console.log('Container:', containerData);
              console.log('Canvas:', canvasData);
              console.log('Image:', imageData);

              // تعيين منطقة القص المثلى
              const cropWidth = Math.min(canvasData.width * 0.8, containerData.width * 0.8);
              const cropHeight = Math.min(canvasData.height * 0.8, containerData.height * 0.8);

              cropper.setCropBoxData({
                left: canvasData.left + (canvasData.width - cropWidth) / 2,
                top: canvasData.top + (canvasData.height - cropHeight) / 2,
                width: cropWidth,
                height: cropHeight
              });

              // تأكد من ظهور الصورة بالكامل
              if (canvasData.width < containerData.width || canvasData.height < containerData.height) {
                const scaleX = containerData.width / imageData.naturalWidth;
                const scaleY = containerData.height / imageData.naturalHeight;
                const scale = Math.min(scaleX, scaleY) * 0.8;
                cropper.zoomTo(scale);
              }

            } catch (e) {
              console.warn('تحذير في إعداد منطقة القص:', e);
            }
          }, 500);
        },
        error(err) {
          console.error('[showCropperModal] خطأ في تهيئة أداة القص:', err);
          cleanup();
          callback(null, 'خطأ في تهيئة أداة القص');
        }
      });

      elements.image.cropperInstance = cropper;

    } catch (error) {
      console.error('[showCropperModal] استثناء في إنشاء أداة القص:', error);
      cleanup();
      callback(null, 'استثناء في أداة القص');
    }
  }

  // ═══════════════════════════════════════════════════════════════════
  // ترميم الصورة على الخادم — مساعد للفحص فقط: نجاحه دليل على وجود وجه،
  // لكن كانفس الأصل هو ما يُرفع ويُؤرشف دون أي تغيير (لا تُؤرشف صورة مولّدة).
  // (file_type = 12 تخرج 400x600 في فحص الخادم، وبقية الأنواع بمقاسها الأصلي)
  // ═══════════════════════════════════════════════════════════════════
  async function restoreFaceOnServer(sourceCanvas, fileType) {
    try {
      const blob = await new Promise((resolve, reject) => {
        sourceCanvas.toBlob((b) => (b ? resolve(b) : reject(new Error('toBlob failed'))), 'image/jpeg', 0.95);
      });
      if (!blob) return null;

      const form = new FormData();
      form.append('image', blob, 'face_restore_input.jpg');
      form.append('file_type', String(fileType));
      form.append('_token', faceCsrfForm());

      const controller = new AbortController();
      const timer = setTimeout(() => controller.abort(), 130000);
      const response = await fetch(FACE_CFG.serverRestore.url, {
        method: 'POST',
        body: form,
        signal: controller.signal,
        headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': faceCsrfForm() }
      });
      clearTimeout(timer);

      if (!response.ok) {
        console.warn('[FaceRestore] استجابة غير ناجحة:', response.status);
        return null;
      }

      const data = await response.json();
      if (!data || data.ok !== true || !data.image) {
        console.warn('[FaceRestore] لم يتم الترميم:', data && data.reason);
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

      console.log('[FaceRestore] ✅ تم استلام صورة مرمّمة:', outCanvas.width + 'x' + outCanvas.height, data.restore);
      return outCanvas;
    } catch (e) {
      console.warn('[FaceRestore] فشل الترميم:', e && (e.name === 'AbortError' ? 'timeout' : e.message));
      return null;
    }
  }

  // معالج زر القص - النسخة المصححة
  elements.cropBtn.onclick = async function() {
    if (!cropperReady || !cropper) {
      Swal.fire({
        icon: 'error',
        title: 'خطأ',
        text: 'أداة القص غير جاهزة'
      });
      return;
    }

    console.log('[CropButton] بدء عملية القص والمعالجة للملف:', file.name);

    // عرض شريط التقدم المحدث
    Swal.fire({
      title: 'جاري معالجة الصورة...',
      html: `
        <div class="progress mb-3" style="height: 20px;">
          <div class="progress-bar progress-bar-striped progress-bar-animated"
               role="progressbar"
               style="width: 10%"
               id="processingProgress">10%</div>
        </div>
        <div class="text-muted" id="statusText">
          <small>بدء عملية القص...</small>
        </div>
      `,
      allowOutsideClick: false,
      showConfirmButton: false
    });

    bootstrap.Modal.getInstance(elements.modal).hide();

    try {
      // تحديث شريط التقدم - بدء القص
      const progressBar = document.getElementById('processingProgress');
      const statusText = document.getElementById('statusText');

      if (progressBar && statusText) {
        progressBar.style.width = '30%';
        progressBar.textContent = '30%';
        statusText.innerHTML = '<small>جاري قص الصورة...</small>';
      }

      let canvas = cropper.getCroppedCanvas({
        imageSmoothingQuality: 'high',
        fillColor: '#ffffff'
      });

      if (!canvas) {
        throw new Error('فشل في قص الصورة');
      }

      console.log('[CropButton] تم إنشاء canvas مقصوص بنجاح');

      // ═══════════════════════════════════════════════════════════════
      // فحص الوجه: المتصفح أولاً، ثم الخادم كمرجع أدق، والترميم مساعد فحص فقط
      // ═══════════════════════════════════════════════════════════════
      const fileType = file._fileType || 'image';
      if (fileType === 'document') {
        console.log('[FaceCheck] ⏭️ تخطي فحص الوجه - الوثيقة لا تتطلب فحص:', file.name);
      } else {
        setFaceProgress(progressBar, statusText, 40, 'جاري فحص الوجه...');

        const modelsLoaded = await loadFaceModels();
        const client = modelsLoaded
          ? await checkFaceOnCanvas(canvas)
          : { valid: false, code: 'engine-error', detail: FACE_REASONS['engine-error'], engineError: true, ms: 0 };

        reportFaceCheck({
          stage: 'client',
          file_type: fileType,
          code: client.code,
          detail: client.detail,
          score: client.score || 0,
          rotated: client.rotatedDeg || 0,
          ms: client.ms || 0,
          engine: modelsLoaded ? (FACE_ENGINE.backend || 'unknown') : 'models-failed',
          canvas: canvas.width + 'x' + canvas.height
        });

        // صورة مقلوبة (EXIF غير مخبوز في المتصفح): التدوير يصحّح الفحص والأرشفة معاً
        if (client.rotatedCanvas) {
          canvas = client.rotatedCanvas;
        }

        let verdict = client;

        // الخادم يُستدعى عند رفض المتصفح أو عند تعطل محركه — لا على المسار الناجح
        if (!client.valid) {
          setFaceProgress(progressBar, statusText, 45, 'جاري تأكيد الفحص على الخادم...');

          const server = await verifyFaceOnServer(canvas, fileType, client.code);
          reportFaceCheck({
            stage: 'server',
            file_type: fileType,
            code: server.code || (server.pass ? 'ok' : 'unknown'),
            detail: server.detail,
            score: server.score || 0,
            ms: server.ms || 0,
            engine: 'node'
          });

          if (server.ok && server.pass) {
            verdict = { valid: true, code: 'ok', score: server.score || 0, via: 'server' };
          } else if (server.ok && !server.pass) {
            // رفض حقيقي من المحرك الأدق
            verdict = { valid: false, code: server.code, detail: FACE_REASONS[server.code] || server.detail, score: server.score || 0, via: 'server' };
          } else if (client.engineError) {
            // المحركان معاً غير متاحين → لا نوقف تسجيل يتيم بسبب عطل قياس
            verdict = { valid: true, code: 'fail-open', detail: 'تعذّر الفحص الآلي، تم القبول للمراجعة' };
            console.warn('[FaceCheck] ⚠️ fail-open: المتصفح والخادم غير متاحين');
          }
        }

        // الترميم مساعد للفحص فقط: نجاحه دليل على وجود وجه، لكن المُؤرشف يبقى الأصل
        if (!verdict.valid && FACE_CFG.restoreAsProof && FACE_QUALITY_CODES.includes(verdict.code)) {
          setFaceProgress(progressBar, statusText, 48, 'جاري تحسين الصورة وإعادة الفحص...');

          const restoredCanvas = await restoreFaceOnServer(canvas, fileType);
          if (restoredCanvas) {
            reportFaceCheck({
              stage: 'restore-aid', file_type: fileType, code: 'ok',
              detail: 'تم القبول بعد ترميم مساعد (الأصل هو المؤرشف)', score: verdict.score || 0, engine: 'node'
            });
            console.log('[FaceCheck] ✅ وجه مؤكد عبر الترميم المساعد — سيُحفظ الأصل بدون تغيير');
            verdict = { valid: true, code: 'ok', via: 'restore-aid' };
          } else {
            reportFaceCheck({
              stage: 'restore-aid', file_type: fileType, code: verdict.code,
              detail: 'الترميم لم ينتج وجهاً مؤكداً', engine: 'node'
            });
          }
        }

        if (!verdict.valid) {
          Swal.close();
          Swal.fire({
            icon: 'warning',
            title: 'صورة غير مقبولة',
            text: verdict.detail || FACE_REASONS['no-face'],
            confirmButtonText: 'اختر صورة أخرى',
            allowOutsideClick: false
          }).then(() => {
            cleanup();
            // فتح حوار اختيار ملف جديد عبر مودال النظام المخصص بدل نافذة أندرويد
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

        console.log('[FaceCheck] ✅ تم التحقق من الوجه بنجاح', verdict.code, verdict.via || 'client');
      }

      // تحديث شريط التقدم - تحويل إلى blob
      if (progressBar && statusText) {
        progressBar.style.width = '50%';
        progressBar.textContent = '50%';
        statusText.innerHTML = '<small>جاري تحويل الصورة...</small>';
      }

      // تحويل canvas إلى blob
      const blob = await new Promise((resolve, reject) => {
        canvas.toBlob((blob) => {
          if (blob) {
            resolve(blob);
          } else {
            reject(new Error('فشل في تحويل الصورة'));
          }
        }, file.type, 0.9);
      });

      console.log('[CropButton] تم تحويل canvas إلى blob:', blob.size, 'bytes');

      // إنشاء اسم ملف جديد مع إشارة أنه مقصوص
      const originalName = file.name.replace(/\.[^/.]+$/, '');
      const extension = file.name.match(/\.[^/.]+$/)?.[0] || '.jpg';
      const croppedFileName = `${originalName}_cropped${extension}`;

      // إنشاء ملف جديد
      const croppedFile = new File([blob], croppedFileName, {
        type: file.type,
        lastModified: Date.now()
      });
      // نقل _fileType من الملف الأصلي للملف المقصوص
      croppedFile._fileType = fileType;

      console.log('[CropButton] تم إنشاء ملف مقصوص:', {
        name: croppedFile.name,
        size: croppedFile.size,
        type: croppedFile.type
      });

      // تحديث شريط التقدم - فحص الحاجة للضغط
      if (progressBar && statusText) {
        progressBar.style.width = '70%';
        progressBar.textContent = '70%';
        statusText.innerHTML = '<small>فحص الحاجة للضغط...</small>';
      }

      let finalFile = croppedFile;

      // فحص: هل الضغط مفعّل من الإعداد؟
      const shouldCompress = window._compressAttachmentsEnabled !== false;

      // ضغط الملف المقصوص فقط إذا كان أكبر من 100KB والضغط مفعّل
      if (shouldCompress && croppedFile.size > 100 * 1024) {
        console.log('[CropButton] الملف يحتاج للضغط:', croppedFile.size, 'bytes');

        if (statusText) {
          statusText.innerHTML = '<small>جاري ضغط الصورة...</small>';
        }

        try {
          // ضغط بدون عرض واجهة إضافية
          const options = {
            maxSizeMB: 0.1, // 100KB
            maxWidthOrHeight: 1920,
            useWebWorker: true,
            fileType: croppedFile.type,
            initialQuality: 0.8,
            alwaysKeepResolution: false
          };

          finalFile = await imageCompression(croppedFile, options);
          console.log('[CropButton] تم ضغط الملف بنجاح:', finalFile.size, 'bytes');

          // تحديث اسم الملف ليتضمن أنه مضغوط أيضاً
          const compressedName = finalFile.name.replace('_cropped', '_cropped_compressed');
          finalFile = new File([finalFile], compressedName, {
            type: finalFile.type,
            lastModified: Date.now()
          });

        } catch (compressionError) {
          console.warn('[CropButton] فشل في ضغط الملف، سيتم استخدام النسخة المقصوصة:', compressionError);
          // استخدم الملف المقصوص بدون ضغط في حالة فشل الضغط
        }
      } else {
        if (!shouldCompress) {
          console.log('[CropButton] الضغط معطّل من إعدادات الجمعية');
        } else {
          console.log('[CropButton] الملف صغير بما فيه الكفاية، لا حاجة للضغط');
        }
      }

      // تحديث شريط التقدم - انتهاء المعالجة
      if (progressBar && statusText) {
        progressBar.style.width = '100%';
        progressBar.textContent = '100%';
        statusText.innerHTML = '<small>تمت المعالجة بنجاح!</small>';
      }

      console.log('[CropButton] الملف النهائي:', {
        name: finalFile.name,
        size: finalFile.size,
        type: finalFile.type
      });

      // إخفاء شريط التقدم بعد فترة قصيرة
      setTimeout(() => {
        Swal.close();
        cleanup();
        console.log('[CropButton] تم إنجاز المعالجة بنجاح، استدعاء callback');
        callback(finalFile);
      }, 800);

    } catch (error) {
      console.error('[CropButton] خطأ في المعالجة:', error);
      Swal.fire({
        icon: 'error',
        title: 'خطأ في المعالجة',
        text: 'فشل في معالجة الصورة: ' + error.message
      });
      cleanup();
      handleAttachmentCancelAndCleanup(file);
      callback(null);
    }
  };

  // عرض المودال مع ضمان الحجم الصحيح
  const modal = new bootstrap.Modal(elements.modal, {
    backdrop: 'static',
    keyboard: false,
    focus: true
  });

  // ضمان إعادة تهيئة Cropper عند عرض المودال
  elements.modal.addEventListener('shown.bs.modal', function modalShownHandler() {
    console.log('[showCropperModal] تم عرض المودال');

    setTimeout(() => {
      if (elements.image.naturalWidth > 0) {
        if (cropper) {
          cropper.destroy();
          cropper = null;
        }
        initializeCropper();
      } else {
        console.warn('[showCropperModal] الصورة لم تحمل بعد، إعادة محاولة...');
        setTimeout(() => {
          if (elements.image.naturalWidth > 0) {
            initializeCropper();
          }
        }, 500);
      }
    }, 200);

    // إزالة المستمع بعد أول استخدام
    elements.modal.removeEventListener('shown.bs.modal', modalShownHandler);
  });

  modal.show();
};

// معالجة المسافة البيضاء أسفل الأزرار في الجوال
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
    // حساب المساحة المتاحة
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
    // إعادة تعيين في الشاشات الكبيرة
    cropArea.style.height = '';
    cropArea.style.minHeight = '';
    cropArea.style.maxHeight = '';
    cropArea.style.margin = '';
    cropArea.style.padding = '';
  }
}

// استدعاء عند فتح المودال وعند تغيير الحجم
window.addEventListener('resize', fixCropperAreaHeight);
document.addEventListener('shown.bs.modal', function(e) {
  if (e.target && e.target.id === 'cropperModal') {
    setTimeout(fixCropperAreaHeight, 100);
  }
});
// إضافة هذا السطر لضمان الضبط عند أول تحميل للصفحة
document.addEventListener('DOMContentLoaded', fixCropperAreaHeight);

// FileUploadHandler للمناطق المتعددة
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

        console.log('[FileInput] تم اختيار ملف:', {
          name: file.name,
          size: file.size,
          type: file.type
        });

        try {
          if (file.type.startsWith('image/')) {
            console.log('[FileInput] بدء عرض أداة القص للملف:', file.name);

            // عرض أداة القص مباشرة بدون ضغط مسبق
            window.showCropperModal(file, (processedFile) => {
              if (processedFile) {
                console.log('[FileInput] تم استلام الملف المعالج:', {
                  name: processedFile.name,
                  size: processedFile.size,
                  isCropped: processedFile.name.includes('_cropped')
                });
                uploadFileAJAX(processedFile, zone, preview, hiddenFileId, hiddenTempName, hiddenDocType);
              } else {
                console.error('[FileInput] لم يتم استلام ملف معالج');
                fileInput.value = '';
                Swal.fire({
                  icon: 'warning',
                  title: 'تم الإلغاء',
                  text: 'تم إلغاء عملية معالجة الصورة'
                });
              }
            });
          } else {
            // للملفات غير الصور، رفع مباشر
            console.log('[FileInput] ملف غير صورة، رفع مباشر');
            uploadFileAJAX(file, zone, preview, hiddenFileId, hiddenTempName, hiddenDocType);
          }
        } catch (error) {
          console.error('[FileInput] خطأ في معالجة الملف:', error);
          Swal.fire({
            icon: 'error',
            title: 'خطأ',
            text: 'حدث خطأ أثناء معالجة الملف'
          });
          fileInput.value = '';
        }
      });
    }
  });

  function uploadFileAJAX(file, zone, preview, hiddenFileId, hiddenTempName, hiddenDocType) {
    console.log('[uploadFileAJAX] بدء رفع الملف:', {
      fileName: file.name,
      fileSize: file.size,
      fileType: file.type,
      docType: hiddenDocType?.value,
      isCropped: file.name.includes('_cropped'),
      isCompressed: file.name.includes('_compressed')
    });

    // التحقق من أن الملف صالح
    if (!file || !(file instanceof File)) {
      console.error('[uploadFileAJAX] الملف غير صالح:', file);
      Swal.fire({
        icon: 'error',
        title: 'خطأ',
        text: 'الملف غير صالح'
      });
      return;
    }

    const loaderModal = bootstrap.Modal.getOrCreateInstance(document.getElementById('compressLoaderModal'));
    loaderModal?.show();

    // إنشاء FormData جديد مع التحقق من البيانات
    const formData = new FormData();

    // إضافة الملف مع التأكد من الاسم الصحيح
    formData.append('file', file, file.name);

    // إضافة معرف CSRF
    const csrf = document.querySelector('meta[name="csrf-token"]');
    if (csrf) {
      formData.append('_token', csrf.getAttribute('content'));
    }

    // إضافة نوع المستند
    if (hiddenDocType?.value) {
      formData.append('document_type_value', hiddenDocType.value);
      console.log('[uploadFileAJAX] نوع المستند:', hiddenDocType.value);
    }

    // إضافة معلومات إضافية للتتبع
    formData.append('is_cropped', file.name.includes('_cropped') ? '1' : '0');
    formData.append('is_compressed', file.name.includes('_compressed') ? '1' : '0');
    formData.append('original_size', file.size.toString());
    formData.append('file_type', file.type);

    // عرض محتويات FormData للتأكد
    console.log('[uploadFileAJAX] محتويات FormData:');
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
      console.log('[uploadFileAJAX] استجابة الخادم - الحالة:', response.status);
      if (!response.ok) {
        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
      }
      return response.json();
    })
    .then(data => {
      loaderModal?.hide();
      console.log('[uploadFileAJAX] بيانات الاستجابة:', data);

      if (data.success && data.file_id && data.temp_file_name) {
        console.log('[uploadFileAJAX] تم رفع الملف بنجاح:', {
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
            console.log('[uploadFileAJAX] تم عرض معاينة الصورة');
          };
          reader.readAsDataURL(file);
        } else if (preview) {
          preview.textContent = file.name;
          preview.style.display = '';
          console.log('[uploadFileAJAX] تم عرض اسم الملف');
        }

        // إظهار رسالة نجاح
        Swal.fire({
          icon: 'success',
          title: 'تم الرفع بنجاح',
          text: `تم رفع ومعالجة الملف بنجاح`,
          timer: 2000,
          showConfirmButton: false
        });

      } else {
        console.error('[uploadFileAJAX] فشل رفع الملف:', data);
        Swal.fire({
          icon: 'error',
          title: 'خطأ في الرفع',
          text: data.message || 'فشل رفع الملف'
        });
        resetFields();
      }
    })
    .catch(err => {
      loaderModal?.hide();
      console.error('[uploadFileAJAX] خطأ في الشبكة:', err);
      Swal.fire({
        icon: 'error',
        title: 'خطأ في الشبكة',
        text: 'حدث خطأ أثناء رفع الملف. يرجى المحاولة مرة أخرى.'
      });
      resetFields();
    });

    function resetFields() {
      if (hiddenFileId) hiddenFileId.value = '';
      if (hiddenTempName) hiddenTempName.value = '';
      if (preview) preview.style.display = 'none';
      console.log('[uploadFileAJAX] تم إعادة تعيين الحقول');
    }
  }

  // ...existing code...
});
</script>


