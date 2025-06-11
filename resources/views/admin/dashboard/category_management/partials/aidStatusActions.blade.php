<div class="btn-group" role="group">
    <button type="button" class="btn btn-warning btn-sm"
        data-bs-toggle="modal"
        data-bs-target="#editAidStatusModal"
        data-id="{{ $row->id }}"
        data-description="{{ $row->description }}"
        data-action="{{ route('admin.aid_status.update', $row->id) }}">
        <i class="fas fa-edit"></i>
    </button>
    <button type="button" class="btn btn-danger btn-sm"
        data-bs-toggle="modal"
        data-bs-target="#deleteAidStatusModal"
        data-id="{{ $row->id }}"
        data-description="{{ $row->description }}"
        data-action="{{ route('admin.aid_status.destroy', $row->id) }}">
        <i class="fas fa-trash"></i>
    </button>
</div>
