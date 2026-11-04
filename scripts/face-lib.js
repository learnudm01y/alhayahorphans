/**
 * face-lib.js — محرّك كشف الوجه المشترك (مسح متعدّد المقاييس + تحقّق هندسي للمعالم)
 *
 * يُستخدم من:
 *   scripts/face-verify.js   (فحص خادمي نهائي)
 *   scripts/face-restore.js  (فحص ما قبل/بعد الترميم)
 *
 * العتبات هنا مطابقة لـ FACE_CFG في resources/views/user/generalRegistration/javascript/cropper.blade.php
 * — أي تغيير يجب أن يُنعكس في المكانين.
 *
 * السبب الجذري الذي يعالجه هذا الملف: SsdMobilenetv1 يمرر دائماً toBatchTensor(512, keepAspectRatio:false)،
 * فالصورة الطولية عالية الدقة تُسحق إلى مربع 512 ويصبح الوجه أصغر من عتبة الكشف.
 * الحل: تمريرة الإطار الكامل ثم مربعات (tiles) بأحجام مختلفة بترتيب من المركز إلى الأطراف،
 * يتبعها تحقّق هندسي من المعالم (ميل/زاوية/تعادل/حجم) بدل عدّ النقاط فقط.
 */
import sharp from 'sharp';
import * as faceapi from '@vladmandic/face-api/dist/face-api.node-wasm.js';

export const CFG = {
  minFaceRelWidth: 0.06,
  minFaceAbsWidth: 40,
  significantFaceRelWidth: 0.10,
  maxWorkSide: 1600,
  tileOverlap: 0.2,
  timeBudgetMs: 12000,
  sweeps: {
    full: [
      { kind: 'frame', confidence: 0.50 },
      { kind: 'tiles', scale: 0.72, confidence: 0.50, maxTiles: 4 },
      { kind: 'frame', confidence: 0.35 },
      { kind: 'tiles', scale: 0.50, confidence: 0.42, maxTiles: 6 },
      { kind: 'tiles', scale: 0.34, confidence: 0.35, maxTiles: 8 },
    ],
    coarse: [
      { kind: 'frame', confidence: 0.50 },
      { kind: 'tiles', scale: 0.72, confidence: 0.50, maxTiles: 4 },
    ],
    frameOnly: [
      { kind: 'frame', confidence: 0.50 },
    ],
  },
  rotationPlan: [
    { deg: 0, sweep: 'full' },
    { deg: 90, sweep: 'coarse' },
    { deg: 270, sweep: 'coarse' },
    { deg: 180, sweep: 'frameOnly' },
  ],
  eyeSpanOverBoxWidth: [0.22, 0.78],
  eyeRollMax: 0.25,
  eyeYRange: [0.18, 0.72],
  mouthYawMax: 0.26,
  jawOverEyeSpanMin: 0.90,
};

let modelsLoaded = false;

export async function initFaceApi(modelsDir) {
  if (modelsLoaded) return true;
  try {
    await faceapi.tf.setBackend('wasm');
  } catch {
    await faceapi.tf.setBackend('cpu');
  }
  await faceapi.tf.ready();
  await faceapi.nets.ssdMobilenetv1.loadFromDisk(modelsDir);
  await faceapi.nets.faceLandmark68Net.loadFromDisk(modelsDir);
  modelsLoaded = true;
  return true;
}

/** قراءة أي ملف صورة إلى RGB خام مع EXIF-rotate وتحجيم الحفاظ على النسبة */
export async function loadWorkRgb(file, maxSide = CFG.maxWorkSide) {
  let img = sharp(file, { failOn: 'none' }).rotate();
  const meta = await img.metadata();
  let { width: w, height: h } = meta;

  if (Math.max(w, h) > maxSide) {
    const scale = maxSide / Math.max(w, h);
    w = Math.max(1, Math.round(w * scale));
    h = Math.max(1, Math.round(h * scale));
    img = img.resize(w, h, { fit: 'fill' });
  }

  const { data, info } = await img.ensureAlpha().raw().toBuffer({ resolveWithObject: true });

  let rgb = data;
  if (info.channels === 4) {
    rgb = await sharp(data, { raw: { width: info.width, height: info.height, channels: 4 } })
      .removeAlpha().raw().toBuffer();
  } else if (info.channels === 1) {
    rgb = await sharp(data, { raw: { width: info.width, height: info.height, channels: 1 } })
      .toColourspace('srgb').raw().toBuffer();
  }

  return { rgb, w, h };
}

async function rgbRotated(rgb, w, h, deg) {
  if (!deg) return { rgb, w, h };
  const out = await sharp(Buffer.from(rgb), { raw: { width: w, height: h, channels: 3 } })
    .rotate(deg).raw().toBuffer({ resolveWithObject: true });
  return { rgb: out.data, w: out.info.width, h: out.info.height };
}

async function detectAll(rgb, w, h, minConfidence) {
  const tensor = faceapi.tf.tensor3d(new Uint8Array(rgb), [h, w, 3]);
  try {
    const detections = await faceapi
      .detectAllFaces(tensor, new faceapi.SsdMobilenetv1Options({ minConfidence }))
      .withFaceLandmarks();
    if (!detections || !detections.length) return [];
    return detections.map((d) => ({
      x: d.detection.box.x,
      y: d.detection.box.y,
      width: d.detection.box.width,
      height: d.detection.box.height,
      score: d.detection.score,
      landmarks: d.landmarks.positions.map((p) => ({ x: p.x, y: p.y })),
    }));
  } finally {
    tensor.dispose();
  }
}

async function cropRgb(rgb, w, h, left, top, width, height) {
  return sharp(Buffer.from(rgb), { raw: { width: w, height: h, channels: 3 } })
    .extract({ left, top, width, height })
    .raw().toBuffer();
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
  const xs = tileAxis(w, tileSide, CFG.tileOverlap);
  const ys = tileAxis(h, tileSide, CFG.tileOverlap);
  const cx = w / 2, cy = h / 2;
  const list = [];
  for (const tx of xs) {
    for (const ty of ys) {
      list.push([tx, ty]);
    }
  }
  list.sort((a, b) => Math.hypot(a[0] + tileSide / 2 - cx, a[1] + tileSide / 2 - cy)
    - Math.hypot(b[0] + tileSide / 2 - cx, b[1] + tileSide / 2 - cy));
  return list.slice(0, maxTiles);
}

function iou(a, b) {
  const x1 = Math.max(a.x, b.x), y1 = Math.max(a.y, b.y);
  const x2 = Math.min(a.x + a.width, b.x + b.width);
  const y2 = Math.min(a.y + a.height, b.y + b.height);
  const inter = Math.max(0, x2 - x1) * Math.max(0, y2 - y1);
  const union = a.width * a.height + b.width * b.height - inter;
  return union > 0 ? inter / union : 0;
}

function dedupe(list) {
  const sorted = [...list].sort((a, b) => b.score - a.score);
  const kept = [];
  for (const d of sorted) {
    if (!kept.some((k) => iou(k, d) > 0.35)) kept.push(d);
  }
  return kept;
}

/** تنفيذ مرحلة مسح واحدة؛ يُرجع أول وجه مطابق أو null، ويجمع كل المرشّحين في sink */
async function runSweep(rgb, w, h, sweep, deadline, sink) {
  const shortSide = Math.min(w, h);

  if (sweep.kind === 'frame') {
    if (Date.now() > deadline) return null;
    const found = await detectAll(rgb, w, h, sweep.confidence);
    sink.push(...found);
    if (!found.length) return null;
    return found.sort((a, b) => b.score - a.score)[0];
  }

  const tileSide = Math.max(64, Math.min(shortSide, Math.round(shortSide * sweep.scale)));
  if (tileSide >= Math.max(w, h)) return runSweep(rgb, w, h, { kind: 'frame', confidence: sweep.confidence }, deadline, sink);

  for (const [tx, ty] of centerOutTiles(w, h, tileSide, sweep.maxTiles || 4)) {
    if (Date.now() > deadline) return null;
    const tile = await cropRgb(rgb, w, h, tx, ty, tileSide, tileSide);
    const found = await detectAll(tile, tileSide, tileSide, sweep.confidence);
    for (const d of found) {
      d.x += tx; d.y += ty;
      if (d.landmarks) d.landmarks = d.landmarks.map((p) => ({ x: p.x + tx, y: p.y + ty }));
      sink.push(d);
    }
    if (found.length) return found.sort((a, b) => b.score - a.score)[0];
  }

  return null;
}

/** تحقّق هندسي حقيقي (تباعد/ميل/زاوية/تعادل/حجم) بدل عدّ النقاط فقط */
export function validateGeometry(face, w, h) {
  const lm = face.landmarks;
  if (!lm || lm.length < 68) return { ok: false, code: 'landmarks-incomplete', detail: 'معالم الوجه غير مكتملة' };
  if (!lm.every((p) => p && isFinite(p.x) && isFinite(p.y))) {
    return { ok: false, code: 'landmarks-invalid', detail: 'معالم الوجه غير واضحة' };
  }

  const boxW = Math.max(1, face.width);
  const boxH = Math.max(1, face.height);
  const avg = (a, b) => {
    let x = 0, y = 0;
    for (let i = a; i < b; i++) { x += lm[i].x; y += lm[i].y; }
    return { x: x / (b - a), y: y / (b - a) };
  };
  const leftEye = avg(36, 42), rightEye = avg(42, 48), mouth = avg(48, 68);

  const eyeDist = Math.hypot(rightEye.x - leftEye.x, rightEye.y - leftEye.y);
  if (eyeDist < 2) return { ok: false, code: 'eyes-unresolved', detail: 'العينان غير واضحتين في الصورة' };

  const spanRatio = eyeDist / boxW;
  if (spanRatio < CFG.eyeSpanOverBoxWidth[0] || spanRatio > CFG.eyeSpanOverBoxWidth[1]) {
    return { ok: false, code: 'face-ratio-unusual', detail: 'وضعية الوجه غير طبيعية — استخدم صورة أمامية واضحة' };
  }

  const roll = Math.abs(rightEye.y - leftEye.y) / eyeDist;
  if (roll > CFG.eyeRollMax) {
    return { ok: false, code: 'face-tilted', detail: 'الوجه مائل كثيراً — استخدم صورة مستقيمة' };
  }

  const relEyeY = ((leftEye.y + rightEye.y) / 2 - face.y) / boxH;
  if (relEyeY < CFG.eyeYRange[0] || relEyeY > CFG.eyeYRange[1]) {
    return { ok: false, code: 'face-framing-off', detail: 'الوجه غير مؤطَّر بشكل صحيح داخل الصورة' };
  }

  const yaw = Math.abs(mouth.x - (leftEye.x + rightEye.x) / 2) / boxW;
  if (yaw > CFG.mouthYawMax) {
    return { ok: false, code: 'face-turned-side', detail: 'الوجه غير مواجه للكاميرا — استخدم صورة أمامية' };
  }

  const jawPts = lm.slice(0, 17);
  const jawWidth = Math.max(...jawPts.map((p) => p.x)) - Math.min(...jawPts.map((p) => p.x));
  if (jawWidth / eyeDist < CFG.jawOverEyeSpanMin) {
    return { ok: false, code: 'jaw-unresolved', detail: 'أجزاء من الوجه غير ظاهرة (غطاء أو قصّ) — اكشف الوجه كاملاً' };
  }

  const relWidth = boxW / w;
  if (boxW < CFG.minFaceAbsWidth || relWidth < CFG.minFaceRelWidth) {
    return { ok: false, code: 'face-too-small', detail: 'حجم الوجه صغير جداً بالنسبة للصورة — قرّب الصورة' };
  }

  return {
    ok: true,
    metrics: {
      eyeDist: Math.round(eyeDist),
      spanRatio: +spanRatio.toFixed(3),
      roll: +roll.toFixed(3),
      yaw: +yaw.toFixed(3),
      relWidth: +relWidth.toFixed(3),
      faceWidthPx: Math.round(boxW),
    },
  };
}

/**
 * الفحص الكامل: مسح متعدّد المقاييس على أكثر من اتجاه (للصور المقلوبة/بدون EXIF مخبوز) + تحقّق هندسي.
 * @returns {{pass:boolean, code:string, detail?:string, score?:number, rotated?:number, metrics?:object, ms:number}}
 */
export async function verifyFaceInRgb(rgb, w, h, {
  timeBudgetMs = CFG.timeBudgetMs,
  sweepsKey = 'full',
} = {}) {
  const t0 = Date.now();
  const deadline = t0 + timeBudgetMs;
  let lastCode = 'no-face';
  let lastDetail = 'لم يتم العثور على وجه واضح في الصورة';

  for (const step of CFG.rotationPlan) {
    if (Date.now() > deadline) break;

    const frame = await rgbRotated(rgb, w, h, step.deg);
    const plan = CFG.sweeps[step.sweep] || CFG.sweeps[step.deg === 0 ? sweepsKey : 'coarse'];
    const sink = [];
    let face = null;

    for (const sweep of plan) {
      if (Date.now() > deadline) break;
      face = await runSweep(frame.rgb, frame.w, frame.h, sweep, deadline, sink);
      if (face) break;
    }

    if (!face) {
      lastCode = 'no-face';
      continue;
    }

    const candidates = dedupe(sink);
    const significant = candidates.filter((d) => d.width / frame.w >= CFG.significantFaceRelWidth);
    if (significant.length > 1) {
      return {
        pass: false, code: 'multi-face',
        detail: 'الصورة تحتوي على أكثر من وجه — يرجى إرسال صورة لشخص واحد فقط',
        faces: significant.length, rotated: step.deg, ms: Date.now() - t0,
      };
    }

    const v = validateGeometry(face, frame.w, frame.h);
    if (!v.ok) {
      lastCode = v.code;
      lastDetail = v.detail;
      // اتجاه خاطئ يُظهرFace جانبي/مائل: جرّب الاتجاهات الأخرى قبل الحكم
      if (step.deg === 0 && (v.code === 'face-turned-side' || v.code === 'face-tilted' || v.code === 'face-framing-off')) continue;
      return { pass: false, code: v.code, detail: v.detail, score: +face.score.toFixed(3), rotated: step.deg, ms: Date.now() - t0 };
    }

    return {
      pass: true,
      code: 'ok',
      score: +face.score.toFixed(3),
      rotated: step.deg,
      metrics: v.metrics,
      box: {
        x: Math.round(face.x), y: Math.round(face.y),
        width: Math.round(face.width), height: Math.round(face.height),
      },
      candidates: candidates.length,
      ms: Date.now() - t0,
    };
  }

  return { pass: false, code: lastCode, detail: lastDetail, ms: Date.now() - t0 };
}

export async function verifyFaceFile(file, opts = {}) {
  const src = await loadWorkRgb(file, opts.maxWorkSide);
  const check = await verifyFaceInRgb(src.rgb, src.w, src.h, opts);
  return { ...check, work: { w: src.w, h: src.h } };
}
