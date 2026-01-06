<div class="btn-group" role="group">
    <button type="button" class="btn btn-warning btn-sm"
        data-bs-toggle="modal"
        data-bs-target="#editOrphanNeedModal"
        data-id="{{ $row->id }}"
        data-name="{{ $row->description }}"
        data-action="{{ route('admin.orphan_needs.update', $row->id) }}">
        <i class="fas fa-edit"></i>
    </button>
    <button type="button" class="btn btn-danger btn-sm"
        data-bs-toggle="modal"
        data-bs-target="#deleteOrphanNeedModal"
        data-id="{{ $row->id }}"
        data-name="{{ $row->description }}"
        data-action="{{ route('admin.orphan_needs.destroy', $row->id) }}">
        <i class="fas fa-trash"></i>
    </button>
</div>
