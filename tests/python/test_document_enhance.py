import contextlib
import hashlib
import importlib.util
import io
import json
from pathlib import Path
import subprocess
import sys
import tempfile
import unittest
from unittest import mock

SCRIPT = Path(__file__).resolve().parents[2] / "scripts" / "document-enhance.py"
SPEC = importlib.util.spec_from_file_location("document_enhance", SCRIPT)
doc = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(doc)
AVAILABLE = doc.DEPENDENCIES_AVAILABLE
if AVAILABLE:
    import cv2
    import numpy as np
    from PIL import Image


class ContractTests(unittest.TestCase):
    def test_invalid_arguments_are_one_private_json_object(self):
        output = io.StringIO()
        with contextlib.redirect_stdout(output):
            code = doc.main(["--input", "private-person-path", "--unexpected"])
        self.assertEqual(code, 1)
        self.assertEqual(json.loads(output.getvalue()), {"status": "failed", "reason": "invalid_arguments"})
        self.assertEqual(len(output.getvalue().splitlines()), 1)

    def test_missing_dependencies_have_structured_failure(self):
        output = io.StringIO()
        with mock.patch.object(doc, "DEPENDENCIES_AVAILABLE", False), contextlib.redirect_stdout(output):
            code = doc.main(["--input", str(SCRIPT), "--output", str(SCRIPT.with_suffix(".jpg"))])
        self.assertEqual(code, 1)
        self.assertEqual(json.loads(output.getvalue()), {"status": "failed", "reason": "missing_dependencies"})

    def test_cli_failure_is_json_without_traceback_or_paths(self):
        result = subprocess.run([sys.executable, "-B", str(SCRIPT), "--unknown", "sensitive-name"],
                                capture_output=True, text=True, timeout=15, check=False)
        self.assertNotEqual(result.returncode, 0)
        self.assertEqual(json.loads(result.stdout), {"status": "failed", "reason": "invalid_arguments"})
        self.assertNotIn("sensitive-name", result.stdout + result.stderr)
        self.assertNotIn("Traceback", result.stderr)


@unittest.skipUnless(AVAILABLE, "OpenCV, NumPy and Pillow are required; no dependencies are installed by tests")
class DocumentTests(unittest.TestCase):
    def setUp(self):
        self.directory = tempfile.TemporaryDirectory()
        self.root = Path(self.directory.name).resolve()
        self.source = self.root / "private-original.png"
        self.output = self.root / "derivative.jpg"

    def tearDown(self):
        self.directory.cleanup()

    @staticmethod
    def page():
        image = np.full((1000, 780, 3), 248, np.uint8)
        for y in range(150, 820, 48):
            cv2.putText(image, "REGISTRATION DOCUMENT 0123456789", (65, y),
                        cv2.FONT_HERSHEY_SIMPLEX, 0.70, (32, 32, 32), 1, cv2.LINE_AA)
        cv2.putText(image, "FAINT TEXT 0123456789", (65, 870),
                    cv2.FONT_HERSHEY_SIMPLEX, 0.70, (185, 185, 185), 1, cv2.LINE_AA)
        return image

    @classmethod
    def shadow_page(cls):
        page = cls.page().astype(np.float32)
        gradient = np.linspace(0.72, 1.0, page.shape[1], dtype=np.float32)
        return np.rint(page * gradient[None, :, None]).astype(np.uint8)

    def save(self, rgb, **kwargs):
        Image.fromarray(rgb).save(self.source, **kwargs)
        return hashlib.sha256(self.source.read_bytes()).digest()

    def process(self, rgb, osd=(0, None), **kwargs):
        digest = self.save(rgb)
        with mock.patch.object(doc, "osd_rotation", return_value=osd):
            result = doc.enhance(self.source, self.output, **kwargs)
        self.assertEqual(digest, hashlib.sha256(self.source.read_bytes()).digest())
        self.assertEqual(set(result), {"status", "reason", "operations", "warnings", "width", "height"})
        self.assertNotIn(str(self.root), json.dumps(result))
        self.assertNotIn("private-original", json.dumps(result))
        return result

    def output_array(self):
        with Image.open(self.output) as image:
            self.assertEqual(image.format, "JPEG")
            return np.array(image.convert("RGB"))

    def test_shadow_is_reduced_without_erasing_faint_text(self):
        before = self.shadow_page()
        result = self.process(before, osd=(0, "osd_unavailable"))
        self.assertEqual(result["status"], "enhanced")
        self.assertIn("illumination_normalization", result["operations"])
        self.assertIn("osd_unavailable", result["warnings"])
        after = self.output_array()
        before_range = np.ptp(np.percentile(before[25, :, 0], [10, 90]))
        after_range = np.ptp(np.percentile(after[25, :, 0], [10, 90]))
        self.assertLess(after_range, before_range * 0.85)
        faint = self.page()[845:875, :, 0] < 210
        before_background = np.broadcast_to(before[830, :, 0], faint.shape)
        after_background = np.broadcast_to(after[830, :, 0], faint.shape)
        before_contrast = np.mean(before_background[faint] - before[845:875, :, 0][faint].astype(float))
        after_contrast = np.mean(after_background[faint] - after[845:875, :, 0][faint].astype(float))
        self.assertGreater(before_contrast, 15)
        self.assertGreater(after_contrast, before_contrast * 0.8)
        self.assertLessEqual(self.output.stat().st_size, 5_000_000)

    @classmethod
    def desk_photo(cls, background=130, seed=5):
        canvas = np.full((1250, 1000, 3), background, np.uint8)
        canvas = np.clip(canvas + np.random.default_rng(seed).integers(-7, 8, canvas.shape),
                         0, 255).astype(np.uint8)
        canvas[125:1125, 110:890] = cls.page()
        return np.clip(canvas.astype(np.float32) * 0.52 + np.array([0, 3, 10], np.float32),
                       0, 255).astype(np.uint8)

    def test_low_light_photo_on_a_desk_is_cropped_and_whitened(self):
        photo = self.desk_photo()
        self.assertEqual(doc.find_sheet(photo, doc.text_evidence(photo)), (None, False))
        result = self.process(photo)
        self.assertEqual(result["status"], "enhanced")
        self.assertIn("perspective", result["operations"])
        self.assertIn("illumination_normalization", result["operations"])
        self.assertIn("paper_whitening", result["operations"])
        gray = self.output_array().mean(axis=2)
        self.assertGreaterEqual(float(np.percentile(gray, 90)), 250)
        self.assertAlmostEqual(result["height"] / result["width"], 1000 / 780, delta=0.06)

    def test_dim_scan_without_a_sheet_edge_is_whitened_in_place(self):
        dim = np.clip(self.page().astype(np.float32) * 0.55 + np.array([0, 2, 8], np.float32),
                      0, 255).astype(np.uint8)
        result = self.process(dim)
        self.assertEqual(result["status"], "enhanced")
        self.assertIn("illumination_normalization", result["operations"])
        self.assertIn("paper_whitening", result["operations"])
        self.assertNotIn("perspective", result["operations"])
        after = self.output_array()
        gray = after.mean(axis=2)
        self.assertGreaterEqual(float(np.percentile(gray, 90)), 250)
        paper = after[30:80, 100:650].reshape(-1, 3)
        self.assertLess(float(np.ptp(np.median(paper, axis=0))), 6)

    @classmethod
    def letterboxed_scene(cls):
        rng = np.random.default_rng(7)
        canvas = np.full((1600, 720, 3), 132, np.uint8)
        for y in range(0, 1600, 36):
            canvas[y:y + 3, :] = (158, 148, 176)
        canvas = np.clip(canvas + rng.integers(-6, 7, canvas.shape), 0, 255).astype(np.uint8)
        canvas[:500] = 6
        canvas[1300:] = 6
        card = np.full((260, 320, 3), 232, np.uint8)
        for y in range(30, 240, 30):
            cv2.putText(card, "ID 0123456789", (14, y), cv2.FONT_HERSHEY_SIMPLEX,
                        0.55, (35, 35, 35), 1, cv2.LINE_AA)
        book = np.full((240, 580, 3), 240, np.uint8)
        for y in range(25, 220, 26):
            cv2.putText(book, "VACCINE 4.26194343 28/11/2016", (16, y),
                        cv2.FONT_HERSHEY_SIMPLEX, 0.5, (30, 30, 40), 1, cv2.LINE_AA)
        canvas[540:800, 60:380] = cv2.GaussianBlur(card, (0, 0), 1.1)
        canvas[830:1070, 60:640] = cv2.GaussianBlur(book, (0, 0), 1.1)
        return np.clip(canvas.astype(np.float32) * 0.62 + np.array([2, 4, 10], np.float32),
                       0, 255).astype(np.uint8)

    def test_letterboxed_photo_of_several_documents_is_cropped_and_sharpened(self):
        photo = self.letterboxed_scene()
        result = self.process(photo)
        self.assertEqual(result["status"], "enhanced")
        self.assertIn("letterbox_trim", result["operations"])
        self.assertIn("scene_exposure", result["operations"])
        self.assertNotIn("paper_whitening", result["operations"])
        self.assertIn("scene_sharpen", result["operations"])
        self.assertEqual(result["width"], 720)
        self.assertEqual(result["height"], 800)
        after = self.output_array().astype(np.float32)
        strokes = after[330:570, 60:640].mean(axis=2)
        self.assertLess(float(strokes.min()), 90)
        self.assertGreaterEqual(float(np.percentile(strokes, 95)), 240)
        cloth_region = after[0:200, 640:710]
        cloth = cv2.cvtColor(cloth_region.astype(np.uint8), cv2.COLOR_RGB2HSV)[:, :, 1]
        self.assertGreater(float(cloth.mean()), 10)
        self.assertLess(float(cloth_region.mean()), 230)

    def test_letterbox_trim_leaves_full_frame_content_alone(self):
        page = self.page()
        self.assertIs(doc.trim_borders(page), page)

    def test_tall_scene_exposure_uses_a_mask_sized_like_its_pixels(self):
        scene = cv2.resize(self.letterboxed_scene()[500:1300], (760, 1100))
        result, operations = doc.photometric(scene, doc.text_evidence(scene))
        self.assertEqual(result.shape, scene.shape)
        self.assertIn("scene_exposure", operations)

    def test_saturated_stamp_color_is_not_whitened(self):
        before = self.shadow_page()
        before[30:65, 40:80] = (185, 30, 45)
        result = self.process(before)
        self.assertEqual(result["status"], "enhanced")
        after = self.output_array()
        np.testing.assert_allclose(after[38:57, 48:72].mean(axis=(0, 1)), (185, 30, 45), atol=4)

    def test_faint_ink_stays_dark_and_pale_seals_gain_saturation(self):
        before = self.shadow_page()
        cv2.putText(before, "FAINT INK 0123456789", (65, 880), cv2.FONT_HERSHEY_SIMPLEX,
                    0.9, (150, 150, 150), 2, cv2.LINE_AA)
        before[30:65, 40:80] = (150, 170, 205)
        result = self.process(before)
        self.assertEqual(result["status"], "enhanced")
        self.assertIn("paper_whitening", result["operations"])
        self.assertIn("color_vibrance", result["operations"])
        after = self.output_array().astype(np.float32)
        band_before = before[845:895, 60:700].astype(np.float32)
        core = np.all(band_before == 150, axis=2)
        self.assertGreater(int(core.sum()), 500)
        self.assertLess(float(after[845:895, 60:700][core].mean()), 140)
        self.assertGreaterEqual(float(np.percentile(after.mean(axis=2), 90)), 250)
        seal_before = cv2.cvtColor(before[30:65, 40:80], cv2.COLOR_RGB2HSV)
        seal_after = cv2.cvtColor(after[30:65, 40:80].astype(np.uint8), cv2.COLOR_RGB2HSV)
        self.assertGreater(float(seal_after[:, :, 1].mean()), float(seal_before[:, :, 1].mean()) * 1.2)
        self.assertLess(abs(float(seal_after[:, :, 0].mean()) - float(seal_before[:, :, 0].mean())), 3)

    def test_neutral_photo_regions_are_not_mapped_to_paper_white(self):
        before = self.shadow_page()
        before[100:260, 450:700] = (35, 40, 45)
        for x in range(465, 690, 35):
            cv2.circle(before, (x, 180), 10, (95, 100, 105), -1)
        result = self.process(before)
        self.assertEqual(result["status"], "enhanced")
        after = self.output_array()
        region = cv2.cvtColor(after[100:260, 450:700], cv2.COLOR_RGB2GRAY)
        self.assertLess(float(np.percentile(region, 95)), 220)

    def test_blank_tiny_low_contrast_and_blurred_inputs_remain_untouched(self):
        cases = [np.full((1000, 780, 3), 248, np.uint8),
                 np.full((150, 120, 3), 220, np.uint8),
                 np.clip(self.page().astype(float) * 0.015 + 245, 0, 255).astype(np.uint8),
                 cv2.GaussianBlur(self.page(), (0, 0), 10)]
        for image in cases:
            with self.subTest(shape=image.shape, spread=int(np.ptp(image))):
                digest = self.save(image)
                with mock.patch.object(doc, "osd_rotation") as osd:
                    result = doc.enhance(self.source, self.output)
                self.assertEqual(result["status"], "unchanged")
                self.assertEqual(result["operations"], [])
                self.assertFalse(self.output.exists())
                self.assertEqual(digest, hashlib.sha256(self.source.read_bytes()).digest())
                osd.assert_not_called()

    def test_colored_identity_card_and_photo_are_not_whitened(self):
        for background in ((150, 205, 240), (235, 185, 145)):
            with self.subTest(background=background):
                page = self.page()
                mask = page[:, :, 0] > 240
                page[mask] = background
                page[40:120, 560:700] = (80, 100, 160)
                result = self.process(page)
                self.assertEqual(result["status"], "unchanged")
                self.assertFalse(self.output.exists())

    def test_colored_document_can_rotate_without_whitening(self):
        page = self.page()
        page[page[:, :, 0] > 240] = (150, 205, 240)
        result = self.process(page, osd=(180, None))
        self.assertEqual(result["status"], "enhanced")
        self.assertIn("rotate_180", result["operations"])
        self.assertNotIn("illumination_normalization", result["operations"])
        np.testing.assert_allclose(self.output_array()[-25, -25], (150, 205, 240), atol=4)

    def test_unstructured_grayscale_marks_are_not_treated_as_document_text(self):
        page = np.full((1000, 780, 3), 242, np.uint8)
        random = np.random.default_rng(12)
        for x, y, radius in random.integers([20, 20, 2], [760, 980, 10], size=(180, 3)):
            cv2.circle(page, (int(x), int(y)), int(radius), (70, 70, 70), -1)
        result = self.process(page)
        self.assertEqual(result["status"], "unchanged")
        self.assertFalse(self.output.exists())

    def test_clean_scan_is_not_reencoded_without_improvement(self):
        result = self.process(self.page())
        self.assertEqual(result["status"], "unchanged")
        self.assertEqual(result["reason"], "no_confident_improvement")
        self.assertFalse(self.output.exists())

    def test_detected_sheet_is_rectified_and_corner_content_survives(self):
        page = self.page()
        for x, y in ((22, 22), (735, 22), (22, 955), (735, 955)):
            page[y:y + 15, x:x + 15] = (190, 20, 35)
        corners = np.float32([[95, 105], [905, 145], [860, 1145], [135, 1125]])
        source_corners = np.float32([[0, 0], [779, 0], [779, 999], [0, 999]])
        matrix = cv2.getPerspectiveTransform(source_corners, corners)
        photo = cv2.warpPerspective(page, matrix, (1000, 1250), borderValue=(75, 75, 75))
        result = self.process(photo)
        self.assertEqual(result["status"], "enhanced")
        self.assertIn("perspective", result["operations"])
        after = self.output_array()
        self.assertLess(float((after.mean(axis=2) < 100).mean()),
                        float((photo.mean(axis=2) < 100).mean()) * 0.45)
        red = (after[:, :, 0] > 130) & (after[:, :, 1] < 70) & (after[:, :, 2] < 85)
        h, w = red.shape
        for section in (red[:h // 2, :w // 2], red[:h // 2, w // 2:],
                        red[h // 2:, :w // 2], red[h // 2:, w // 2:]):
            self.assertGreater(int(section.sum()), 50)
        self.assertNotAlmostEqual(result["height"] / result["width"], 2 ** 0.5, places=2)

    def test_internal_table_border_is_not_mistaken_for_sheet(self):
        page = self.page()
        cv2.rectangle(page, (42, 50), (742, 945), (30, 30, 30), 3)
        quad, ambiguous = doc.find_sheet(page, doc.text_evidence(page))
        self.assertIsNone(quad)
        self.assertFalse(ambiguous)
        result = self.process(page)
        self.assertNotIn("perspective", result["operations"])

    def test_small_sheet_and_uncertain_boundary_are_not_cropped(self):
        page = self.page()
        for background, size in ((75, (490, 630)), (237, (780, 1000))):
            canvas = np.full((1250, 1000, 3), background, np.uint8)
            resized = cv2.resize(page, size)
            x, y = (1000 - size[0]) // 2, (1250 - size[1]) // 2
            canvas[y:y + size[1], x:x + size[0]] = resized
            quad, _ = doc.find_sheet(canvas, doc.text_evidence(canvas))
            self.assertIsNone(quad)

    def test_conflicting_large_sheets_are_ambiguous(self):
        page = self.page()
        q1 = np.int32([[[50, 70]], [[730, 70]], [[730, 930]], [[50, 930]]])
        q2 = np.int32([[[60, 95]], [[708, 95]], [[708, 905]], [[60, 905]]])
        with mock.patch.object(doc.cv2, "findContours", return_value=([q1, q2], None)), \
                mock.patch.object(doc, "sheet_boundary", return_value=True):
            quad, ambiguous = doc.find_sheet(page, doc.text_evidence(page))
        self.assertIsNone(quad)
        self.assertTrue(ambiguous)

    @staticmethod
    def projection_score(rgb):
        ink = (cv2.cvtColor(rgb, cv2.COLOR_RGB2GRAY) < 130).sum(axis=1).astype(float)
        return float(np.square(ink).sum() / max(ink.sum(), 1))

    def test_deskew_uses_text_lines_in_both_directions(self):
        for rotation in (-2.4, 2.4):
            with self.subTest(rotation=rotation):
                if self.output.exists():
                    self.output.unlink()
                page = self.page()
                matrix = cv2.getRotationMatrix2D((390, 500), rotation, 1)
                tilted = cv2.warpAffine(page, matrix, (780, 1000), borderValue=(255, 255, 255))
                evidence = doc.text_evidence(tilted)
                angle = doc.deskew_angle(evidence)
                self.assertIsNotNone(angle)
                self.assertAlmostEqual(angle, -rotation, delta=0.35)
                result = self.process(tilted)
                self.assertIn("deskew", result["operations"])
                self.assertGreater(self.projection_score(self.output_array()), self.projection_score(tilted) * 1.15)

    def test_large_angle_and_rules_alone_do_not_trigger_deskew(self):
        matrix = cv2.getRotationMatrix2D((390, 500), 9, 1)
        tilted = cv2.warpAffine(self.page(), matrix, (780, 1000), borderValue=(255, 255, 255))
        self.assertIsNone(doc.deskew_angle(doc.text_evidence(tilted)))
        ruled = np.full((1000, 780, 3), 248, np.uint8)
        for y in range(100, 900, 50):
            cv2.line(ruled, (30, y), (740, y + 22), (30, 30, 30), 2)
        self.assertIsNone(doc.deskew_angle(doc.text_evidence(ruled)))

    def test_expanded_rotation_does_not_clip_edge_marks(self):
        page = np.full((600, 800, 3), 255, np.uint8)
        for x, y in ((0, 0), (790, 0), (0, 590), (790, 590)):
            page[y:y + 10, x:x + 10] = 0
        rotated = doc.rotate_expanded(page, 4)
        self.assertGreater(rotated.shape[0], 600)
        self.assertGreater(rotated.shape[1], 800)
        self.assertGreater(int((rotated[:, :, 0] < 100).sum()), 360)
        np.testing.assert_array_equal(rotated[0, 0], (255, 255, 255))

    def test_exif_is_transposed_once_and_removed_from_derivative(self):
        image = Image.fromarray(self.page())
        exif = Image.Exif()
        exif[274] = 6
        exif[315] = "private metadata"
        image.save(self.source, format="JPEG", quality=96, exif=exif)
        digest = hashlib.sha256(self.source.read_bytes()).digest()
        loaded, size, operations = doc.load_image(self.source, 3200, 24_000_000)
        self.assertEqual(size, (1000, 780))
        self.assertEqual(loaded.shape, (780, 1000, 3))
        self.assertEqual(operations, ["exif_transpose"])
        with mock.patch.object(doc, "osd_rotation", return_value=(0, "osd_low_confidence")):
            result = doc.enhance(self.source, self.output)
        self.assertEqual(result["status"], "enhanced")
        self.assertEqual(result["operations"].count("exif_transpose"), 1)
        with Image.open(self.output) as output:
            self.assertEqual(output.size, (1000, 780))
            self.assertFalse(output.getexif())
        self.assertEqual(digest, hashlib.sha256(self.source.read_bytes()).digest())

    def test_transparency_is_composited_on_white(self):
        page = self.page()
        rgba = np.dstack((page, np.where(page[:, :, 0] > 240, 0, 255).astype(np.uint8)))
        rgba[rgba[:, :, 3] == 0, :3] = 0
        result = self.process(rgba)
        self.assertEqual(result["status"], "enhanced")
        self.assertIn("alpha_to_white", result["operations"])
        output = self.output_array()
        self.assertGreater(int(output[10:40, 10:40].min()), 250)
        self.assertLess(int(output.min()), 50)

    def test_corrupt_and_truncated_images_fail_without_output(self):
        for data in (b"not an image", b"\x89PNG\r\n\x1a\ncorrupt"):
            self.source.write_bytes(data)
            with self.assertRaisesRegex(doc.DocumentError, "invalid_image"):
                doc.enhance(self.source, self.output)
            self.assertEqual(self.source.read_bytes(), data)
            self.assertFalse(self.output.exists())
        self.save(self.page(), format="JPEG")
        data = self.source.read_bytes()[:1000]
        self.source.write_bytes(data)
        with self.assertRaisesRegex(doc.DocumentError, "invalid_image"):
            doc.enhance(self.source, self.output)
        self.assertFalse(self.output.exists())

    def test_multiframe_tiff_and_animated_png_are_rejected(self):
        image = Image.fromarray(self.page())
        second = Image.new("RGB", image.size, "white")
        for format_name in ("TIFF", "PNG"):
            image.save(self.source, format=format_name, save_all=True, append_images=[second])
            with self.assertRaisesRegex(doc.DocumentError, "multiframe_image"):
                doc.enhance(self.source, self.output)
            self.assertFalse(self.output.exists())

    def test_header_limits_are_checked_before_load(self):
        self.source.write_bytes(b"header")
        for size, format_name, frames, expected in (
                ((6000, 5000), "JPEG", 1, "pixel_limit"),
                ((780, 1000), "GIF", 1, "unsupported_format"),
                ((780, 1000), "TIFF", 2, "multiframe_image")):
            with self.subTest(reason=expected):
                image = mock.MagicMock(size=size, format=format_name, n_frames=frames)
                image.__enter__.return_value = image
                with mock.patch.object(doc.Image, "open", return_value=image):
                    with self.assertRaisesRegex(doc.DocumentError, expected):
                        doc.load_image(self.source, 3200, 24_000_000)
                image.load.assert_not_called()

    def test_input_and_existing_output_can_never_be_overwritten(self):
        digest = self.save(self.shadow_page())
        with self.assertRaisesRegex(doc.DocumentError, "same_path"):
            doc.enhance(self.source, self.source)
        self.output.write_bytes(b"existing derivative")
        with self.assertRaisesRegex(doc.DocumentError, "output_exists"):
            doc.enhance(self.source, self.output)
        self.assertEqual(self.output.read_bytes(), b"existing derivative")
        self.assertEqual(digest, hashlib.sha256(self.source.read_bytes()).digest())
        with self.assertRaisesRegex(doc.DocumentError, "output_exists"):
            doc.write_output(self.output, b"replacement")
        self.assertEqual(self.output.read_bytes(), b"existing derivative")

    def test_failed_output_write_removes_only_its_partial_derivative(self):
        digest = self.save(self.page())
        with mock.patch.object(doc.os, "fsync", side_effect=OSError("private filesystem details")):
            with self.assertRaisesRegex(doc.DocumentError, "output_write_failed"):
                doc.write_output(self.output, b"partial output")
        self.assertFalse(self.output.exists())
        self.assertEqual(digest, hashlib.sha256(self.source.read_bytes()).digest())

    def test_relative_paths_and_unbounded_arguments_are_rejected(self):
        self.save(self.page())
        with self.assertRaisesRegex(doc.DocumentError, "absolute_paths_required"):
            doc.enhance("relative.png", self.output)
        for kwargs in ({"max_side": 3201}, {"max_pixels": 24_000_001}, {"max_pixels": 0},
                       {"osd_timeout": 31}, {"osd_timeout": float("nan")}, {"max_side": 0}):
            with self.subTest(kwargs=kwargs), self.assertRaisesRegex(doc.DocumentError, "invalid_arguments"):
                doc.enhance(self.source, self.output, **kwargs)

    def test_output_and_detection_resolutions_are_bounded(self):
        large = cv2.resize(self.page(), (1560, 2000))
        evidence = doc.text_evidence(large)
        self.assertLessEqual(max(evidence["gray"].shape), 1200)
        result = self.process(large, osd=(180, None), max_side=1000)
        self.assertEqual(result["status"], "enhanced")
        self.assertLessEqual(max(result["width"], result["height"]), 1000)
        self.assertIn("resize", result["operations"])
        self.assertEqual(self.output_array().shape[:2], (result["height"], result["width"]))

    def test_high_entropy_jpeg_adapts_to_five_megabytes(self):
        noisy = np.random.default_rng(8).integers(0, 256, (2300, 2300, 3), dtype=np.uint8)
        data, size, resized, quality = doc.encode_jpeg(noisy, 3200)
        self.assertLessEqual(len(data), 5_000_000)
        self.assertTrue(resized or quality < 94)
        self.assertGreaterEqual(quality, 82)
        self.assertLessEqual(max(size), 3200)
        with Image.open(io.BytesIO(data)) as image:
            self.assertEqual(image.format, "JPEG")
            self.assertEqual(image.size, size)
            image.load()

    def test_jpeg_resize_fallback_is_real_and_bounded(self):
        noisy = np.random.default_rng(3).integers(0, 256, (1000, 780, 3), dtype=np.uint8)
        with mock.patch.object(doc, "MAX_JPEG_BYTES", 300_000):
            data, size, resized, quality = doc.encode_jpeg(noisy, 3200)
        self.assertLessEqual(len(data), 300_000)
        self.assertTrue(resized)
        self.assertLess(max(size), 1000)
        self.assertGreaterEqual(quality, 82)

    def test_osd_clockwise_directions_in_processing(self):
        for angle in (90, 180, 270):
            with self.subTest(angle=angle):
                if self.output.exists():
                    self.output.unlink()
                page = self.page()
                page[20:45, 25:55] = (190, 20, 35)
                result = self.process(page, osd=(angle, None))
                self.assertIn(f"rotate_{angle}", result["operations"])
                output = self.output_array()
                mask = (output[:, :, 0] > 130) & (output[:, :, 1] < 70) & (output[:, :, 2] < 85)
                ys, xs = np.where(mask)
                x, y = float(xs.mean()) / output.shape[1], float(ys.mean()) / output.shape[0]
                expected = {90: (0.96, 0.05), 180: (0.95, 0.96), 270: (0.04, 0.95)}[angle]
                self.assertAlmostEqual(x, expected[0], delta=0.04)
                self.assertAlmostEqual(y, expected[1], delta=0.04)

    def test_low_confidence_and_missing_osd_do_not_prevent_safe_improvements(self):
        for warning in ("osd_low_confidence", "osd_unavailable", "osd_timeout", "osd_insufficient_text"):
            with self.subTest(warning=warning):
                if self.output.exists():
                    self.output.unlink()
                result = self.process(self.shadow_page(), osd=(0, warning))
                self.assertEqual(result["status"], "enhanced")
                self.assertIn(warning, result["warnings"])
                self.assertFalse(any(op.startswith("rotate_") for op in result["operations"]))


@unittest.skipUnless(AVAILABLE, "OpenCV, NumPy and Pillow are required")
class OsdTests(unittest.TestCase):
    def test_confident_osd_runs_cli_without_shell_and_sets_thread_limit(self):
        rgb = np.full((300, 400, 3), 240, np.uint8)
        for angle in (0, 90, 180, 270):
            response = subprocess.CompletedProcess([], 0,
                f"Rotate: {angle}\nOrientation confidence: 15.25\nScript: Latin\n".encode(), b"")
            with mock.patch.object(doc.subprocess, "run", return_value=response) as run:
                rotation, warning = doc.osd_rotation(rgb, "binary path/tesseract;not-a-shell", 4)
            self.assertEqual((rotation, warning), (angle, None))
            args, kwargs = run.call_args
            self.assertIsInstance(args[0], list)
            self.assertEqual(args[0][0], "binary path/tesseract;not-a-shell")
            self.assertIn("--psm", args[0])
            self.assertEqual(args[0][args[0].index("--psm") + 1], "0")
            self.assertEqual(kwargs["env"]["OMP_THREAD_LIMIT"], "1")
            self.assertEqual(kwargs["timeout"], 4)
            self.assertFalse(kwargs["shell"])
            self.assertTrue(kwargs["input"].startswith(b"\x89PNG"))

    def test_low_confidence_and_invalid_output_never_rotate(self):
        rgb = np.full((300, 400, 3), 240, np.uint8)
        for stdout, expected in ((b"Rotate: 90\nOrientation confidence: 14.99\n", "osd_low_confidence"),
                                 (b"Rotate: 45\nOrientation confidence: 99\n", "osd_invalid"),
                                 (b"Rotate: 180\nOrientation confidence: nan\n", "osd_invalid"),
                                 (b"private OCR text and path", "osd_invalid")):
            response = subprocess.CompletedProcess([], 0, stdout, b"private path")
            with mock.patch.object(doc.subprocess, "run", return_value=response):
                self.assertEqual(doc.osd_rotation(rgb, "tesseract", 15), (0, expected))

    def test_missing_timeout_and_insufficient_text_are_safe_skips(self):
        rgb = np.full((300, 400, 3), 240, np.uint8)
        for error, warning in ((FileNotFoundError("private path"), "osd_unavailable"),
                               (subprocess.TimeoutExpired("private path", 1), "osd_timeout"),
                               (OSError("private details"), "osd_failed")):
            with mock.patch.object(doc.subprocess, "run", side_effect=error):
                self.assertEqual(doc.osd_rotation(rgb, "tesseract", 1), (0, warning))
        response = subprocess.CompletedProcess([], 1, b"", b"Too few characters. private path")
        with mock.patch.object(doc.subprocess, "run", return_value=response):
            self.assertEqual(doc.osd_rotation(rgb, "tesseract", 1), (0, "osd_insufficient_text"))

    def test_rotation_direction_maps_pixels_clockwise(self):
        rgb = np.arange(18, dtype=np.uint8).reshape(2, 3, 3)
        for degrees, target in ((90, (0, -1)), (180, (-1, -1)), (270, (-1, 0))):
            rotated = doc.rotate_clockwise(rgb, degrees)
            np.testing.assert_array_equal(rotated[target], rgb[0, 0])
            self.assertEqual(sorted(rotated.ravel().tolist()), sorted(rgb.ravel().tolist()))
        np.testing.assert_array_equal(doc.rotate_clockwise(rgb, 0), rgb)


if __name__ == "__main__":
    unittest.main()
