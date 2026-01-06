<div class="btn-group" role="group">
    <button type="button" class="btn btn-warning btn-sm"
        data-bs-toggle="modal"
        data-bs-target="#editCreativityAspectModal"
        data-id="{{ $row->id }}"
        data-name="{{ $row->description }}"
        data-action="{{ route('admin.creativity_aspects.update', $row->id) }}">
        <i class="fas fa-edit"></i>
    </button>
    <button type="button" class="btn btn-danger btn-sm"
        data-bs-toggle="modal"
        data-bs-target="#deleteCreativityAspectModal"
        data-id="{{ $row->id }}"
        data-name="{{ $row->description }}"
        data-action="{{ route('admin.creativity_aspects.destroy', $row->id) }}">
        <i class="fas fa-trash"></i>
    </button>
</div>
