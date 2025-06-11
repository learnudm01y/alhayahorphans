<div class="btn-group" role="group">
    <button type="button" class="btn btn-warning btn-sm"
        data-bs-toggle="modal"
        data-bs-target="#editCurrencyTypeModal"
        data-id="{{ $row->id }}"
        data-description="{{ $row->description }}"
        data-action="{{ route('admin.CurrencyType_name.update', $row->id) }}">
        <i class="fas fa-edit"></i>
    </button>
    <button type="button" class="btn btn-danger btn-sm"
        data-bs-toggle="modal"
        data-bs-target="#deleteCurrencyTypeModal"
        data-id="{{ $row->id }}"
        data-description="{{ $row->description }}"
        data-action="{{ route('admin.CurrencyType_name.destroy', $row->id) }}">
        <i class="fas fa-trash"></i>
    </button>
</div>
