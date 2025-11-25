<!-- Add Department Modal -->
<div class="modal fade" id="addDeptModal" tabindex="-1" aria-labelledby="addDeptModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="../resources/utilities/functions/department_operation.php">
                <div class="modal-header">
                    <h5 class="modal-title" id="addDeptModalLabel">Add Dept/Org</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="department_name" class="form-label">Dept/Org Name</label>
                        <input type="text" class="form-control" id="department_name" name="department_name" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <input type="hidden" name="action" value="add">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Add Dept/Org</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Department Modal -->
<div class="modal fade" id="editDeptModal" tabindex="-1" aria-labelledby="editDeptModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="../resources/utilities/functions/department_operation.php">
                <div class="modal-header">
                    <h5 class="modal-title" id="editDeptModalLabel">Edit Dept/Org</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="department_id_edit" class="form-label">Select Dept/Org</label>
                        <select id="department_id_edit" name="department_id" class="form-select" required>
                            <?php foreach ($departments as $dept): ?>
                                <option value="<?php echo $dept['id']; ?>"><?php echo $dept['department']; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="new_department_name" class="form-label">New Dept/Org Name</label>
                        <input type="text" class="form-control" id="new_department_name" name="new_department_name" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <input type="hidden" name="action" value="edit">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Edit Dept/Org</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Department Modal -->
<div class="modal fade" id="deleteDeptModal" tabindex="-1" aria-labelledby="deleteDeptModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="../resources/utilities/functions/department_operation.php">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteDeptModalLabel">Delete Dept/Org</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="department_id_delete" class="form-label">Select Dept/Org</label>
                        <select id="department_id_delete" name="department_id" class="form-select" required>
                            <?php foreach ($departments as $dept): ?>
                                <option value="<?php echo $dept['id']; ?>"><?php echo $dept['department']; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <input type="hidden" name="action" value="delete">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-danger">Delete Dept/Org</button>
                </div>
            </form>
        </div>
    </div>
</div>
