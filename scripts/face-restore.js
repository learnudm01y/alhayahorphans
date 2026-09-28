#!/usr/bin/env node
/**
 * face-restore.js — ترميم الصورة الشخصية قبل فحص الوجه
 *
 *   sharp (فك/محاذاة/دمج/ضغط)
 *   + Real-ESRGAN x4  (تكبير دقة الوجه، بلاطات 64px)
 *   + RestoreFormer   (ترميم الوجه 512x512)
 *
 * الاستخدام:
 *   node scripts/face-restore.js --in <in.jpg> --out <out.jpg> --json
 *   node scripts/face-restore.js --in <in.jpg> --out <out.jpg> --json --check
 *
 * المخرجات (سطر واحد JSON):
 *   {"ok":true,"out":"...","w":..,"h":..,"ms":{..},"face":{..}}
 *   {"ok":false,"reason":"...","ms":{..}}
 */
import fs from 'node:fs';
import path from 'node:path';
import os from 'node:os';
import { fileURLToPath } from 'node:url';
import sharp from 'sharp';
import * as ort from 'onnxruntime-node';
import * as faceapi from '@vladmandic/face-api/dist/face-api.node-wasm.js';

const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const MODELS_DIR = process.env.FACE_RESTORE_MODELS || path.join(ROOT, 'storage', 'app', 'face_models');
const FACEAPI_MODELS = process.env.FACEAPI_MODELS || path.join(ROOT, 'public', 'scripts', 'models');
const ESRGAN_PATH = path.join(MODELS_DIR, 'real_esrgan_x4.onnx');
const RF_PATH = path.join(MODELS_DIR, 'restoreformer.onnx');

const ORT_OPTS = {
  executionProviders: ['cpu'],
  graphOptimizationLevel: 'basic',
  enableCpuMemArena: false,
  enableMemPattern: false,
  executionMode: 'sequential',
};

// ---------- args ----------
function parseArgs(argv) {
  const a = {
    minConf: 0.1, norm: '01', outRange: 'auto', tile: 64, alignTarget: 128, quality: 92,
    json: false, check: false, debug: false, out: null, in: null,
    noRf: false, rfRetries: 2, minFreeMB: 420, eyeSpan: 0.28, eyeY: 0.42, target: null,
  };
  for (let i = 0; i < argv.length; i++) {
    const k = argv[i];
    if (!k.startsWith('--')) continue;
    const v = argv[i + 1] && !argv[i + 1].startsWith('--') ? argv[++i] : 'true';
    switch (k.replace(/^--/, '')) {
      case 'in': a.in = v; break;
      case 'out': a.out = v; break;
      case 'min-conf': a.minConf = parseFloat(v); break;
      case 'norm': a.norm = v; break;
      case 'out-range': a.outRange = v; break;
      case 'tile': a.tile = parseInt(v, 10); break;
      case 'align': a.alignTarget = parseInt(v, 10); break;
      case 'quality': a.quality = parseInt(v, 10); break;
      case 'json': a.json = true; break;
      case 'check': a.check = true; break;
      case 'debug': a.debug = true; break;
      case 'no-rf': a.noRf = v !== 'false'; break;
      case 'rf-retries': a.rfRetries = Math.max(1, parseInt(v, 10) || 1); break;
      case 'min-free-mb': a.minFreeMB = parseInt(v, 10) || 0; break;
      case 'eye-span': a.eyeSpan = parseFloat(v); break;
      case 'eye-y': a.eyeY = parseFloat(v); break;
      case 'target': {
        const m = /^(\d+)x(\d+)$/i.exec(String(v));
        if (m) a.target = { w: parseInt(m[1], 10), h: parseInt(m[2], 10) };
        break;
      }
    }
  }
  return a;
}
const args = parseArgs(process.argv.slice(2));
const ms = {};
const log = (...m) => console.error('[face-restore]', ...m);
const fail = (reason) => { const o = { ok: false, reason, ms }; console.log(JSON.stringify(o)); process.exit(2); };

function now() { return Number(process.hrtime.bigint() / 1000000n); }

// ---------- sharp helpers ----------
async function loadWorkingImage(file) {
  let img = sharp(file, { failOn: 'none' }).rotate(); // EXIF auto-rotate
  const meta = await img.metadata();
  let { width: w, height: h } = meta;
  const CAP = 1024;
  if (Math.max(w, h) > CAP) {
    const scale = CAP / Math.max(w, h);
    w = Math.round(w * scale); h = Math.round(h * scale);
    img = img.resize(w, h, { fit: 'inside', kernel: 'lanczos3' });
  }
  const { data, info } = await img.raw().toBuffer({ resolveWithObject: true });
  if (info.channels === 4) {
    const rgb = await sharp(data, { raw: { width: info.width, height: info.height, channels: 4 } })
      .removeAlpha().raw().toBuffer();
    return { rgb, w: info.width, h: info.height };
  }
  if (info.channels === 1) {
    const rgb = await sharp(data, { raw: { width: info.width, height: info.height, channels: 1 } })
      .toColourspace('srgb').raw().toBuffer();
    return { rgb, w: info.width, h: info.height };
  }
  return { rgb: data, w: info.width, h: info.height };
}

// ---------- face-api ----------
let faceapiReady = false;
async function initFaceApi() {
  if (faceapiReady) return true;
  try { await faceapi.tf.setBackend('wasm'); } catch { /* fallback */ }
  await faceapi.tf.ready();
  await faceapi.nets.ssdMobilenetv1.loadFromDisk(FACEAPI_MODELS);
  await faceapi.nets.faceLandmark68Net.loadFromDisk(FACEAPI_MODELS);
  faceapiReady = true;
  return true;
}

async function detectFace(rgb, w, h, minConf) {
  await initFaceApi();
  const tensor = faceapi.tf.tensor3d(new Uint8Array(rgb), [h, w, 3]);
  try {
    const detections = await faceapi
      .detectAllFaces(tensor, new faceapi.SsdMobilenetv1Options({ minConfidence: minConf }))
      .withFaceLandmarks();
    if (!detections || !detections.length) return null;
    // نأخذ أكبر وجه عند تعددهم (الصورة الشخصية يجب أن تكون لشخص واحد أصلاً)
    detections.sort((a, b) => b.detection.box.area - a.detection.box.area);
    const d = detections[0];
    const { x, y, width, height } = d.detection.box;
    return {
      box: { x, y, width, height, score: d.detection.score },
      count: detections.length,
      landmarks: d.landmarks.positions.map((p) => ({ x: p.x, y: p.y })),
    };
  } finally {
    tensor.dispose();
  }
}

/** مراكز العينين من معالم 68 (36-41 اليسرى، 42-47 اليمنى) */
function eyeCenters(lm) {
  const avg = (a, b) => {
    let x = 0, y = 0;
    for (let i = a; i < b; i++) { x += lm[i].x; y += lm[i].y; }
    return { x: x / (b - a), y: y / (b - a) };
  };
  return { left: avg(36, 42), right: avg(42, 48) };
}

/**
 * تحويل similarity بمصفوفة خطية فقط (sharp.affine يطبّق: world = M·input)
 * ويحسب نافذة القص بحيث يقع مركز العين اليسرى في هدف ثابت داخل مخرج size×size.
 */
function alignmentTransform(lm, w, h, size, targetEyeY = 0.42, targetEyeSpan = 0.34) {
  const { left, right } = eyeCenters(lm);
  const dx = right.x - left.x, dy = right.y - left.y;
  const dist = Math.hypot(dx, dy);
  if (!dist) return null;
  const angle = Math.atan2(dy, dx);
  const scale = (targetEyeSpan * size) / dist;
  const cos = Math.cos(-angle), sin = Math.sin(-angle);
  const a = scale * cos, b = -scale * sin;
  const c = scale * sin, d = scale * cos;
  const apply = (p) => ({ x: a * p.x + b * p.y, y: c * p.x + d * p.y });

  // أصل المخرج = أقل نقطة في نطاق الصورة بعد التحويل
  const corners = [[0, 0], [w, 0], [0, h], [w, h]].map(([x, y]) => apply({ x, y }));
  const bx0 = Math.min(...corners.map((p) => p.x));
  const by0 = Math.min(...corners.map((p) => p.y));

  const wl = apply(left), wr = apply(right);
  const target = { x: (0.5 - targetEyeSpan / 2) * size, y: targetEyeY * size };
  const crop = {
    left: Math.round(wl.x - bx0 - target.x),
    top: Math.round(wl.y - by0 - target.y),
    width: size, height: size,
  };
  return { m: [[a, b], [c, d]], crop, wl, wr, target, box: { bx0, by0 }, spanPx: Math.hypot(wr.x - wl.x, wr.y - wl.y) };
}

/** تحويل مربع بإحداثيات المصدر إلى فضاء العالم: world = M·p − bboxMin */
function transformBox(box, m, bbox, margins) {
  const [[a, b], [c, d]] = m;
  const x0 = box.x - box.width * margins.side, x1 = box.x + box.width * (1 + margins.side);
  const y0 = box.y - box.height * margins.top, y1 = box.y + box.height * (1 + margins.bottom);
  const xs = [], ys = [];
  for (const [x, y] of [[x0, y0], [x1, y0], [x0, y1], [x1, y1]]) {
    xs.push(a * x + b * y - bbox.bx0);
    ys.push(c * x + d * y - bbox.by0);
  }
  const nx = Math.min(...xs), ny = Math.min(...ys);
  return { x: nx, y: ny, w: Math.max(...xs) - nx, h: Math.max(...ys) - ny };
}

/** قص نافذة size×size من الصورة المحوّلة (حشو محايد خارج الحدود) */
async function extractWindow(worldPng, crop, size) {
  const meta = await sharp(worldPng).metadata();
  const ix0 = Math.max(0, crop.left), iy0 = Math.max(0, crop.top);
  const ix1 = Math.min(meta.width, crop.left + size), iy1 = Math.min(meta.height, crop.top + size);
  if (ix1 - ix0 < 8 || iy1 - iy0 < 8) return null;

  const win = await sharp(worldPng)
    .extract({ left: ix0, top: iy0, width: ix1 - ix0, height: iy1 - iy0 })
    .png().toBuffer();

  const canvas = await sharp({ create: { width: size, height: size, channels: 3, background: { r: 128, g: 128, b: 128 } } })
    .composite([{ input: win, blend: 'over', left: ix0 - crop.left, top: iy0 - crop.top }])
    .removeAlpha()
    .raw().toBuffer();
  if (canvas.length !== size * size * 3) throw new Error(`window-raw-size:${canvas.length}`);
  return canvas;
}

// ---------- Real-ESRGAN (بلاطات 64px) ----------
async function loadEsr() {
  if (!fs.existsSync(ESRGAN_PATH)) return null;
  try { return await ort.InferenceSession.create(ESRGAN_PATH, ORT_OPTS); } catch (e) { log('ESRGAN load failed:', e.message); return null; }
}

async function esrganUpscale(sess, rgb, w, h, tile, scale = 4) {
  const outW = w * scale, outH = h * scale;
  const out = Buffer.alloc(outW * outH * 3);
  const inN = tile * tile * 3;
  const tileOut = tile * scale;
  const outPlane = tileOut * tileOut;
  for (let y = 0; y < h; y += tile) {
    for (let x = 0; x < w; x += tile) {
      const tw = Math.min(tile, w - x), th = Math.min(tile, h - y);
      // نملأ البلاطة الحافية بنسخ من الحافة لتصبح size×size دائمًا (تخطيط NCHW)
      const input = new Float32Array(inN);
      const plane = tile * tile;
      for (let ty = 0; ty < tile; ty++) {
        const sy = ty < th ? ty : th - 1;
        for (let tx = 0; tx < tile; tx++) {
          const sx = tx < tw ? tx : tw - 1;
          const si = ((y + sy) * w + (x + sx)) * 3;
          const pi = ty * tile + tx;
          input[pi] = rgb[si] / 255;
          input[plane + pi] = rgb[si + 1] / 255;
          input[plane * 2 + pi] = rgb[si + 2] / 255;
        }
      }
      const tensor = new ort.Tensor(input, [1, 3, tile, tile]);
      const res = await sess.run({ input: tensor });
      const o = res.output.data;
      const copyW = tw * scale, copyH = th * scale;
      for (let c = 0; c < 3; c++) {
        for (let r = 0; r < copyH; r++) {
          const dst = ((y * scale + r) * outW + x * scale) * 3 + c;
          const src = c * outPlane + r * tileOut;
          for (let cc = 0; cc < copyW; cc++) {
            let v = o[src + cc];
            if (v < 0) v = 0; else if (v > 1) v = 1;
            out[dst + cc * 3] = v * 255;
          }
        }
      }
    }
  }
  return { out, outW, outH };
}

// ---------- RestoreFormer (512x512 ثابت) ----------
async function loadRf() {
  if (!fs.existsSync(RF_PATH)) return null;
  try { return await ort.InferenceSession.create(RF_PATH, ORT_OPTS); } catch (e) { log('RestoreFormer load failed:', e.message); return null; }
}

function toNorm(r, g, b, count, norm) {
  const out = new Float32Array(count * 3);
  for (let i = 0; i < count; i++) {
    if (norm === '11') { out[i] = r[i] / 127.5 - 1; out[count + i] = g[i] / 127.5 - 1; out[2 * count + i] = b[i] / 127.5 - 1; }
    else if (norm === '255') { out[i] = r[i]; out[count + i] = g[i]; out[2 * count + i] = b[i]; }
    else { out[i] = r[i] / 255; out[count + i] = g[i] / 255; out[2 * count + i] = b[i] / 255; }
  }
  return out;
}

async function restoreFace(sess, rgb, w, h, norm, outRange = 'auto') {
  const N = 512, count = N * N;
  const r = new Float32Array(count), g = new Float32Array(count), b = new Float32Array(count);
  for (let y = 0; y < N; y++) {
    const sy = Math.min(h - 1, Math.round((y * h) / N));
    for (let x = 0; x < N; x++) {
      const sx = Math.min(w - 1, Math.round((x * w) / N));
      const si = (sy * w + sx) * 3, di = y * N + x;
      r[di] = rgb[si]; g[di] = rgb[si + 1]; b[di] = rgb[si + 2];
    }
  }
  const input = toNorm(r, g, b, count, norm);
  const tensor = new ort.Tensor(input, [1, 3, N, N]);
  const res = await sess.run({ input: tensor });
  const o = res.output.data;
  const n = Math.min(o.length, count * 3);
  let mn = Infinity, mx = -Infinity;
  for (let i = 0; i < n; i++) { const v = o[i]; if (v < mn) mn = v; if (v > mx) mx = v; }

  // نطاق الإخراج: نعتمد المئينات لا القيم القصوى (رنين نادر قد يتجاوز [0,1]).
  // الموديل المُستخدم يخرج [0,1] (أُثبت بمقارنة سطوع منطقة الوجه مع المصدر)؛
  // نستخدم [-1,1] فقط لو كانت المئينات السفلى ≤ -0.5 (موديل tanh).
  const s = Float32Array.from(o.subarray(0, n)).sort();
  const q = (p) => s[Math.min(s.length - 1, Math.floor(p * s.length))];
  const loQ = q(0.005), hiQ = q(0.995);
  let outMean = 0;
  for (let i = 0; i < n; i++) outMean += o[i];
  outMean /= n;
  let isum = 0, icnt = 0;
  for (let i = 0; i < rgb.length; i += 48) { isum += rgb[i]; icnt++; }
  const inputMean = isum / icnt;

  let lo = 0, hi = 255;
  if (outRange === '01') { lo = 0; hi = 1; }
  else if (outRange === '11') { lo = -1; hi = 1; }
  else if (outRange !== '255' && hiQ <= 1.5 && loQ >= -1.5) { lo = loQ <= -0.5 ? -1 : 0; hi = 1; }
  const meta = { min: mn, max: mx, loQ, hiQ, outMean, inputMean };
  const out = Buffer.alloc(count * 3);
  for (let i = 0; i < count; i++) {
    for (let c = 0; c < 3; c++) {
      let v = o[c * count + i];
      if (hi === 1) v = (v - lo) * (255 / (hi - lo));
      if (v < 0) v = 0; else if (v > 255) v = 255;
      out[i * 3 + c] = v;
    }
  }
  return { rgb: out, meta, range: `${lo}..${hi}` };
}

// ---------- دمج الوجه المرمم داخل إطار الإخراج ----------
async function featheredMask(w, h, inset, feather) {
  const svg = `<svg xmlns="http://www.w3.org/2000/svg" width="${w}" height="${h}">
    <rect x="${inset}" y="${inset}" width="${Math.max(1, w - inset * 2)}" height="${Math.max(1, h - inset * 2)}" rx="${Math.round(Math.min(w, h) * 0.12)}" fill="white"/></svg>`;
  return sharp(Buffer.from(svg)).greyscale().blur(feather).raw().toBuffer();
}

/**
 * الدمج في فضاء العالم: خلفية = الصورة المحوّلة داخل إطار يحتوي الرأس كاملًا،
 * ثم لصق الوجه المرمم فوق نافذة المحاذاة، ثم تحجيم نهائي إلى المقاس المطلوب (مثل 400x600).
 */
async function composeOutput(worldPng, paste, head, restoredRgb, restoredSize, target) {
  if (paste.w < 8 || paste.h < 8) fail('paste-rect-too-small');
  const meta = await sharp(worldPng).metadata();
  const W = meta.width, H = meta.height;

  // محتوى الإطار = اتحاد (نافذة اللصق، مستطيل الرأس) — الرأس كامل دائمًا داخل الإطار
  const x0 = Math.min(paste.x, head.x), y0 = Math.min(paste.y, head.y);
  const x1 = Math.max(paste.x + paste.w, head.x + head.w), y1 = Math.max(paste.y + paste.h, head.y + head.h);
  const contentW = x1 - x0, contentH = y1 - y0;
  const ccx = (x0 + x1) / 2, ccy = (y0 + y1) / 2;

  let outW, outH, fx, fy;
  if (!target) {
    // بلا مقاس مطلوب: الإطار = العالم كاملًا → نُرمّم في مكانه دون أي تغيير للمقاس
    outW = W; outH = H; fx = 0; fy = 0;
  } else {
    const aspect = target.w / target.h;
    let frameH = Math.max(contentH * 1.15, (contentW * 1.15) / aspect);
    let frameW = frameH * aspect;
    if (frameW < contentW) { frameW = contentW; frameH = frameW / aspect; }
    outW = Math.max(1, Math.round(frameW)); outH = Math.max(1, Math.round(frameH));
    fx = Math.round(ccx - outW / 2); fy = Math.round(ccy - outH / 2);
    if (outW <= W) fx = Math.max(0, Math.min(fx, W - outW));
    if (outH <= H) fy = Math.max(0, Math.min(fy, H - outH));
  }

  // خلفية الإطار (حشو محايد لو تجاوز الإطار حدود العالم)
  const ex0 = Math.max(0, fx), ey0 = Math.max(0, fy);
  const ex1 = Math.min(W, fx + outW), ey1 = Math.min(H, fy + outH);
  let base = sharp({ create: { width: outW, height: outH, channels: 3, background: { r: 128, g: 128, b: 128 } } });
  if (ex1 > ex0 && ey1 > ey0) {
    const part = await sharp(worldPng)
      .extract({ left: ex0, top: ey0, width: ex1 - ex0, height: ey1 - ey0 })
      .png().toBuffer();
    base = base.composite([{ input: part, blend: 'over', left: ex0 - fx, top: ey0 - fy }]);
  }
  const frameBuf = await base.removeAlpha().png().toBuffer();

  // رفع بدقة الوجه المرمم ثم اللصق فوق نافذة المحاذاة
  const K = restoredSize / paste.w;
  const dstW = Math.round(outW * K), dstH = Math.round(outH * K);
  const bg = await sharp(frameBuf).resize(dstW, dstH, { kernel: 'lanczos3' }).png().toBuffer();

  const relX = Math.round((paste.x - fx) * K), relY = Math.round((paste.y - fy) * K);
  const dstRw = Math.round(paste.w * K), dstRh = Math.round(paste.h * K);
  const ix0 = Math.max(0, relX), iy0 = Math.max(0, relY);
  const ix1 = Math.min(dstW, relX + dstRw), iy1 = Math.min(dstH, relY + dstRh);

  let out = bg, finalW = dstW, finalH = dstH;
  if (ix1 - ix0 >= 8 && iy1 - iy0 >= 8) {
    const inset = Math.round(dstRw * 0.10);
    const mask = await featheredMask(dstRw, dstRh, inset, Math.max(4, Math.round(Math.min(dstRw, dstRh) * 0.05)));
    const layer = await sharp(restoredRgb, { raw: { width: restoredSize, height: restoredSize, channels: 3 } })
      .resize(dstRw, dstRh, { kernel: 'lanczos3' })
      .joinChannel(mask, { raw: { width: dstRw, height: dstRh, channels: 1 } }).png().toBuffer();
    const sub = await sharp(layer)
      .extract({ left: ix0 - relX, top: iy0 - relY, width: ix1 - ix0, height: iy1 - iy0 })
      .png().toBuffer();
    out = await sharp(bg).composite([{ input: sub, blend: 'over', left: ix0, top: iy0 }]).png().toBuffer();
  }

  if (args.debug) {
    const dbg = path.join(ROOT, 'scratch', 'face_dbg');
    fs.mkdirSync(dbg, { recursive: true });
    fs.writeFileSync(path.join(dbg, 'frame.png'), frameBuf);
    log(`dbg frame ${outW}x${outH} @${fx},${fy} K=${K.toFixed(2)} paste=[${Math.round(paste.x)},${Math.round(paste.y)}] head=${Math.round(head.w)}x${Math.round(head.h)}`);
  }

  // تحجيم نهائي: إلى المقاس المطلوب، وإلا إلى مقاس الصورة الأصلية (نفس النسبة فلا تشويه)
  const destW = target ? target.w : W, destH = target ? target.h : H;
  if (finalW !== destW || finalH !== destH) {
    out = await sharp(out).resize(destW, destH, { fit: 'fill', kernel: 'lanczos3' }).png().toBuffer();
    finalW = destW; finalH = destH;
  }

  return { buffer: out, outW: finalW, outH: finalH, frame: { fx, fy, outW, outH, K } };
}

/**
 * ترميم في مكانه (بلا مقاس مطلوب): المخرج بمقاس الصورة الأصلية تمامًا.
 * القناع يُلفّ الوجه بقناة alpha، ثم يُحوَّل بـ M⁻¹ إلى إحداثيات المصدر وتُلصق
 * الرقاقة فوق الصورة الأصلية — فلا تُمس بكسلات الخلفية والرأس إطلاقًا.
 */
async function composeInPlace(srcPng, paste, restoredRgb, restoredSize, m, cropOffset, bboxMin) {
  const meta = await sharp(srcPng).metadata();
  const W = meta.width, H = meta.height;
  if (paste.w < 8 || paste.h < 8) fail('paste-rect-too-small');

  const [[a, b], [c, d]] = m;
  const det = a * d - b * c;
  if (!det) fail('singular-transform');

  // بكسل الرقاقة u ↔ المصدر: p = M⁻¹·(cropOffset + u·paste.w/restoredSize + bboxMin)
  const k = paste.w / restoredSize;
  const ia = (d * k) / det, ib = (-b * k) / det, ic = (-c * k) / det, id = (a * k) / det;
  const wx = cropOffset.x + bboxMin.bx0, wy = cropOffset.y + bboxMin.by0;
  const tx = (d * wx - b * wy) / det, ty = (-c * wx + a * wy) / det;

  const inset = Math.round(restoredSize * 0.10);
  const mask = await featheredMask(restoredSize, restoredSize, inset, Math.max(4, Math.round(restoredSize * 0.05)));
  const layer = await sharp(restoredRgb, { raw: { width: restoredSize, height: restoredSize, channels: 3 } })
    .joinChannel(mask, { raw: { width: restoredSize, height: restoredSize, channels: 1 } })
    .png().toBuffer();

  const rendered = await sharp(layer)
    .affine([[ia, ib], [ic, id]], { interpolator: 'bicubic', background: { r: 0, g: 0, b: 0, alpha: 0 } })
    .png().toBuffer();
  const rmeta = await sharp(rendered).metadata();

  // sharp.affine يضع أصغر نقطة في المخرج عند (0,0) ⇒ موقعها الحقيقي = min الزوايا + الترجمة
  const corners = [[0, 0], [restoredSize, 0], [0, restoredSize], [restoredSize, restoredSize]]
    .map(([x, y]) => ({ x: ia * x + ib * y + tx, y: ic * x + id * y + ty }));
  let left = Math.round(Math.min(...corners.map((p) => p.x)));
  let top = Math.round(Math.min(...corners.map((p) => p.y)));

  let patch = rendered;
  const cutL = Math.max(0, -left), cutT = Math.max(0, -top);
  const cutR = Math.max(0, left + rmeta.width - W), cutB = Math.max(0, top + rmeta.height - H);
  if (cutL || cutT || cutR || cutB) {
    const cw = rmeta.width - cutL - cutR, ch = rmeta.height - cutT - cutB;
    if (cw < 8 || ch < 8) {
      log('in-place: رقاقة الوجه خارج الصورة — يُعاد المخرج دون لصق');
      return { buffer: srcPng, outW: W, outH: H, frame: { fx: 0, fy: 0, outW: W, outH: H, K: restoredSize / paste.w } };
    }
    patch = await sharp(rendered).extract({ left: cutL, top: cutT, width: cw, height: ch }).png().toBuffer();
    left += cutL; top += cutT;
  }

  const out = await sharp(srcPng).composite([{ input: patch, blend: 'over', left, top }]).png().toBuffer();
  log(`in-place patch ${rmeta.width}x${rmeta.height} @${left},${top} K=${(restoredSize / paste.w).toFixed(2)} => ${W}x${H}`);
  return { buffer: out, outW: W, outH: H, frame: { fx: 0, fy: 0, outW: W, outH: H, K: restoredSize / paste.w } };
}

/**
 * تحقق مطابق لـ checkFaceOnCanvas في المتصفح (cropper.blade.php L69-191):
 * Pass1 كشف ≥0.5 على الصورة كاملة (وجه واحد فقط + عرض≥6% + معالم≥68)
 * Pass2 تكبير منطقة الوجه إلى 300px ثم كشف ≥0.5 للمعالم (مع رجوع لمعالم Pass1)
 */
async function verifyLikeClient(file) {
  const out = { found: false, pass: false, reason: null };
  let img;
  try { img = await loadWorkingImage(file); } catch (e) { out.reason = 'تعذر قراءة الصورة الناتجة'; return out; }

  const p1 = await detectFace(img.rgb, img.w, img.h, 0.50);
  if (!p1) { out.reason = 'لم يتم العثور على وجه واضح في الصورة'; return out; }
  out.found = true;
  out.count = p1.count;
  out.score = Number(p1.box.score.toFixed(3));
  out.faceWidthPct = Number(((p1.box.width / img.w) * 100).toFixed(1));

  if (p1.count > 1) { out.reason = 'الصورة تحتوي على أكثر من وجه'; return out; }
  if (p1.box.width / img.w < 0.06) { out.reason = 'حجم الوجه صغير جداً بالنسبة للصورة'; return out; }

  // Pass 2: قص منطقة مكبّرة حول الوجه وفحص المعالم
  const padding = Math.max(p1.box.width, p1.box.height) * 0.5;
  const sx = Math.max(0, Math.floor(p1.box.x - padding));
  const sy = Math.max(0, Math.floor(p1.box.y - padding));
  const sw = Math.max(1, Math.round(Math.min(img.w - sx, p1.box.width + padding * 2)));
  const sh = Math.max(1, Math.round(Math.min(img.h - sy, p1.box.height + padding * 2)));
  const ZOOM_W = 300, ZOOM_H = Math.max(1, Math.round(ZOOM_W * (sh / sw)));

  let positions = null;
  try {
    const zoomRgb = await sharp(img.rgb, { raw: { width: img.w, height: img.h, channels: 3 } })
      .extract({ left: sx, top: sy, width: sw, height: sh })
      .resize(ZOOM_W, ZOOM_H, { fit: 'fill' })
      .raw().toBuffer();
    const p2 = await detectFace(zoomRgb, ZOOM_W, ZOOM_H, 0.50);
    if (p2 && p2.landmarks && p2.landmarks.length >= 68) positions = p2.landmarks;
  } catch (e) { /* يُعتمد على معالم Pass 1 */ }
  if (!positions) positions = p1.landmarks;

  if (!positions || positions.length < 68) { out.reason = 'معالم الوجه غير مكتملة'; return out; }

  const groups = [[0, 17, 17], [17, 22, 5], [22, 27, 5], [27, 36, 9], [36, 42, 6], [42, 48, 6], [48, 68, 20]];
  const valid = groups.every(([a, b, need]) => {
    const pts = positions.slice(a, b);
    return pts.length === need && pts.every((p) => p && isFinite(p.x) && isFinite(p.y));
  });
  if (!valid) { out.reason = 'معالم الوجه غير واضحة'; return out; }

  out.pass = true;
  return out;
}

// ---------- main ----------
(async function main() {
  const t0 = now();
  if (!args.in || !fs.existsSync(args.in)) fail('input-missing');
  if (!args.out) fail('out-missing');

  let t = now();
  const src = await loadWorkingImage(args.in);
  ms.decode = now() - t;
  log(`decoded ${src.w}x${src.h} in ${ms.decode}ms`);

  t = now();
  let face = await detectFace(src.rgb, src.w, src.h, 0.50);
  ms.detect = now() - t;
  if (face && face.count > 1) fail('multiple-faces');
  if (!face) {
    // صورة يرفضها المتصفح (score<0.5) — نحاول تحديد موقع الوجه بعتبة أقل لترميمه
    t = now();
    face = await detectFace(src.rgb, src.w, src.h, args.minConf);
    ms.detectRetry = now() - t;
  }
  if (!face) fail('no-face-located');
  log(`face score=${face.box.score.toFixed(3)} box=${JSON.stringify(face.box)} count=${face.count} detect=${ms.detect}ms retry=${ms.detectRetry ?? 0}ms`);

  if (!face.landmarks || face.landmarks.length < 48) fail('landmarks-missing');

  // 1) محاذاة العينين: تحويل الصورة كاملة إلى فضاء world واحد (تطابق الخلفية مع الوجه المرمم)
  t = now();
  const A = args.alignTarget;
  const tf = alignmentTransform(face.landmarks, src.w, src.h, A, args.eyeY, args.eyeSpan);
  if (!tf) fail('align-matrix-failed');
  const worldPng = await sharp(src.rgb, { raw: { width: src.w, height: src.h, channels: 3 } })
    .affine(tf.m, { interpolator: 'nohalo', background: { r: 128, g: 128, b: 128 } })
    .png().toBuffer();
  const wmeta = await sharp(worldPng).metadata();
  ms.align = now() - t;
  log(`world ${wmeta.width}x${wmeta.height} eyesSpan=${tf.spanPx.toFixed(1)}px crop=[${tf.crop.left},${tf.crop.top}] in ${ms.align}ms`);

  if (args.debug) {
    const dbgDir = path.join(ROOT, 'scratch', 'face_dbg');
    fs.mkdirSync(dbgDir, { recursive: true });
    fs.writeFileSync(path.join(dbgDir, 'world.png'), worldPng);
  }

  // نافذة الوجه المُحاذية (تُغذّي النماذج)
  const aligned = await extractWindow(worldPng, tf.crop, A);
  if (!aligned) fail('align-failed');

  // مستطيل الرأس (يشمل الشعر) بإحداثيات العالم — لضمان بقاء الرأس كاملًا
  const headRect = transformBox(face.box, tf.m, tf.box, { top: 0.35, bottom: 0.12, side: 0.20 });
  log(`headRect ${Math.round(headRect.w)}x${Math.round(headRect.h)} @${Math.round(headRect.x)},${Math.round(headRect.y)}`);

  // 2) تكبير Real-ESRGAN x4
  let upW = A * 4, upH = A * 4, upRgb = aligned;
  const esr = await loadEsr();
  if (esr) {
    t = now();
    try {
      const res = await esrganUpscale(esr, aligned, A, A, args.tile, 4);
      upRgb = res.out; upW = res.outW; upH = res.outH;
      ms.esrgan = now() - t;
      log(`esrgan ${A}x${A} -> ${upW}x${upH} in ${ms.esrgan}ms`);
    } catch (e) {
      ms.esrgan = now() - t;
      log('esrgan failed, continue without:', e.message.slice(0, 160));
      upRgb = aligned; upW = A; upH = A;
    } finally {
      try { await esr.release?.(); } catch { /* ignore */ }
    }
  } else {
    log('esrgan model missing — skipped');
    upRgb = aligned; upW = A; upH = A;
  }

  // 3) RestoreFormer 512 — مع محاولات، وإلا نرجع لمخرج Real-ESRGAN/المحاذاة
  let restored = null, restorePath = 'none', rfError = null;
  if (args.noRf) {
    log('restoreformer disabled (--no-rf)');
  } else {
    for (let attempt = 1; attempt <= args.rfRetries; attempt++) {
      const free = Math.round(os.freemem() / 1048576);
      if (free < args.minFreeMB) {
        rfError = `low-memory:${free}MB<${args.minFreeMB}MB`;
        log('restoreformer skipped (memory):', rfError);
        break;
      }
      const rf = await loadRf();
      if (!rf) { rfError = 'model-load-failed'; break; }
      t = now();
      try {
        restored = await restoreFace(rf, upRgb, upW, upH, args.norm);
        ms.restore = (ms.restore || 0) + (now() - t);
        restorePath = 'rf';
        log(`restoreformer attempt ${attempt} OK in ${ms.restore}ms (range ${restored.range}, min=${restored.meta.min.toFixed(3)} max=${restored.meta.max.toFixed(3)} inMean=${restored.meta.inputMean.toFixed(1)} outMean=${restored.meta.outMean.toFixed(3)} loQ=${restored.meta.loQ.toFixed(3)} hiQ=${restored.meta.hiQ.toFixed(3)})`);
        break;
      } catch (e) {
        ms.restore = (ms.restore || 0) + (now() - t);
        rfError = String(e.message).replace(/\u001b\[[0-9;]*m/g, '').replace(/\s+/g, ' ').slice(0, 180);
        log(`restoreformer attempt ${attempt} failed in ${ms.restore}ms:`, rfError);
        restored = null;
      } finally {
        try { await rf.release?.(); } catch { /* ignore */ }
      }
      if (!restored && attempt < args.rfRetries) await new Promise((r) => setTimeout(r, 2000));
    }
  }

  if (!restored) {
    // مخرج التكبير/المحاذاة مباشرة (مسار احتياطي)
    restored = { rgb: upRgb, w: upW, h: upH, range: upW === A ? 'align' : 'esrgan', meta: null };
    restorePath = upW === A ? 'align' : 'esrgan';
    log(`fallback restore path: ${restorePath}`);
  } else {
    restored.w = 512; restored.h = 512;
  }
  if (restored.w !== restored.h) fail('non-square-restored');

  // 4) دمج + ضغط
  t = now();
  const paste = { x: tf.crop.left, y: tf.crop.top, w: A, h: A };
  let comp;
  if (args.target) {
    comp = await composeOutput(worldPng, paste, headRect, restored.rgb, restored.w, args.target);
  } else {
    // بلا --target: ترميم في مكانه بمقاس الصورة الأصلية
    const srcPng = await sharp(src.rgb, { raw: { width: src.w, height: src.h, channels: 3 } }).png().toBuffer();
    comp = await composeInPlace(srcPng, paste, restored.rgb, restored.w, tf.m,
      { x: tf.crop.left, y: tf.crop.top }, tf.box);
  }
  const outBuf = await sharp(comp.buffer).jpeg({ quality: args.quality, mozjpeg: false }).toBuffer();
  fs.writeFileSync(args.out, outBuf);
  ms.compose = now() - t;

  if (args.debug) {
    const dbg = path.join(ROOT, 'scratch', 'face_dbg');
    fs.mkdirSync(dbg, { recursive: true });
    fs.writeFileSync(path.join(dbg, 'aligned.png'), await sharp(aligned, { raw: { width: A, height: A, channels: 3 } }).png().toBuffer());
    if (upW !== A) fs.writeFileSync(path.join(dbg, 'esrgan.png'), await sharp(upRgb, { raw: { width: upW, height: upH, channels: 3 } }).png().toBuffer());
    fs.writeFileSync(path.join(dbg, 'restored.png'), await sharp(restored.rgb, { raw: { width: restored.w, height: restored.h, channels: 3 } }).png().toBuffer());
  }

  // 5) فحص اختياري — يحاكي checkFaceOnCanvas بالمتصفح حرفيًا (cropper.blade.php L69-191)
  let check = null;
  if (args.check) check = await verifyLikeClient(args.out);

  ms.total = now() - t0;
  console.log(JSON.stringify({
    ok: true, out: args.out, w: comp.outW, h: comp.outH,
    bytes: outBuf.length, restore: restorePath, rfError,
    face: { score: Number(face.box.score.toFixed(3)) }, ms, check,
    freeMB: Math.round(os.freemem() / 1048576),
  }));
  process.exit(0);
})().catch((e) => {
  console.error('[face-restore] fatal:', e && e.stack ? e.stack : e);
  console.log(JSON.stringify({ ok: false, reason: 'fatal: ' + String(e && e.message ? e.message : e).slice(0, 200), ms }));
  process.exit(3);
});
