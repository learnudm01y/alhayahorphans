import * as faceapi from '@vladmandic/face-api/dist/face-api.node-wasm.js';
import sharp from 'sharp';
import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

async function main() {
    const imagePath = process.argv[2];

    if (!imagePath || !fs.existsSync(imagePath)) {
        console.log(JSON.stringify({ valid: false, reason: 'ملف الصورة غير موجود أو لم يتم تمرير المسار بشكل صحيح' }));
        process.exit(0);
    }

    // تحميل النماذج
    const MODELS_PATH = path.join(__dirname, 'models');
    try {
        await faceapi.tf.ready();
        await faceapi.nets.ssdMobilenetv1.loadFromDisk(MODELS_PATH);
        await faceapi.nets.faceLandmark68Net.loadFromDisk(MODELS_PATH);
    } catch (err) {
        console.error('Error loading models:', err);
        console.log(JSON.stringify({ valid: false, reason: 'فشل تحميل نماذج فحص الوجه على الخادم' }));
        process.exit(0);
    }

    let tensor = null;

    try {
        // قراءة ومعالجة الصورة عبر Sharp
        const { data, info } = await sharp(imagePath)
            .rotate()
            .resize({
                width: 1000,
                height: 1000,
                fit: 'inside',
                withoutEnlargement: true
            })
            .removeAlpha()
            .raw()
            .toBuffer({
                resolveWithObject: true
            });

        tensor = faceapi.tf.tensor3d(
            new Uint8Array(data),
            [info.height, info.width, 3]
        );

        const detections = await faceapi
            .detectAllFaces(
                tensor,
                new faceapi.SsdMobilenetv1Options({
                    minConfidence: 0.65
                })
            )
            .withFaceLandmarks();

        // 1. عدد الوجوه
        if (!detections || detections.length === 0) {
            console.log(JSON.stringify({ valid: false, reason: 'لم يتم العثور على وجه واضح في الصورة' }));
            process.exit(0);
        }

        if (detections.length > 1) {
            console.log(JSON.stringify({ valid: false, reason: 'الصورة تحتوي على أكثر من وجه' }));
            process.exit(0);
        }

        const face = detections[0];

        // 2. نسبة الثقة Confidence
        if (face.detection.score < 0.65) {
            console.log(JSON.stringify({ valid: false, reason: 'درجة وضوح الوجه غير كافية' }));
            process.exit(0);
        }

        // 3. فحص المعالم 68 Landmarks
        const positions = face.landmarks.positions;
        if (!positions || positions.length < 68) {
            console.log(JSON.stringify({ valid: false, reason: 'معالم الوجه غير مكتملة أو غير واضحة' }));
            process.exit(0);
        }

        const isValidGroup = (pts) => {
            return pts.every(p => p && typeof p.x === 'number' && typeof p.y === 'number' && !isNaN(p.x) && isFinite(p.x) && !isNaN(p.y) && isFinite(p.y));
        };

        // 4. الفك (0 - 16)
        const jaw = positions.slice(0, 17);
        if (jaw.length !== 17 || !isValidGroup(jaw)) {
            console.log(JSON.stringify({ valid: false, reason: 'الفك غير واضح أو غير مكتمل' }));
            process.exit(0);
        }

        // 5. الأنف (27 - 35)
        const nose = positions.slice(27, 36);
        if (nose.length !== 9 || !isValidGroup(nose)) {
            console.log(JSON.stringify({ valid: false, reason: 'معالم الأنف غير واضحة أو غير مكتملة' }));
            process.exit(0);
        }

        // 6. العينان (36 - 41) و (42 - 47)
        const leftEye = positions.slice(36, 42);
        const rightEye = positions.slice(42, 48);
        if (leftEye.length !== 6 || rightEye.length !== 6 || !isValidGroup(leftEye) || !isValidGroup(rightEye)) {
            console.log(JSON.stringify({ valid: false, reason: 'معالم العينين غير واضحة أو غير مكتملة' }));
            process.exit(0);
        }

        // 7. الفم (48 - 67)
        const mouth = positions.slice(48, 68);
        if (mouth.length !== 20 || !isValidGroup(mouth)) {
            console.log(JSON.stringify({ valid: false, reason: 'معالم الفم غير واضحة أو غير مكتملة' }));
            process.exit(0);
        }

        // 8. حجم الوجه بالنسبة للصورة
        const boxWidth = face.detection.box.width;
        const relativeWidth = boxWidth / info.width;
        if (relativeWidth < 0.12) {
            console.log(JSON.stringify({ valid: false, reason: 'حجم الوجه صغير جداً بالنسبة للصورة' }));
            process.exit(0);
        }

        // النجاح المطابق لكافة الشروط
        console.log(JSON.stringify({ valid: true }));

    } catch (err) {
        console.error('Processing error:', err);
        console.log(JSON.stringify({ valid: false, reason: 'حدث خطأ أثناء معالجة الصورة وفحص الوجه' }));
    } finally {
        if (tensor) {
            tensor.dispose();
        }
    }
}

main();
