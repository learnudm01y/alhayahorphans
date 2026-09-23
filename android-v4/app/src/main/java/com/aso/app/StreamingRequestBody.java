package com.aso.app;

import java.io.File;
import java.io.FileInputStream;
import java.io.IOException;
import okhttp3.MediaType;
import okhttp3.RequestBody;
import okio.BufferedSink;

public class StreamingRequestBody extends RequestBody {
    private static final int CHUNK_SIZE = 2048;
    private final File file;
    private final String contentType;

    public StreamingRequestBody(File file, String contentType) {
        this.file = file;
        this.contentType = contentType;
    }

    @Override
    public MediaType contentType() {
        return MediaType.parse(contentType);
    }

    @Override
    public long contentLength() {
        return file.length();
    }

    @Override
    public void writeTo(BufferedSink sink) throws IOException {
        try (FileInputStream in = new FileInputStream(file)) {
            byte[] buffer = new byte[CHUNK_SIZE];
            int read;
            while ((read = in.read(buffer)) != -1) {
                sink.write(buffer, 0, read);
            }
        }
    }
}
