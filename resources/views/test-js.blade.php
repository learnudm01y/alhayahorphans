<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>اختبار JavaScript</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h3>اختبار JavaScript Syntax</h3>
                    </div>
                    <div class="card-body">
                        <button id="testBtn" class="btn btn-primary">تشغيل الاختبار</button>
                        <div id="output" class="mt-3 p-3 bg-light border rounded">
                            <p>اضغط على الزر لتشغيل الاختبار...</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        console.log('🎯 بداية اختبار JavaScript');

        document.addEventListener('DOMContentLoaded', function() {
            const testBtn = document.getElementById('testBtn');
            const output = document.getElementById('output');

            testBtn.addEventListener('click', function() {
                output.innerHTML = '<p>🚀 تشغيل الاختبار...</p>';

                try {
                    // Test basic syntax
                    const testData = {
                        name: 'test',
                        value: 42,
                        array: [1, 2, 3]
                    };

                    // Test arrow function
                    const testArrow = () => {
                        return 'Arrow function works';
                    };

                    // Test template literals
                    const message = `Test message: ${testData.name}`;

                    output.innerHTML = `
                        <p>✅ JavaScript Syntax Test Completed</p>
                        <p>Object: ${JSON.stringify(testData)}</p>
                        <p>Arrow Function: ${testArrow()}</p>
                        <p>Template Literal: ${message}</p>
                    `;

                } catch (error) {
                    output.innerHTML = `<p>❌ Error: ${error.message}</p>`;
                    console.error('Test error:', error);
                }
            });
        });
    </script>
</body>
</html>
