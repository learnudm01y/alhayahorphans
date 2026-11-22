#!/usr/bin/env python3
import argparse
import io
import json
import math
import os
from pathlib import Path
import re
import subprocess
import sys
import warnings as python_warnings

try:
    import cv2
    import numpy as np
    from PIL import Image, ImageOps, UnidentifiedImageError
except ImportError:
    DEPENDENCIES_AVAILABLE = False
else:
    DEPENDENCIES_AVAILABLE = True
    cv2.setNumThreads(1)
    cv2.ocl.setUseOpenCL(False)

MAX_JPEG_BYTES = 5_000_000
FORMATS = {"JPEG", "PNG", "WEBP", "TIFF", "BMP"}


class DocumentError(Exception):
    pass


class JsonParser(argparse.ArgumentParser):
    def error(self, message):
        raise DocumentError("invalid_arguments")


def bounded_size(width, height, max_side):
    scale = min(1.0, max_side / max(width, height))
    return max(1, round(width * scale)), max(1, round(height * scale))


def smaller(image, max_side):
    height, width = image.shape[:2]
    size = bounded_size(width, height, max_side)
    return cv2.resize(image, size, interpolation=cv2.INTER_AREA) if size != (width, height) else image


def trim_borders(rgb):
    """Crop phone-screenshot letterboxing down to the content band."""
    gray = cv2.cvtColor(rgb, cv2.COLOR_RGB2GRAY)
    height, width = gray.shape

    def content_span(profile):
        gap = max(4, round(len(profile) * 0.01))
        start = end = best_start = best_end = None
        for index, content in enumerate(profile):
            if content:
                if start is None or index - end > gap:
                    start = index
                end = index
                if best_end is None or end - start > best_end - best_start:
                    best_start, best_end = start, end
        return (0, len(profile) - 1) if best_end is None else (best_start, best_end)

    bright = gray >= 45
    top, bottom = content_span(bright.mean(axis=1) >= 0.5)
    left, right = content_span(bright[top:bottom + 1].mean(axis=0) >= 0.5)
    if top == 0 and bottom == height - 1 and left == 0 and right == width - 1:
        return rgb
    if (bottom - top + 1 < max(240, height * 0.2)
            or right - left + 1 < max(240, width * 0.2)):
        return rgb
    if (top <= height * 0.02 and height - 1 - bottom <= height * 0.02
            and left <= width * 0.02 and width - 1 - right <= width * 0.02):
        return rgb
    return rgb[top:bottom + 1, left:right + 1]


def load_image(path, max_side, max_pixels):
    try:
        if path.stat().st_size > 128_000_000:
            raise DocumentError("input_too_large")
        with python_warnings.catch_warnings():
            python_warnings.simplefilter("error", Image.DecompressionBombWarning)
            with Image.open(path) as source:
                width, height = source.size
                if width < 1 or height < 1 or width * height > max_pixels:
                    raise DocumentError("pixel_limit")
                if source.format not in FORMATS:
                    raise DocumentError("unsupported_format")
                # Do not enumerate every directory in a potentially long multipage TIFF.
                try:
                    source.seek(1)
                except EOFError:
                    source.seek(0)
                else:
                    raise DocumentError("multiframe_image")
                orientation = source.getexif().get(274, 1)
                source.load()
                image = ImageOps.exif_transpose(source)
                original_size = image.size
                image.thumbnail((max_side, max_side), Image.Resampling.LANCZOS)
                operations = ["exif_transpose"] if orientation in range(2, 9) else []
                if image.mode in {"RGBA", "LA"} or "transparency" in image.info:
                    rgba = image.convert("RGBA")
                    if rgba.getchannel("A").getextrema()[0] < 255:
                        operations.append("alpha_to_white")
                    background = Image.new("RGBA", rgba.size, (255, 255, 255, 255))
                    image = Image.alpha_composite(background, rgba).convert("RGB")
                else:
                    image = image.convert("RGB")
                return np.array(image), original_size, operations
    except (Image.DecompressionBombError, Image.DecompressionBombWarning):
        raise DocumentError("pixel_limit") from None
    except (UnidentifiedImageError, OSError, SyntaxError, ValueError):
        raise DocumentError("invalid_image") from None


def text_evidence(rgb):
    small = smaller(rgb, 1200)
    gray = cv2.cvtColor(small, cv2.COLOR_RGB2GRAY)
    height, width = gray.shape
    block = max(15, int(min(height, width) * 0.035) | 1)
    ink = cv2.adaptiveThreshold(gray, 255, cv2.ADAPTIVE_THRESH_GAUSSIAN_C,
                                cv2.THRESH_BINARY_INV, block, 9)
    _, _, stats, centers = cv2.connectedComponentsWithStats(ink, connectivity=8)
    stats, centers = stats[1:], centers[1:]
    widths, heights, areas = stats[:, 2], stats[:, 3], stats[:, 4]
    valid = ((widths >= 2) & (widths <= width * 0.065)
             & (heights >= 3) & (heights <= height * 0.045)
             & (areas >= 5) & (areas <= width * height * 0.0015)
             & (widths >= heights * 0.12) & (widths <= heights * 9)
             & (areas >= widths * heights * 0.09))
    stats, centers = stats[valid], centers[valid]
    contrast = float(np.percentile(gray, 95) - np.percentile(gray, 2))
    sharpness = float(cv2.Laplacian(gray, cv2.CV_32F).var()) / max(contrast, 24) ** 2
    coverage = float(np.count_nonzero(ink)) / ink.size
    reason = None
    if contrast < 15 or coverage < 0.003:
        reason = "blank_or_low_contrast"
    elif sharpness < 0.0006:
        reason = "severely_blurred"
    elif not 24 <= len(centers) <= 2500 or coverage > 0.24:
        reason = "insufficient_text_evidence"
    return {"gray": gray, "ink": ink, "stats": stats, "centers": centers,
            "sharpness": sharpness, "reason": reason}


def paper_pixels(rgb):
    small = smaller(rgb, 800)
    gray = cv2.cvtColor(small, cv2.COLOR_RGB2GRAY)
    hsv = cv2.cvtColor(small, cv2.COLOR_RGB2HSV)
    bright = float(np.percentile(gray, 95))
    return (hsv[:, :, 1] < 42) & (gray > max(90.0, bright * 0.6))


def paper_like(rgb, minimum=0.76):
    small = smaller(rgb, 800)
    gray = cv2.cvtColor(small, cv2.COLOR_RGB2GRAY)
    hsv = cv2.cvtColor(small, cv2.COLOR_RGB2HSV)
    # Judge brightness against the page's own bright end so dim scans still
    # qualify; fixed floors only keep near-black images out.
    bright = float(np.percentile(gray, 95))
    neutral = paper_pixels(rgb)
    colored = (hsv[:, :, 1] > 65) & (gray > 65)
    return (float(neutral.mean()) >= minimum and float(colored.mean()) < 0.09
            and float(np.percentile(gray, 70)) > max(105.0, bright * 0.62))


def ordered_quad(points):
    points = np.asarray(points, dtype=np.float32).reshape(4, 2)
    center = points.mean(axis=0)
    points = points[np.argsort(np.arctan2(points[:, 1] - center[1], points[:, 0] - center[0]))]
    return np.roll(points, -int(np.argmin(points.sum(axis=1))), axis=0)


def sheet_boundary(gray, quad):
    center = quad.mean(axis=0)
    distance = max(4, min(gray.shape) * 0.015)
    for index in range(4):
        start, end = quad[index], quad[(index + 1) % 4]
        edge = end - start
        normal = np.array([-edge[1], edge[0]]) / np.linalg.norm(edge)
        if np.dot(normal, center - (start + end) / 2) < 0:
            normal = -normal
        samples = start + np.linspace(0.15, 0.85, 50)[:, None] * edge
        inside = np.rint(samples + normal * distance).astype(int)
        outside = np.rint(samples - normal * distance).astype(int)
        if (np.any(outside < 0) or np.any(outside[:, 0] >= gray.shape[1])
                or np.any(outside[:, 1] >= gray.shape[0])):
            return False
        inner = gray[inside[:, 1], inside[:, 0]].astype(float)
        outer = gray[outside[:, 1], outside[:, 0]].astype(float)
        # A text/table border has paper on BOTH sides, unlike a sheet on a desk.
        if np.median(inner) < 155 or np.median(inner - outer) < 22:
            return False
    return True


def find_sheet(rgb, evidence, require_text_inside=True):
    small = smaller(rgb, 1200)
    gray = evidence["gray"]
    height, width = gray.shape
    edges = cv2.Canny(cv2.GaussianBlur(gray, (3, 3), 0), 40, 120)
    edges = cv2.morphologyEx(edges, cv2.MORPH_CLOSE, np.ones((3, 3), np.uint8))
    contours, _ = cv2.findContours(edges, cv2.RETR_LIST, cv2.CHAIN_APPROX_SIMPLE)
    candidates = []
    for contour in sorted(contours, key=cv2.contourArea, reverse=True)[:24]:
        area = cv2.contourArea(contour)
        if not 0.55 <= area / (width * height) <= 0.94:
            continue
        polygon = cv2.approxPolyDP(contour, 0.018 * cv2.arcLength(contour, True), True)
        if len(polygon) != 4 or not cv2.isContourConvex(polygon):
            continue
        quad = ordered_quad(polygon)
        lengths = np.linalg.norm(np.roll(quad, -1, axis=0) - quad, axis=1)
        if min(lengths) < min(width, height) * 0.35 or max(lengths) / min(lengths) > 2.25:
            continue
        if max(lengths[0], lengths[2]) / min(lengths[0], lengths[2]) > 1.3:
            continue
        if max(lengths[1], lengths[3]) / min(lengths[1], lengths[3]) > 1.3:
            continue
        vectors_a, vectors_b = np.roll(quad, 1, axis=0) - quad, np.roll(quad, -1, axis=0) - quad
        cosines = np.sum(vectors_a * vectors_b, axis=1) / (np.linalg.norm(vectors_a, axis=1)
                                                                       * np.linalg.norm(vectors_b, axis=1))
        if np.max(np.abs(cosines)) > 0.42:
            continue
        if abs(math.degrees(math.atan2(*(quad[1] - quad[0])[::-1]))) > 18:
            continue
        if np.any(np.abs(quad.mean(axis=0) / [width, height] - 0.5) > 0.14):
            continue
        margin = max(4, min(width, height) * 0.008)
        if (np.any(quad < margin) or np.any(quad[:, 0] > width - margin)
                or np.any(quad[:, 1] > height - margin) or not sheet_boundary(gray, quad)):
            continue
        mask = np.zeros(gray.shape, np.uint8)
        cv2.fillConvexPoly(mask, np.rint(quad).astype(np.int32), 255)
        pixels = small[mask != 0]
        chroma = pixels.max(axis=1).astype(int) - pixels.min(axis=1)
        if np.mean((chroma < 38) & (pixels.mean(axis=1) > 145)) < 0.72:
            continue
        if require_text_inside:
            centers = evidence["centers"]
            outside = sum(cv2.pointPolygonTest(quad, tuple(map(float, point)), False) < 0
                          for point in centers)
            if outside > min(2, len(centers) * 0.02):
                continue
        else:
            # A dim page shot inside a sleeve or on a desk: ink-like texture
            # outside the sheet is background. Clipped page content, though,
            # still has paper-like surroundings just beyond the quad.
            center = quad.mean(axis=0)
            border = np.zeros(gray.shape, np.uint8)
            inner = np.zeros(gray.shape, np.uint8)
            cv2.fillConvexPoly(border, np.rint(center + (quad - center) * 1.08).astype(np.int32), 1)
            cv2.fillConvexPoly(inner, np.rint(quad).astype(np.int32), 1)
            ring = (border > 0) & (inner == 0)
            hsv = cv2.cvtColor(small, cv2.COLOR_RGB2HSV)
            p95 = float(np.percentile(gray, 95))
            ring_paper = float(np.mean((hsv[:, :, 1][ring] < 42)
                                       & (gray[ring] > max(90.0, p95 * 0.6))))
            if ring_paper >= 0.5:
                centers = evidence["centers"]
                outside = sum(cv2.pointPolygonTest(quad, tuple(map(float, point)), False) < 0
                              for point in centers)
                if outside > min(2, len(centers) * 0.02):
                    continue
        if not any(np.linalg.norm(quad - other, axis=1).mean() < min(width, height) * 0.015
                   for other in candidates):
            candidates.append(quad)
    if len(candidates) != 1:
        return None, len(candidates) > 1
    scale = np.array([rgb.shape[1] / width, rgb.shape[0] / height], np.float32)
    return candidates[0] * scale, False


def perspective(rgb, quad, max_side):
    center = quad.mean(axis=0)
    expanded = center + (quad - center) * 1.025
    height, width = rgb.shape[:2]
    if (np.any(expanded < 0) or np.any(expanded[:, 0] >= width)
            or np.any(expanded[:, 1] >= height)):
        return None
    lengths = np.linalg.norm(np.roll(expanded, -1, axis=0) - expanded, axis=1)
    out_width, out_height = bounded_size(math.ceil((lengths[0] + lengths[2]) / 2),
                                         math.ceil((lengths[1] + lengths[3]) / 2), max_side)
    destination = np.float32([[0, 0], [out_width - 1, 0],
                             [out_width - 1, out_height - 1], [0, out_height - 1]])
    matrix = cv2.getPerspectiveTransform(expanded.astype(np.float32), destination)
    return cv2.warpPerspective(rgb, matrix, (out_width, out_height), flags=cv2.INTER_CUBIC,
                               borderMode=cv2.BORDER_CONSTANT, borderValue=(255, 255, 255))


def osd_rotation(rgb, binary, timeout):
    gray = cv2.cvtColor(smaller(rgb, 1200), cv2.COLOR_RGB2GRAY)
    encoded, data = cv2.imencode(".png", gray)
    if not encoded:
        return 0, "osd_failed"
    environment = os.environ.copy()
    environment["OMP_THREAD_LIMIT"] = "1"
    try:
        result = subprocess.run([binary, "stdin", "stdout", "--psm", "0", "-l", "osd", "--dpi", "150"],
                                input=data.tobytes(), stdout=subprocess.PIPE, stderr=subprocess.PIPE,
                                timeout=timeout, env=environment, check=False, shell=False)
    except FileNotFoundError:
        return 0, "osd_unavailable"
    except subprocess.TimeoutExpired:
        return 0, "osd_timeout"
    except OSError:
        return 0, "osd_failed"
    if result.returncode:
        warning = "osd_insufficient_text" if b"too few characters" in result.stderr.lower() else "osd_failed"
        return 0, warning
    output = result.stdout.decode("utf-8", errors="replace")
    rotation = re.search(r"(?m)^Rotate:\s*(0|90|180|270)\s*$", output)
    confidence = re.search(r"(?m)^Orientation confidence:\s*(\d+(?:\.\d+)?)\s*$", output)
    if not rotation or not confidence:
        return 0, "osd_invalid"
    score = float(confidence.group(1))
    if not math.isfinite(score):
        return 0, "osd_invalid"
    if score < 15:
        return 0, "osd_low_confidence"
    return int(rotation.group(1)), None


def rotate_clockwise(rgb, degrees):
    return np.rot90(rgb, -(degrees // 90)).copy()


def text_lines(evidence):
    centers, stats = evidence["centers"], evidence["stats"]
    if len(centers) < 24:
        return []
    height, width = evidence["gray"].shape
    median_height = float(np.median(stats[:, 3]))
    joined = cv2.morphologyEx(evidence["ink"], cv2.MORPH_CLOSE,
                              np.ones((1, max(9, round(median_height * 2))), np.uint8))
    contours, _ = cv2.findContours(joined, cv2.RETR_LIST, cv2.CHAIN_APPROX_SIMPLE)
    lines = []
    for contour in contours:
        x, y, w, h = cv2.boundingRect(contour)
        if w < width * 0.22 or h > height * 0.075 or w < h * 5:
            continue
        points = centers[(centers[:, 0] >= x) & (centers[:, 0] < x + w)
                         & (centers[:, 1] >= y) & (centers[:, 1] < y + h)]
        if len(points) < 8 or np.ptp(points[:, 0]) < width * 0.22:
            continue
        slope, intercept = np.polyfit(points[:, 0], points[:, 1], 1)
        residual = np.abs(points[:, 1] - (points[:, 0] * slope + intercept))
        if np.median(residual) > median_height * 0.30 or np.percentile(residual, 90) > median_height * 0.70:
            continue
        lines.append((math.degrees(math.atan(slope)), float(points[:, 1].mean())))
    return lines


def has_text_layout(evidence):
    for turn in (False, True):
        if turn:
            width = evidence["gray"].shape[1]
            centers, stats = evidence["centers"], evidence["stats"]
            rotated_stats = stats[:, [1, 0, 3, 2, 4]].copy()
            rotated_stats[:, 1] = width - stats[:, 0] - stats[:, 2]
            evidence = {"gray": np.rot90(evidence["gray"]).copy(),
                        "ink": np.rot90(evidence["ink"]).copy(), "stats": rotated_stats,
                        "centers": np.column_stack((centers[:, 1], width - 1 - centers[:, 0]))}
        lines = text_lines(evidence)
        if len(lines) >= 3:
            angles = np.array([line[0] for line in lines])
            if (abs(float(np.median(angles))) <= 12
                    and np.mean(np.abs(angles - np.median(angles)) < 1.5) >= 0.8
                    and np.ptp([line[1] for line in lines]) > evidence["gray"].shape[0] * 0.15):
                return True
    return False


def deskew_angle(evidence):
    lines = text_lines(evidence)
    if len(lines) < 4 or np.ptp([line[1] for line in lines]) < evidence["gray"].shape[0] * 0.2:
        return None
    angles = np.array([line[0] for line in lines])
    angle = float(np.median(angles))
    if (not 0.4 <= abs(angle) <= 4.5 or np.median(np.abs(angles - angle)) > 0.25
            or np.mean(np.abs(angles - angle) <= 0.5) < 0.8):
        return None
    return angle


def rotate_expanded(rgb, angle):
    height, width = rgb.shape[:2]
    matrix = cv2.getRotationMatrix2D((width / 2, height / 2), angle, 1)
    cosine, sine = abs(matrix[0, 0]), abs(matrix[0, 1])
    size = (math.ceil(width * cosine + height * sine), math.ceil(height * cosine + width * sine))
    matrix[:, 2] += np.array(size) / 2 - [width / 2, height / 2]
    return cv2.warpAffine(rgb, matrix, size, flags=cv2.INTER_CUBIC,
                          borderMode=cv2.BORDER_CONSTANT, borderValue=(255, 255, 255))


def paper_knee(values, soft, hard, top):
    values = values.astype(np.float32)
    result = values.copy()
    high = values > soft
    result[high] = soft + (values[high] - soft) * (top - soft) / (hard - soft)
    return np.clip(result, 0, 255)


def boost_vibrance(rgb, strength=0.45):
    hsv = cv2.cvtColor(np.clip(rgb, 0, 255).astype(np.uint8), cv2.COLOR_RGB2HSV).astype(np.float32)
    saturation = hsv[:, :, 1]
    weight = (np.clip((saturation - 20) / 30, 0, 1)
              * np.clip((180 - saturation) / 55, 0, 1))
    if float(np.mean(weight)) <= 0.0005:
        return rgb, False
    hsv[:, :, 1] = np.clip(saturation * (1 + strength * weight), 0, 255)
    return cv2.cvtColor(hsv.astype(np.uint8), cv2.COLOR_HSV2RGB).astype(np.float32), True


def level_paper(rgb):
    work = rgb.astype(np.float32)
    small = smaller(rgb, 800)
    gray = cv2.cvtColor(small, cv2.COLOR_RGB2GRAY)
    height, width = rgb.shape[:2]
    kernel = max(15, round(min(gray.shape) * 0.06) | 1)
    # Estimate paper brightness on a replicated border so the image edge is not
    # mistaken for shading, and do it per channel so the paper evens out.
    padded = cv2.copyMakeBorder(small, kernel, kernel, kernel, kernel, cv2.BORDER_REPLICATE)
    background = cv2.morphologyEx(padded, cv2.MORPH_CLOSE,
                                 cv2.getStructuringElement(cv2.MORPH_ELLIPSE, (kernel, kernel)))
    background = cv2.GaussianBlur(background.astype(np.float32), (0, 0), min(gray.shape) * 0.025)
    background = background[kernel:kernel + small.shape[0], kernel:kernel + small.shape[1]]
    # Judge the paper itself, not a dark desk or letterbox sharing the frame.
    mask = paper_pixels(rgb)
    values = background[mask] if float(mask.mean()) >= 0.05 else background
    low, high = map(float, np.percentile(values, [10, 90]))
    # Shaded or under-exposed paper (dim photos on a dark desk included) is
    # worth levelling; a near-black frame must not sneak in.
    if not ((high - low >= 18 or high < 200) and low >= max(60.0, high * 0.5) and high >= 120):
        return rgb, False
    gain = np.clip(254 / np.maximum(background, 1), 1, 2.2)
    gain = cv2.resize(gain, (width, height), interpolation=cv2.INTER_LINEAR)
    saturation = cv2.cvtColor(rgb, cv2.COLOR_RGB2HSV)[:, :, 1].astype(np.float32) / 255
    # Only clearly coloured ink resists levelling; a faint bluish cast on
    # dim paper is still paper.
    gain = 1 + (gain - 1) * np.clip(1 - np.maximum(saturation - 0.12, 0) / 0.24, 0, 1)[:, :, None]
    gain = np.minimum(gain, np.maximum(1, 252 / np.maximum(work, 1)))
    candidate = work * gain
    if np.mean(candidate - work) <= 0.8:
        return rgb, False
    return np.clip(candidate, 0, 255).astype(np.uint8), True


def photometric(rgb, evidence, rectified=False):
    leveled, normalized = level_paper(rgb)
    raw_paper = paper_like(rgb, 0.72)
    paper = raw_paper or (rectified and normalized and paper_like(leveled, 0.72))
    scene = paper or float(paper_pixels(leveled if normalized else rgb).mean()) >= 0.08
    if not scene:
        return rgb, []
    operations = []
    work = leveled.astype(np.float32) if paper and normalized else rgb.astype(np.float32)
    if paper and normalized:
        operations.append("illumination_normalization")
    elif not paper:
        # Level only the exposure of a multi-document scene. Local paper
        # levelling here would bleach cloth, skin and coloured card sleeves.
        mask = paper_pixels(rgb)
        gray = cv2.cvtColor(rgb, cv2.COLOR_RGB2GRAY)
        if float(mask.mean()) >= 0.03:
            paper_level = float(np.percentile(gray[mask], 75))
            gain = float(np.clip(245 / max(paper_level, 1), 1, 1.65))
            if gain > 1.025:
                work = np.clip(work * gain, 0, 255)
                operations.append("scene_exposure")
    small = smaller(rgb, 800)
    gray = cv2.cvtColor(small, cv2.COLOR_RGB2GRAY)
    neutral = (small.max(axis=2).astype(int) - small.min(axis=2) < 28) & (gray > 165)
    needs_paper_tone = normalized or float(np.percentile(gray, 97)) < 235
    if paper and needs_paper_tone and neutral.mean() > 0.5:
        channels = np.median(small[neutral], axis=0)
        if 4 <= np.ptp(channels) <= 18:
            gains = np.clip(channels.mean() / channels, 0.98, 1.02).astype(np.float32)
            strength = np.clip(1 - np.ptp(work, axis=2) / 32, 0, 1)
            work *= 1 + (gains - 1)[None, None, :] * strength[:, :, None]
            operations.append("white_balance")
    if paper and needs_paper_tone:
        before = work.copy()
        clipped = np.clip(work, 0, 255).astype(np.uint8)
        luminance, channel_a, channel_b = cv2.split(cv2.cvtColor(clipped, cv2.COLOR_RGB2LAB))
        saturation = cv2.cvtColor(clipped, cv2.COLOR_RGB2HSV)[:, :, 1].astype(np.float32)
        colored = np.clip((saturation - 35) / 40, 0, 1)
        # Bilateral filtering removes fine paper/JPEG noise without washing
        # across ink edges. The local paper anchor then supplies the white point.
        filtered = cv2.bilateralFilter(luminance, 7, 22, 7).astype(np.float32)
        anchor = cv2.morphologyEx(filtered, cv2.MORPH_CLOSE,
                                 cv2.getStructuringElement(cv2.MORPH_ELLIPSE, (41, 41)))
        anchor = cv2.GaussianBlur(anchor, (0, 0), 6)
        deficit = np.maximum(anchor - filtered - 9, 0)
        fine_anchor = cv2.morphologyEx(filtered, cv2.MORPH_CLOSE,
                                      cv2.getStructuringElement(cv2.MORPH_ELLIPSE, (15, 15)))
        fine_deficit = np.maximum(fine_anchor - filtered - 6, 0)
        fine_ink = np.clip((fine_deficit - 8) / 24, 0, 1)
        cleaned_luminance = 255 - deficit * (1.45 + 1.35 * fine_ink)
        # A local bright spot inside a photograph is not paper. Only use the
        # scanner-style curve where the surrounding anchor is genuinely light.
        regional_light = cv2.GaussianBlur(filtered, (0, 0), 18)
        paper_confidence = (np.clip((anchor - 175) / 55, 0, 1)
                            * np.clip((regional_light - 145) / 60, 0, 1))
        neutral_luminance = (paper_confidence * cleaned_luminance
                             + (1 - paper_confidence) * luminance)
        luminance = ((1 - colored) * neutral_luminance
                     + colored * paper_knee(luminance, 205, 248, 252))
        paper_chroma = (paper_confidence * np.clip((luminance - 190) / 45, 0, 1)
                        * (1 - colored) * 0.85)
        channel_a = 128 + (channel_a.astype(np.float32) - 128) * (1 - paper_chroma)
        channel_b = 128 + (channel_b.astype(np.float32) - 128) * (1 - paper_chroma)
        whitened = cv2.cvtColor(cv2.merge([np.clip(luminance, 0, 255).astype(np.uint8),
                                           np.clip(channel_a, 0, 255).astype(np.uint8),
                                           np.clip(channel_b, 0, 255).astype(np.uint8)]),
                                cv2.COLOR_LAB2RGB).astype(np.float32)
        whitened, vibrance = boost_vibrance(whitened)
        if np.mean(np.abs(whitened - before)) > 1.0:
            work = whitened
            operations.append("paper_whitening")
            if vibrance:
                operations.append("color_vibrance")
    elif not paper:
        work, vibrance = boost_vibrance(work)
        if vibrance:
            operations.append("color_vibrance")
    residual = work - cv2.GaussianBlur(work, (3, 3), 0.6)
    background_mask = cv2.cvtColor(work, cv2.COLOR_RGB2GRAY) > 210
    noise = float(np.median(np.abs(residual[background_mask]))) if np.any(background_mask) else 0
    if 1.2 < noise < 5:
        quiet = (np.max(np.abs(residual), axis=2) < 7) & background_mask
        work[quiet] -= residual[quiet] * 0.12
        operations.append("mild_denoise")
    median_height = float(np.median(evidence["stats"][:, 3])) if len(evidence["stats"]) else 0
    paper_sharpen = 0.0006 <= evidence["sharpness"] < 0.02 and median_height >= 8
    if paper_sharpen:
        detail = work - cv2.GaussianBlur(work, (0, 0), 0.9)
        edges = np.clip((np.max(np.abs(detail), axis=2) - 0.6) / 2.4, 0, 1)
        adjustment = np.clip(detail * (0.3 * edges[:, :, None]), -5, 5)
        if np.mean(np.abs(adjustment)) >= 0.08:
            work += adjustment
            operations.append("mild_sharpen")
    elif scene and not raw_paper and evidence["sharpness"] < 0.06:
        luminance = cv2.cvtColor(work, cv2.COLOR_RGB2GRAY)
        detail = luminance - cv2.GaussianBlur(luminance, (0, 0), 1.1)
        local = cv2.GaussianBlur(luminance, (0, 0), 8)
        gain = np.clip((local - 55) / 80, 0, 1)[:, :, None] * 0.75
        adjustment = np.clip(detail[:, :, None] * gain, -14, 14)
        if np.mean(np.abs(adjustment)) >= 0.4:
            work += adjustment
            operations.append("scene_sharpen")
    np.rint(work, out=work)
    np.clip(work, 0, 255, out=work)
    return work.astype(np.uint8), operations


def encode_jpeg(rgb, max_side):
    bounded = smaller(rgb, max_side)
    image = Image.fromarray(bounded)
    resized = bounded.shape != rgb.shape
    for _ in range(5):
        for quality in (94, 90, 86, 82):
            buffer = io.BytesIO()
            image.save(buffer, format="JPEG", quality=quality, subsampling=0, optimize=False)
            data = buffer.getvalue()
            if len(data) <= MAX_JPEG_BYTES:
                return data, image.size, resized, quality
        scale = min(0.9, max(0.65, math.sqrt(MAX_JPEG_BYTES / len(data)) * 0.94))
        size = (max(1, int(image.width * scale)), max(1, int(image.height * scale)))
        if min(size) < 256:
            break
        image = image.resize(size, Image.Resampling.LANCZOS)
        resized = True
    raise DocumentError("output_too_large")


def write_output(path, data):
    created = False
    try:
        with path.open("xb") as stream:
            created = True
            stream.write(data)
            stream.flush()
            os.fsync(stream.fileno())
    except FileExistsError:
        raise DocumentError("output_exists") from None
    except OSError:
        if created:
            try:
                path.unlink()
            except OSError:
                pass
        raise DocumentError("output_write_failed") from None


def enhance(input_path, output_path, tesseract="tesseract", max_side=3200,
            max_pixels=24_000_000, osd_timeout=15):
    if not DEPENDENCIES_AVAILABLE:
        raise DocumentError("missing_dependencies")
    if (not 256 <= max_side <= 3200 or not 1 <= max_pixels <= 24_000_000
            or not math.isfinite(osd_timeout) or not 0 < osd_timeout <= 30
            or not tesseract or "\x00" in tesseract):
        raise DocumentError("invalid_arguments")
    source, output = Path(input_path), Path(output_path)
    if not source.is_absolute() or not output.is_absolute():
        raise DocumentError("absolute_paths_required")
    if source.resolve() == output.resolve() or (output.exists() and source.exists() and source.samefile(output)):
        raise DocumentError("same_path")
    if output.exists() or output.is_symlink():
        raise DocumentError("output_exists")
    if not source.is_file():
        raise DocumentError("input_unreadable")
    if not output.parent.is_dir():
        raise DocumentError("output_directory_missing")
    rgb, original_size, operations = load_image(source, max_side, max_pixels)
    warnings = []

    def unchanged(reason):
        return {"status": "unchanged", "reason": reason, "operations": [], "warnings": warnings,
                "width": original_size[0], "height": original_size[1]}

    trimmed = trim_borders(rgb)
    if trimmed is not rgb:
        rgb = trimmed
        operations.append("letterbox_trim")
    if min(rgb.shape[:2]) < 240:
        return unchanged("too_small")
    evidence = text_evidence(rgb)
    if evidence["reason"]:
        return unchanged(evidence["reason"])
    quad, ambiguous = find_sheet(rgb, evidence)
    if quad is None and not ambiguous:
        leveled, _ = level_paper(rgb)
        if leveled is not rgb:
            # Low-light photos hide the sheet edge in shadow; look again on a
            # levelled copy (the same analysis scale as the photometric pass).
            probe = smaller(leveled, 800)
            quad, ambiguous = find_sheet(probe, text_evidence(probe), require_text_inside=False)
            if quad is not None:
                quad = quad * np.array([rgb.shape[1] / probe.shape[1],
                                        rgb.shape[0] / probe.shape[0]], np.float32)
    if ambiguous:
        warnings.append("geometry_ambiguous")
    layout = has_text_layout(evidence)
    rectified = False
    if quad is not None:
        corrected = perspective(rgb, quad, max_side)
        if corrected is not None:
            # Perspective can prevent text-line grouping until the page is rectified.
            rgb = corrected
            rectified = True
            operations.append("perspective")
            layout = has_text_layout(text_evidence(rgb))
        elif not layout:
            warnings.append("geometry_uncertain")
    if layout:
        rotation, warning = osd_rotation(rgb, tesseract, osd_timeout)
        if warning:
            warnings.append(warning)
        if rotation:
            rgb = rotate_clockwise(rgb, rotation)
            operations.append(f"rotate_{rotation}")
        evidence = text_evidence(rgb)
        angle = deskew_angle(evidence)
        if angle is not None:
            rgb = smaller(rotate_expanded(rgb, angle), max_side)
            operations.append("deskew")
            evidence = text_evidence(rgb)
    else:
        # Geometry stays untouched without a confirmed text layout; colour and
        # illumination can still be corrected safely in place.
        warnings.append("layout_uncertain")
    rgb, adjustments = photometric(rgb, evidence, rectified)
    operations.extend(adjustments)
    if not operations:
        if layout:
            return unchanged("no_confident_improvement")
        return unchanged("uncertain_geometry" if quad is not None else "uncertain_text_layout")
    data, size, resized, quality = encode_jpeg(rgb, max_side)
    if resized or max(original_size) > max_side:
        operations.append("resize")
    if quality < 94:
        operations.append("jpeg_quality_reduced")
    write_output(output, data)
    return {"status": "enhanced", "reason": "safe_improvements", "operations": operations,
            "warnings": warnings, "width": size[0], "height": size[1]}


def main(argv=None):
    try:
        parser = JsonParser(add_help=False, allow_abbrev=False)
        parser.add_argument("--input", required=True)
        parser.add_argument("--output", required=True)
        parser.add_argument("--tesseract", default="tesseract")
        parser.add_argument("--max-side", type=int, default=3200)
        parser.add_argument("--max-pixels", type=int, default=24_000_000)
        parser.add_argument("--osd-timeout", type=float, default=15)
        args = parser.parse_args(argv)
        result = enhance(args.input, args.output, args.tesseract, args.max_side, args.max_pixels, args.osd_timeout)
        code = 0
    except DocumentError as error:
        result, code = {"status": "failed", "reason": str(error)}, 1
    except Exception:
        result, code = {"status": "failed", "reason": "processing_error"}, 1
    print(json.dumps(result, separators=(",", ":")))
    return code


if __name__ == "__main__":
    sys.exit(main())
