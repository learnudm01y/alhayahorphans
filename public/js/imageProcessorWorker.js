// imageProcessorWorker.js
// Web Worker for non-blocking image compression using OffscreenCanvas and browser-image-compression

importScripts('https://cdn.jsdelivr.net/npm/browser-image-compression@2.0.2/dist/browser-image-compression.js');

self.onmessage = async function(e) {
  try {
    const { imageBitmap, options } = e.data;
    // Create OffscreenCanvas from ImageBitmap
    const canvas = new OffscreenCanvas(imageBitmap.width, imageBitmap.height);
    const ctx = canvas.getContext('2d');
    ctx.drawImage(imageBitmap, 0, 0);
    // Release the ImageBitmap memory
    imageBitmap.close && imageBitmap.close();

    // Detect best output format (AVIF > WebP > original)
    let fileType = options.fileType || 'image/jpeg';
    let supportsAVIF = false, supportsWebP = false;
    try {
      supportsAVIF = !!(await canvas.convertToBlob ? await canvas.convertToBlob({ type: 'image/avif' }) : await canvas.toBlob(() => {}, 'image/avif'));
    } catch {}
    try {
      supportsWebP = !!(await canvas.convertToBlob ? await canvas.convertToBlob({ type: 'image/webp' }) : await canvas.toBlob(() => {}, 'image/webp'));
    } catch {}
    if (supportsAVIF) fileType = 'image/avif';
    else if (supportsWebP) fileType = 'image/webp';

    // Convert canvas to Blob
    const blob = await new Promise(resolve => canvas.convertToBlob ? resolve(canvas.convertToBlob({ type: fileType })) : canvas.toBlob(resolve, fileType));
    // Compress using browser-image-compression
    const compressedBlob = await imageCompression(blob, {
      ...options,
      fileType,
      onProgress: percent => self.postMessage({ type: 'progress', percent })
    });
    // Return result
    self.postMessage({ type: 'done', blob: compressedBlob, fileType });
    self.close();
  } catch (err) {
    self.postMessage({ type: 'error', message: err && err.message ? err.message : String(err) });
    self.close();
  }
};
