package com.aso.app.v4.admin_offline;

import android.content.Context;
import android.graphics.Color;
import android.graphics.pdf.PdfDocument;
import android.os.Environment;
import android.util.Log;

import com.aso.app.v4.db.AdminOfflineDatabaseHelperV4;

import org.json.JSONArray;
import org.json.JSONObject;

import java.io.File;
import java.io.FileOutputStream;
import java.io.OutputStream;
import java.text.SimpleDateFormat;
import java.util.Date;
import java.util.Locale;

/**
 * Local offline report generator (Doc/03 §3).
 *
 * Reads from admin_reports_source_v4 only — no network, no MySQL.
 * Output: PDF via android.graphics.pdf.PdfDocument (no external library).
 * Watermark note: "تم إنشاؤه محلياً بتاريخ البيانات: XX".
 */
public final class ReportsEngineV4 {
    private static final String TAG = "ReportsEngineV4";
    private static final String WATERMARK_PREFIX = "تم إنشاؤه محلياً بتاريخ البيانات: ";

    private ReportsEngineV4() {}

    /** In-memory report bundle for HTML preview (web UI) — no PDF needed. */
    public static JSONObject buildReportBundle(Context context, String reportType) {
        AdminOfflineDatabaseHelperV4 db = AdminOfflineDatabaseHelperV4.getInstance(context);
        JSONObject bundle = new JSONObject();
        try {
            JSONArray rows = db.queryReportRows(reportType, null);
            bundle.put("report_type", reportType);
            bundle.put("generated_locally_at",
                new SimpleDateFormat("yyyy-MM-dd HH:mm:ss", Locale.US).format(new Date()));
            bundle.put("data_as_of",
                new SimpleDateFormat("yyyy-MM-dd HH:mm:ss", Locale.US)
                    .format(new Date(db.getReportsFetchedAt(reportType))));
            bundle.put("watermark", WATERMARK_PREFIX
                + new SimpleDateFormat("yyyy-MM-dd HH:mm", Locale.US)
                    .format(new Date(db.getReportsFetchedAt(reportType))));
            bundle.put("row_count", rows.length());
            bundle.put("rows", rows);
        } catch (Exception e) {
            Log.e(TAG, "buildReportBundle failed", e);
        }
        return bundle;
    }

    /**
     * Generate a simple multi-page PDF from flat report rows.
     * @return absolute path of written file, or null on failure.
     */
    public static String generatePdf(Context context, String reportType, String title) {
        JSONArray rows;
        long dataAsOf;
        try {
            AdminOfflineDatabaseHelperV4 db = AdminOfflineDatabaseHelperV4.getInstance(context);
            rows = db.queryReportRows(reportType, null);
            dataAsOf = db.getReportsFetchedAt(reportType);
        } catch (Exception e) {
            Log.e(TAG, "load rows failed", e);
            return null;
        }

        PdfDocument document = new PdfDocument();
        try {
            int pageWidth = 595;  // A4 @ 72dpi
            int pageHeight = 842;
            int margin = 40;
            int lineHeight = 16;
            int y = margin;
            PdfDocument.PageInfo pageInfo = new PdfDocument.PageInfo
                .Builder(pageWidth, pageHeight, 1).create();
            PdfDocument.Page page = document.startPage(pageInfo);
            android.graphics.Canvas canvas = page.getCanvas();

            android.graphics.Paint titlePaint = new android.graphics.Paint();
            titlePaint.setTextSize(16f);
            titlePaint.setFakeBoldText(true);
            titlePaint.setColor(Color.BLACK);

            android.graphics.Paint bodyPaint = new android.graphics.Paint();
            bodyPaint.setTextSize(10f);
            bodyPaint.setColor(Color.DKGRAY);

            android.graphics.Paint wmPaint = new android.graphics.Paint();
            wmPaint.setTextSize(9f);
            wmPaint.setColor(Color.GRAY);

            canvas.drawText(title != null ? title : reportType, margin, y, titlePaint);
            y += lineHeight + 6;
            String watermark = WATERMARK_PREFIX
                + new SimpleDateFormat("yyyy-MM-dd HH:mm", Locale.US).format(new Date(dataAsOf));
            canvas.drawText(watermark, margin, y, wmPaint);
            y += lineHeight + 10;

            for (int i = 0; i < rows.length(); i++) {
                if (y > pageHeight - margin) {
                    document.finishPage(page);
                    pageInfo = new PdfDocument.PageInfo
                        .Builder(pageWidth, pageHeight, document.getPages().size() + 1).create();
                    page = document.startPage(pageInfo);
                    canvas = page.getCanvas();
                    y = margin;
                }
                try {
                    JSONObject row = rows.getJSONObject(i);
                    String line = (i + 1) + ". " + summarize(row);
                    canvas.drawText(line, margin, y, bodyPaint);
                    y += lineHeight;
                } catch (Exception ignored) {}
            }
            document.finishPage(page);

            File outDir = context.getExternalFilesDir(Environment.DIRECTORY_DOCUMENTS);
            if (outDir == null) outDir = context.getFilesDir();
            if (!outDir.exists()) outDir.mkdirs();
            String fileName = "v4_report_" + reportType + "_"
                + System.currentTimeMillis() + ".pdf";
            File outFile = new File(outDir, fileName);
            OutputStream os = new FileOutputStream(outFile);
            try {
                document.writeTo(os);
            } finally {
                os.close();
            }
            Log.i(TAG, "PDF written: " + outFile.getAbsolutePath()
                + " rows=" + rows.length());
            return outFile.getAbsolutePath();
        } catch (Exception e) {
            Log.e(TAG, "generatePdf failed", e);
            return null;
        } finally {
            try { document.close(); } catch (Exception ignored) {}
        }
    }

    private static String summarize(JSONObject row) {
        String[] preferred = {"orphan_name", "guardian_name", "data_first_name",
            "re_guardian_name", "person_name", "identity_number", "file_id_number",
            "bank_name", "client_uuid"};
        StringBuilder sb = new StringBuilder();
        for (String k : preferred) {
            if (row.has(k) && !row.isNull(k)) {
                if (sb.length() > 0) sb.append(" | ");
                sb.append(k).append("=").append(row.optString(k, ""));
            }
            if (sb.length() > 120) break;
        }
        if (sb.length() == 0) {
            return row.toString().substring(0, Math.min(120, row.toString().length()));
        }
        return sb.toString();
    }
}
