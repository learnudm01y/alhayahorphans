 <!DOCTYPE html>
 <html lang="ar" dir="rtl">

 <head>
     <meta charset="UTF-8">
     <meta name="viewport" content="width=device-width, initial-scale=1.0">
     <title>نموذج مثل Google Forms</title>
     <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
     {{-- <link rel="stylesheet" href="{{ asset('frontend/style.css') }}"> --}}
     <style>
        /* الأنماط الأساسية */
body {
    background-color: #eaeaea;
    font-family: 'Tajawal', sans-serif;
    direction: rtl;
    text-align: right;
    margin: 0;
    padding: 20px;
    display: flex;
    justify-content: center;
    flex-wrap: wrap;
}

#form-content {
    width: 60%;
    max-width: 800px;
    padding: 20px;
    position: relative;
    transition: margin-right 0.3s ease-in-out, margin-top 0.3s ease-in-out;
    margin-right: 60px;
}

/* عند فتح الشريط الجانبي على سطح المكتب */
#form-content.shifted {
    margin-right: 400px;
}

/* تعديل الهوامش للأجهزة التي عرضها أقل من 991 بكسل */
@media (max-width: 991px) {
    #form-content {
        margin-right: 20px;
        margin-top: 80px;
    }

    #form-content.shifted {
        margin-right: 20px;
    }
}

/* تحسين التصميم للأجهزة الصغيرة (مثل الجوال) */
@media (max-width: 768px) {
    #form-content {
        width: 90%;
        margin-right: 0;
        padding: 10px;
    }

    .toggle-sidebar {
        top: 10px;
        right: 10px;
        padding: 5px 10px;
    }

    .sidebar {
        width: 90%;
        right: -90%;
    }

    .sidebar.active {
        right: 0;
    }
}

.card {
    border-radius: 12px;
    background: #ffffff;
    box-shadow: 0px 4px 12px rgba(0, 0, 0, 0.2);
    margin-bottom: 20px;
    display: none;
}

.card.active {
    display: block;
}

h2 {
    background: linear-gradient(45deg, #34495e, #16a085);
    color: white;
    padding: 15px;
    border-radius: 10px;
    text-align: center;
    margin-bottom: 20px;
}

.btn-custom {
    background: #16a085;
    color: white;
    border-radius: 8px;
    padding: 10px 20px;
    font-size: 16px;
    width: 48%;
    margin: 5px 1% 0 1%;
    transition: background 0.3s ease;
}

.btn-custom:hover {
    background: #13856a;
}

.sidebar {
    width: 340px;
    background: #2c3e50;
    color: white;
    height: 100vh;
    padding: 20px;
    position: fixed;
    right: -380px;
    top: 0;
    transition: right 0.3s ease-in-out;
    z-index: 1100;
}

.sidebar.active {
    right: 0;
}

.toggle-sidebar {
    position: fixed;
    top: 20px;
    right: 20px;
    background: #16a085;
    color: white;
    border: none;
    padding: 10px 15px;
    border-radius: 5px;
    cursor: pointer;
    z-index: 1200;
}

.toggle-sidebar.hidden {
    display: none;
}

.close-sidebar {
    background: #c0392b;
    color: white;
    border: none;
    padding: 5px 10px;
    border-radius: 5px;
    cursor: pointer;
    position: absolute;
    top: 10px;
    left: 10px;
}

.info-box {
    background: white;
    padding: 10px;
    border-radius: 8px;
    box-shadow: 0px 4px 8px rgba(0, 0, 0, 0.2);
    color: black;
    margin-top: 60px;
    display: flex;
    flex-direction: column;
    align-items: center;
}

.info-box img {
    width: 80px;
    height: 80px;
    object-fit: cover;
    border-radius: 8px;
    margin-bottom: 10px;
}

img {
    display: block;
    margin: 0 auto;
}

.container {
    display: flex;
    justify-content: center;
}

     </style>
 </head>

 <body>
     <button id="toggleBtn" class="toggle-sidebar" onclick="toggleSidebar()">☰ عرض المعلومات</button>
     <div class="sidebar" id="sidebar">
         <button class="close-sidebar" onclick="toggleSidebar()">×</button>
         <h3>المعلومات المدخلة</h3>
         <div class="info-box">
             <img src="{{ asset('uploads/logo.jpg') }}" alt="صورة شخصية">
             <div>
                 <p><strong>اسم المعيل:</strong> <span id="guardian_name">-</span></p>
                 <p><strong>رقم هوية المعيل:</strong> <span id="guardian_id">-</span></p>
                 <p><strong>اسم الأب المتوفى:</strong> <span id="father_name">-</span></p>
                 <p><strong>رقم هوية الأب:</strong> <span id="father_id">-</span></p>
                 <p><strong>اسم الأم المتوفاة:</strong> <span id="mother_name">-</span></p>
                 <p><strong>رقم هوية الأم:</strong> <span id="mother_id">-</span></p>
             </div>
         </div>
         <div class="info-box">
             <img src="{{ asset('uploads/logo.jpg') }}" alt="صورة شخصية">
             <div>
                 <p><strong>اسم المعيل:</strong> <span id="guardian_name">-</span></p>
                 <p><strong>رقم هوية المعيل:</strong> <span id="guardian_id">-</span></p>
                 <p><strong>اسم الأب المتوفى:</strong> <span id="father_name">-</span></p>
                 <p><strong>رقم هوية الأب:</strong> <span id="father_id">-</span></p>
                 <p><strong>اسم الأم المتوفاة:</strong> <span id="mother_name">-</span></p>
                 <p><strong>رقم هوية الأم:</strong> <span id="mother_id">-</span></p>
             </div>
         </div>
     </div>
     <div class="content" id="form-content">
         <img src="{{ asset('uploads/logo.jpg') }}" alt="صورة توضيحية" class="img-fluid mb-4"
             style="max-width: 50%; border-radius: 10px; ">
         <!-- قسم 2: بيانات المعيل -->
         <div class="card p-4 active" id="section2">
             <h2>قسم 2 من 11 - بيانات المعيل</h2>
             <form>
                 <div class="mb-3">
                     <label class="form-label">اسم المعيل رباعي *</label>
                     <input type="text" class="form-control" required
                         oninput="updateSidebar('guardian_name', this.value)">
                 </div>
                 <div class="mb-3">
                     <label class="form-label">رقم هوية المعيل *</label>
                     <input type="text" class="form-control" required
                         oninput="updateSidebar('guardian_id', this.value)">
                 </div>
                 <div class="mb-3">
                     <label class="form-label">صلة القرابة *</label>
                     <select class="form-select" id="relationshipSelect" required
                         onchange="handleRelationship(this.value)">
                         <option value="">اختر...</option>
                         <option value="أم">أم</option>
                         <option value="خال / خالة">خال / خالة</option>
                         <option value="جد / جدة جهة الأم">جد / جدة جهة الأم</option>
                         <option value="زوجة الأب">زوجة الأب</option>
                         <option value="أخ / أخت">أخ / أخت</option>
                         <option value="أخرى">أخرى</option>
                     </select>
                 </div>
                 <!-- الحقل الجديد الذي يظهر عند اختيار "أخرى" -->
                 <div class="mb-3" id="otherRelationshipField" style="display: none;">
                     <label class="form-label">يرجى تحديد صلة القرابة:</label>
                     <input type="text" class="form-control" placeholder="أدخل صلة القرابة">
                 </div>
                 <div class="mb-3">
                     <label class="form-label">رقم الجوال*</label>
                     <input type="text" class="form-control" required
                         oninput="updateSidebar('guardian_id', this.value)">
                 </div>
                 <div class="mb-3">
                     <label class="form-label">رقم الجوال البديل*</label>
                     <input type="text" class="form-control" required
                         oninput="updateSidebar('guardian_id', this.value)">
                 </div>
                 <div class="mb-3">
                     <label class="form-label">المحافظة*</label>
                     <select class="form-select" required>
                         <option>شمال غزة</option>
                         <option>غزة</option>
                         <option>الوسطى</option>
                         <option>خانيونس</option>
                         <option>رفح</option>
                     </select>
                 </div>
                 <div class="mb-3">
                     <label class="form-label">العنوان التفصيلي*</label>
                     <input type="text" class="form-control" required
                         oninput="updateSidebar('guardian_id', this.value)">
                 </div>
                 <div class="mb-3">
                     <label class="form-label">احتياجات الأسرة*</label>
                     <input type="text" class="form-control" required
                         oninput="updateSidebar('guardian_id', this.value)">
                 </div>
                 <div class="mb-3">
                     <label class="form-label">صورة هوية المعيل*</label>
                     <input type="file" class="form-control" required>
                 </div>
                 {{-- <div class="mb-3">
            <label class="form-label">حجة الوصاية أو الإعالة*</label>
            <input type="file" class="form-control" required>
          </div> --}}
                 <div class="mb-3">
                     <label for="imageUpload" class="form-label">Choose images</label>
                     <input class="form-control" type="file" id="imageUpload" name="images[]" multiple
                         accept="image/*">
                 </div>
                 <div class="d-flex justify-content-between">
                     <button type="button" class="btn btn-custom" onclick="navigateSection(-1)">السابق</button>
                     <button type="button" class="btn btn-custom" onclick="navigateSection(1)">التالي</button>
                 </div>
             </form>
         </div>
         <!-- قسم 3: بيانات الأب المتوفى -->
         <div class="card p-4" id="section3">
             <h2>قسم 3 من 11 - البيانات الخاصة بالأب المتوفى</h2>
             <form>
                 <div class="mb-3">
                     <label class="form-label">اسم المتوفى *</label>
                     <input type="text" class="form-control" required
                         oninput="updateSidebar('father_name', this.value)">
                 </div>
                 <div class="mb-3">
                     <label class="form-label">رقم الهوية *</label>
                     <input type="text" class="form-control" required
                         oninput="updateSidebar('father_id', this.value)">
                 </div>
                 <div class="mb-3">
                     <label class="form-label">تاريخ الوفاة *</label>
                     <input type="date" class="form-control" required>
                 </div>
                 <div class="mb-3">
                     <label class="form-label">سبب الوفاة *</label>
                     <select class="form-select" required>
                         <option>مرض</option>
                         <option>حادث سير</option>
                         <option>وفاة طبيعية</option>
                         <option>أخرى</option>
                     </select>
                 </div>
                 <div class="mb-3">
                     <label class="form-label">صورة شهادة الوفاة *</label>
                     <input type="file" class="form-control" required>
                 </div>
                 <div class="mb-3">
                     <label class="form-label">هل الأم متوفية؟ *</label>
                     <div>
                         <input type="radio" name="mother_deceased" id="mother_deceased_yes" value="نعم"
                             required onchange="handleMotherDeceased(this.value)">
                         <label for="mother_deceased_yes">نعم</label>
                         <input type="radio" name="mother_deceased" id="mother_deceased_no" value="لا"
                             required onchange="handleMotherDeceased(this.value)">
                         <label for="mother_deceased_no">لا</label>
                     </div>
                 </div>
                 <div class="d-flex justify-content-between">
                     <button type="button" class="btn btn-custom" onclick="navigateSection(-1)">السابق</button>
                     <button type="button" class="btn btn-custom" onclick="navigateSection(1)">التالي</button>
                 </div>
             </form>
         </div>
         <!-- قسم 4: بيانات الأم المتوفاة -->
         <div class="card p-4" id="section4">
             <h2>قسم 4 من 11 - البيانات الخاصة بالأم المتوفاة</h2>
             <form>
                 <div class="mb-3">
                     <label class="form-label">اسم الأم المتوفاة *</label>
                     <input type="text" class="form-control" required
                         oninput="updateSidebar('mother_name', this.value)">
                 </div>
                 <div class="mb-3">
                     <label class="form-label">رقم الهوية الأم *</label>
                     <input type="text" class="form-control" required
                         oninput="updateSidebar('mother_id', this.value)">
                 </div>
                 <div class="mb-3">
                     <label class="form-label">تاريخ وفاة الأم *</label>
                     <input type="date" class="form-control" required>
                 </div>
                 <div class="mb-3">
                     <label class="form-label">سبب وفاة الأم *</label>
                     <select class="form-select" required>
                         <option>مرض</option>
                         <option>حادث سير</option>
                         <option>وفاة طبيعية</option>
                         <option>أخرى</option>
                     </select>
                 </div>
                 <div class="mb-3">
                     <label class="form-label">صورة شهادة وفاة الأم *</label>
                     <input type="file" class="form-control" required>
                 </div>
                 <div class="d-flex justify-content-between">
                     <button type="button" class="btn btn-custom" onclick="navigateSection(-1)">السابق</button>
                     <button type="button" class="btn btn-custom" onclick="navigateSection(1)">التالي</button>
                 </div>
             </form>
         </div>
         <!-- قسم 5: بيانات اليتيم الأول -->
         <div class="card p-4" id="section5">
             <h2>قسم 5 من 11 - البيانات الخاصة باليتيم الأول</h2>
             <form id="orphanForm">
                 <div class="mb-3">
                     <label class="form-label">اسم اليتيم الأول *</label>
                     <input type="text" class="form-control" required>
                 </div>
                 <div class="mb-3">
                     <label class="form-label">رقم هوية اليتيم الأول *</label>
                     <input type="text" class="form-control" required>
                 </div>
                 <div class="mb-3">
                     <label class="form-label">تاريخ ميلاد اليتيم الأول *</label>
                     <input type="date" class="form-control" required>
                 </div>
                 <div class="mb-3">
                     <label class="form-label">الجنس لليتيم الأول *</label>
                     <select class="form-select" required>
                         <option>ذكر</option>
                         <option>أنثى</option>
                     </select>
                 </div>
                 <div class="mb-3">
                     <label class="form-label">الحالة الصحية لليتيم الأول *</label>
                     <select class="form-select" required>
                         <option>سليم</option>
                         <option>معاق</option>
                         <option>مريض مزمن</option>
                     </select>
                 </div>
                 <div class="mb-3">
                     <label class="form-label">صورة شهادة ميلاد اليتيم الأول *</label>
                     <input type="file" class="form-control" required>
                 </div>
                 <div class="mb-3">
                     <label class="form-label">الصورة الشخصية لليتيم الأول (3.5 * 4.5 سم خلفية زرقاء أو بيضاء)
                         *</label>
                     <input type="file" class="form-control" required>
                 </div>
                 <div class="mb-3">
                     <label class="form-label">هل ترغب بإضافة يتيم آخر؟ *</label>
                     <div>
                         <!-- تمرير العنصر this لدالة handleAnotherOrphan -->
                         <input type="radio" name="add_another_orphan" id="add_another_orphan_yes" value="نعم"
                             required onchange="handleAnotherOrphan(this)">
                         <label for="add_another_orphan_yes">نعم</label>
                         <input type="radio" name="add_another_orphan" id="add_another_orphan_no" value="لا"
                             required onchange="handleAnotherOrphan(this)">
                         <label for="add_another_orphan_no">لا</label>
                     </div>
                 </div>
                 <div class="d-flex justify-content-between" id="orphan-nav-buttons">
                     <button type="button" class="btn btn-custom" onclick="navigateSection(-1)">السابق</button>
                     <button type="button" class="btn btn-custom" id="orphan-next-btn"
                         onclick="handleOrphanNext()">التالي</button>
                     <button type="submit" class="btn btn-custom" id="submit-btn"
                         style="display:none;">إرسال</button>
                 </div>
             </form>
         </div>
         <!-- القسم الجديد: حفظ بيانات اليتيم -->
         <div class="card p-4" id="section6">
             <h2>قسم حفظ بيانات اليتيم</h2>
             <form id="send-save-form">
                 <div class="mb-3">
                     <label for="server-url">عنوان السيرفر:</label>
                     <input type="text" class="form-control" id="server-url" name="server-url" required>
                 </div>
                 <button type="submit" class="btn btn-custom">إرسال البيانات وحفظها</button>
             </form>
             <p id="responseMessage"></p>
         </div>
     </div>
     <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
     <script>
         function handleRelationship(value) {
             const otherField = document.getElementById('otherRelationshipField');
             if (value === "أخرى") {
                 otherField.style.display = 'block';
             } else {
                 otherField.style.display = 'none';
             }
         }
         let currentSection = 2;

         function navigateSection(direction) {
             let nextSection = currentSection + direction;
             let currentElement = document.getElementById('section' + currentSection);
             let nextElement = document.getElementById('section' + nextSection);
             if (nextElement) {
                 currentElement.classList.remove('active');
                 nextElement.classList.add('active');
                 currentSection = nextSection;
             }
         }

         function toggleSidebar() {
             let sidebar = document.getElementById('sidebar');
             let formContent = document.getElementById('form-content');
             sidebar.classList.toggle('active');
             formContent.classList.toggle('shifted');
             let toggleBtn = document.getElementById('toggleBtn');
             if (sidebar.classList.contains('active')) {
                 toggleBtn.style.display = 'none';
             } else {
                 toggleBtn.style.display = 'block';
             }
         }

         function updateSidebar(label, value) {
             switch (label) {
                 case 'guardian_name':
                     document.getElementById('guardian_name').innerText = value;
                     break;
                 case 'guardian_id':
                     document.getElementById('guardian_id').innerText = value;
                     break;
                 case 'father_name':
                     document.getElementById('father_name').innerText = value;
                     break;
                 case 'father_id':
                     document.getElementById('father_id').innerText = value;
                     break;
                 case 'mother_name':
                     document.getElementById('mother_name').innerText = value;
                     break;
                 case 'mother_id':
                     document.getElementById('mother_id').innerText = value;
                     break;
                 default:
                     break;
             }
         }

         function handleMotherDeceased(value) {
             if (value === "لا") {
                 let section3 = document.getElementById('section3');
                 let section5 = document.getElementById('section5');
                 section3.classList.remove('active');
                 section5.classList.add('active');
                 currentSection = 5;
             }
         }
         // تعديل handleAnotherOrphan لتلقي العنصر (this) وليس القيمة فقط
         function handleAnotherOrphan(el) {
             if (el.value === "لا") {
                 // إزالة تفعيل القسم الحالي الذي يحتوي على العنصر المُختار
                 let currentCard = el.closest('.card');
                 currentCard.classList.remove('active');
                 // تفعيل القسم الجديد: حفظ بيانات اليتيم
                 document.getElementById('section6').classList.add('active');
                 currentSection = 6;
             } else {
                 const nextBtn = document.getElementById('orphan-next-btn');
                 nextBtn.style.display = 'block';
             }
         }

         function handleOrphanNext() {
             const radios = document.getElementsByName('add_another_orphan');
             let selectedValue = "";
             for (let radio of radios) {
                 if (radio.checked) {
                     selectedValue = radio.value;
                     break;
                 }
             }
             if (selectedValue === "نعم") {
                 // Clone section5 to add another orphan section on the same page.
                 const section5 = document.getElementById('section5');
                 const newSection = section5.cloneNode(true);
                 section5.classList.remove('active');
                 currentSection++;
                 newSection.id = "section" + currentSection;
                 // Reset orphan radio buttons in the cloned section.
                 const clonedRadios = newSection.querySelectorAll("input[name='add_another_orphan']");
                 clonedRadios.forEach(radio => radio.checked = false);
                 newSection.querySelector("#orphan-next-btn").style.display = 'block';
                 newSection.querySelector("#submit-btn").style.display = 'none';
                 document.getElementById('form-content').appendChild(newSection);
                 newSection.classList.add('active');
             }
         }
         window.addEventListener('load', function() {
             if (window.innerWidth >= 992) {
                 document.getElementById('sidebar').classList.add('active');
                 document.getElementById('form-content').classList.add('shifted');
                 document.getElementById('toggleBtn').style.display = 'none';
             } else {
                 document.getElementById('sidebar').classList.remove('active');
                 document.getElementById('form-content').classList.remove('shifted');
                 document.getElementById('toggleBtn').style.display = 'block';
             }
         });
         window.addEventListener('resize', function() {
             if (window.innerWidth >= 992) {
                 document.getElementById('sidebar').classList.add('active');
                 document.getElementById('form-content').classList.add('shifted');
                 document.getElementById('toggleBtn').style.display = 'none';
             } else {
                 document.getElementById('sidebar').classList.remove('active');
                 document.getElementById('form-content').classList.remove('shifted');
                 document.getElementById('toggleBtn').style.display = 'block';
             }
         });
         document.querySelector('.toggle-sidebar').id = "toggleBtn";

         // كود إرسال البيانات وحفظها في القسم الجديد (قسم حفظ بيانات اليتيم)
         document.getElementById("send-save-form").addEventListener("submit", function(event) {
             event.preventDefault();
             const serverUrl = document.getElementById("server-url").value;
             // جمع البيانات من جميع الأقسام
             const data = {
                 guardian: {
                     name: document.getElementById("guardian_name").innerText,
                     id: document.getElementById("guardian_id").innerText
                 },
                 father: {
                     name: document.getElementById("father_name").innerText,
                     id: document.getElementById("father_id").innerText
                 },
                 mother: {
                     name: document.getElementById("mother_name").innerText,
                     id: document.getElementById("mother_id").innerText
                 },
                 orphan: {
                     // استخراج بيانات اليتيم الأول من القسم 5
                     name: document.querySelector("#section5 input[type='text']").value
                     // يمكن إضافة المزيد من الحقول إذا لزم الأمر
                 }
             };

             fetch(serverUrl, {
                     method: "POST",
                     headers: {
                         "Content-Type": "application/json"
                     },
                     body: JSON.stringify(data)
                 })
                 .then(response => response.json())
                 .then(result => {
                     document.getElementById("responseMessage").textContent = "تم حفظ وإرسال البيانات بنجاح";
                 })
                 .catch(error => {
                     document.getElementById("responseMessage").textContent = "حدث خطأ أثناء إرسال البيانات";
                 });
         });
     </script>
 </body>

 </html>
