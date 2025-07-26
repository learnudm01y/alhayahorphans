<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>اختبار البحث</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body>
    <div class="container mt-5">
        <h2>اختبار البحث</h2>

        <form id="testForm">
            <div class="row">
                <div class="col-md-6">
                    <label for="search_text">نص البحث:</label>
                    <input type="text" class="form-control" id="search_text" name="search_text" value="Kyle Christine Byers Brenda Mason Nayda Ayers">
                </div>
                <div class="col-md-6">
                    <label for="search_type">نوع البحث:</label>
                    <select class="form-select" id="search_type" name="search_type">
                        <option value="all">جميع الجداول</option>
                        <option value="main_records">السجلات الرئيسية</option>
                    </select>
                </div>
            </div>

            <button type="submit" class="btn btn-primary mt-3">بحث</button>
        </form>

        <div id="results" class="mt-4"></div>
    </div>

    <script>
        $('#testForm').on('submit', function(e) {
            e.preventDefault();

            const formData = new FormData(this);

            console.log('Sending search request...');
            console.log('Search text:', formData.get('search_text'));
            console.log('Search type:', formData.get('search_type'));

            $.ajax({
                url: '/admin/search-records',
                method: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                success: function(response) {
                    console.log('Success response:', response);

                    let html = '<div class="alert alert-success">نجح البحث!</div>';
                    html += '<pre>' + JSON.stringify(response, null, 2) + '</pre>';

                    $('#results').html(html);
                },
                error: function(xhr) {
                    console.error('Error response:', xhr);

                    let html = '<div class="alert alert-danger">فشل البحث!</div>';
                    html += '<pre>' + JSON.stringify(xhr.responseJSON, null, 2) + '</pre>';

                    $('#results').html(html);
                }
            });
        });
    </script>
</body>
</html>
