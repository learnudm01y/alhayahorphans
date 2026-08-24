package com.aso.app;

public class SmartRetryEvaluator {
    private static final int MAX_RETRIES = 5;
    private static final long INITIAL_BACKOFF_MS = 2000;

    public static boolean shouldRetry(int currentAttempt, Throwable error) {
        if (currentAttempt >= MAX_RETRIES) {
            return false;
        }

        // Retry on network errors
        if (error instanceof java.net.ConnectException ||
            error instanceof java.net.SocketTimeoutException ||
            error instanceof java.net.UnknownHostException) {
            return true;
        }

        return false;
    }

    public static long calculateBackoffDelay(int currentAttempt) {
        // Exponential backoff: 2s, 4s, 8s, 16s, 32s
        return INITIAL_BACKOFF_MS * (long) Math.pow(2, currentAttempt);
    }
}
