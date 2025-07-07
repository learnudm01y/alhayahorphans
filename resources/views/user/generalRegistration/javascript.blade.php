@include('user.generalRegistration.javascript.taps')
@include('user.generalRegistration.javascript.manageDaedTap')
{{-- تحميل أداة القص مبكراً قبل documentUpload --}}
@include('user.generalRegistration.javascript.documentUpload')
@include('user.generalRegistration.javascript.cropper')
@include('user.generalRegistration.javascript.ageCalculating')
@include('user.generalRegistration.javascript.errorTracker')
@include('user.generalRegistration.javascript.autoComplete')
@include('user.generalRegistration.javascript.showInsertedData')
@include('user.generalRegistration.javascript.manageForm')


