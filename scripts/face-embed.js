#!/usr/bin/env node
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { CFG, initFaceRecognition, extractFaceEmbeddingFile } from './face-lib.js';

const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const FACEAPI_MODELS = process.env.FACEAPI_MODELS || path.join(ROOT, 'public', 'scripts', 'models');
const MODEL_VERSION = process.env.FACE_MATCH_MODEL_VERSION || 'face-api-resnet34-128d-v1';

const args = {
  in: null,
  budget: CFG.timeBudgetMs,
  maxSide: CFG.maxWorkSide,
  minScore: 0.50,
  minFaceWidth: 60,
  dominanceRatio: 1.5,
};

for (let i = 2; i < process.argv.length; i++) {
  const key = process.argv[i];
  const value = process.argv[i + 1] && !process.argv[i + 1].startsWith('--')
    ? process.argv[++i]
    : 'true';

  if (key === '--in') args.in = value;
  if (key === '--budget') args.budget = Math.max(1000, parseInt(value, 10) || CFG.timeBudgetMs);
  if (key === '--max-side') args.maxSide = Math.max(256, parseInt(value, 10) || CFG.maxWorkSide);
  if (key === '--min-score') args.minScore = Math.min(0.99, Math.max(0.1, parseFloat(value) || 0.50));
  if (key === '--min-face-width') args.minFaceWidth = Math.max(40, parseInt(value, 10) || 60);
  if (key === '--dominance-ratio') args.dominanceRatio = Math.max(1.05, parseFloat(value) || 1.5);
}

const emit = (payload, exitCode = 0) => {
  console.log(JSON.stringify(payload));
  process.exit(exitCode);
};

if (!args.in || !fs.existsSync(args.in)) emit({ ok: false, reason: 'input-missing' }, 2);
if (!fs.existsSync(FACEAPI_MODELS)) emit({ ok: false, reason: 'models-missing' }, 2);

try {
  await initFaceRecognition(FACEAPI_MODELS);
  const check = await extractFaceEmbeddingFile(args.in, {
    timeBudgetMs: args.budget,
    maxWorkSide: args.maxSide,
    minDetScore: args.minScore,
    minFaceWidthPx: args.minFaceWidth,
    dominantFaceRatio: args.dominanceRatio,
  });

  emit({
    ok: true,
    model_version: MODEL_VERSION,
    check,
    ms: { total: check.ms },
  });
} catch (error) {
  console.error('[face-embed] engine-error', error && error.name ? error.name : 'Error');
  emit({ ok: false, reason: 'engine-error' }, 3);
}
