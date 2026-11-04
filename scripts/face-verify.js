#!/usr/bin/env node
/**
 * face-verify.js — فحص خادمي نهائي لصورة شخصية (كشف متعدّد المقاييس + تحقّق هندسي)
 *
 *   node scripts/face-verify.js --in <file> --json
 *
 * المخرجات: سطر JSON واحد على stdout (السجل على stderr)، دائماً ok:true يعني "أُنجِز الفحص"،
 * والقرار في check.pass / check.code.
 */
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { CFG, initFaceApi, verifyFaceInRgb, loadWorkRgb } from './face-lib.js';

const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const FACEAPI_MODELS = process.env.FACEAPI_MODELS || path.join(ROOT, 'public', 'scripts', 'models');

const a = { in: null, json: true, maxWorkSide: CFG.maxWorkSide, budget: CFG.timeBudgetMs };
for (let i = 2; i < process.argv.length; i++) {
  const k = process.argv[i];
  const v = process.argv[i + 1] && !process.argv[i + 1].startsWith('--') ? process.argv[++i] : 'true';
  switch (k) {
    case '--in': a.in = v; break;
    case '--json': a.json = true; break;
    case '--max-side': a.maxWorkSide = parseInt(v, 10) || a.maxWorkSide; break;
    case '--budget': a.budget = parseInt(v, 10) || a.budget; break;
  }
}

const log = (...m) => console.error('[face-verify]', ...m);

(async function main() {
  const t0 = Date.now();
  if (!a.in || !fs.existsSync(a.in)) {
    console.log(JSON.stringify({ ok: false, reason: 'input-missing' }));
    process.exit(2);
  }
  if (!fs.existsSync(FACEAPI_MODELS)) {
    console.log(JSON.stringify({ ok: false, reason: 'models-missing' }));
    process.exit(2);
  }

  try {
    await initFaceApi(FACEAPI_MODELS);
    const src = await loadWorkRgb(a.in, a.maxWorkSide);
    log(`work=${src.w}x${src.h}`);
    const check = await verifyFaceInRgb(src.rgb, src.w, src.h, { timeBudgetMs: a.budget });
    log(`pass=${check.pass} code=${check.code} score=${check.score ?? '-'} rotated=${check.rotated ?? 0}`);
    console.log(JSON.stringify({
      ok: true,
      w: src.w,
      h: src.h,
      check,
      cfg: {
        minFaceAbsWidth: CFG.minFaceAbsWidth,
        minFaceRelWidth: CFG.minFaceRelWidth,
        significantFaceRelWidth: CFG.significantFaceRelWidth,
        frameConfidence: CFG.sweeps.full[0].confidence,
        fallbackConfidence: CFG.sweeps.full[2].confidence,
        maxWorkSide: CFG.maxWorkSide,
      },
      ms: { total: Date.now() - t0 },
    }));
    process.exit(0);
  } catch (e) {
    log('error', e && e.message);
    console.log(JSON.stringify({ ok: false, reason: 'engine-error', detail: String(e && e.message || e).slice(0, 300) }));
    process.exit(3);
  }
})();
