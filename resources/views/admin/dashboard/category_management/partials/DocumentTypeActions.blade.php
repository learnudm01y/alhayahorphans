<div class="btn-group" role="group">
    <button type="button" class="btn btn-warning btn-sm"
        data-bs-toggle="modal"
        data-bs-target="#editDocumentTypeModal"
        data-id="{{ $row->id }}"
        data-description="{{ $row->description }}"
        data-action="{{ route('admin.DocumentType_name.update', $row->id) }}">
        <i class="fas fa-edit"></i>
    </button>
    <button type="button" class="btn btn-danger btn-sm"
        data-bs-toggle="modal"
        data-bs-target="#deleteDocumentTypeModal"
        data-id="{{ $row->id }}"
        data-description="{{ $row->description }}"
        data-action="{{ route('admin.DocumentType_name.destroy', $row->id) }}">
        <i class="fas fa-trash"></i>
    </button>
</div>
