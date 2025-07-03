<div class="btn-group" role="group">
    <button type="button" class="btn btn-warning btn-sm"
        data-bs-toggle="modal"
        data-bs-target="#editGeneralCategoryModal"
        data-id="{{ $row->id }}"
        data-description="{{ $row->description }}"
        data-action="{{ route('admin.GeneralCategory_name.update', $row->id) }}">
        <i class="fas fa-edit"></i>
    </button>
    <button type="button" class="btn btn-danger btn-sm"
        data-bs-toggle="modal"
        data-bs-target="#deleteGeneralCategoryModal"
        data-id="{{ $row->id }}"
        data-description="{{ $row->description }}"
        data-action="{{ route('admin.GeneralCategory_name.destroy', $row->id) }}">
        <i class="fas fa-trash"></i>
    </button>
</div>

{{-- filepath: i:\unit test\ASO\ASO - Copy\resources\views\admin\dashboard\category_management\generalcategory.blade.php --}}
{{-- ...existing code... --}}

