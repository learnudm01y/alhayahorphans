package com.aso.app.v4.sync;

import android.util.Log;

import java.nio.charset.StandardCharsets;
import java.security.MessageDigest;

/**
 * Generates the X-Idempotency-Key header value.
 *
 * Algorithm MUST match the backend:
 *   sha256Hex( clientUuid + "|" + operationType + "|" + payloadJson )
 *
 * Retries reuse the same key (stored in outbox_local) so the server
 * returns the cached response without a second INSERT.
 */
public final class IdempotencyKeyGeneratorV4 {
    private static final String TAG = "IdempotencyKeyV4";

    private IdempotencyKeyGeneratorV4() {}

    public static String generate(String clientUuid, String operationType, String payloadJson) {
        String raw = clientUuid + "|" + operationType + "|" + payloadJson;
        return sha256Hex(raw);
    }

    public static String sha256Hex(String input) {
        try {
            MessageDigest digest = MessageDigest.getInstance("SHA-256");
            byte[] hash = digest.digest(input.getBytes(StandardCharsets.UTF_8));
            StringBuilder hex = new StringBuilder(hash.length * 2);
            for (byte b : hash) {
                String h = Integer.toHexString(0xff & b);
                if (h.length() == 1) hex.append('0');
                hex.append(h);
            }
            return hex.toString();
        } catch (Exception e) {
            Log.e(TAG, "SHA-256 unavailable", e);
            // Deterministic fallback keeps idempotency stable across retries.
            return Integer.toHexString(input.hashCode());
        }
    }
}
