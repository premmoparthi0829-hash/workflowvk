class TaskManager {
    constructor() {
        this.apiBase = '../api/';
        this.csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
        this.init();
    }
    
    init() {
        this.bindEvents();
    }
    
    bindEvents() {
        // Create task form
        $(document).on('submit', '#createTaskForm', (e) => this.handleCreateTask(e));
        
        // Update status
        $(document).on('click', '.update-status', (e) => this.showStatusModal(e));
        
        // Accept task
        $(document).on('click', '.accept-task', (e) => this.acceptTask(e));
        
        // Reject task
        $(document).on('click', '.reject-task', (e) => this.showRejectModal(e));
        
        // View task details
        $(document).on('click', '.view-task-details', (e) => this.viewTaskDetails(e));
    }
    
    async handleCreateTask(e) {
        e.preventDefault();
        const form = e.target;
        const formData = new FormData(form);
        
        const taskData = {
            title: formData.get('title'),
            description: formData.get('description'),
            priority: formData.get('priority'),
            deadline: formData.get('deadline'),
            assign_to: formData.get('assign_to')
        };
        
        this.showLoading();
        
        try {
            const response = await this.ajaxRequest('tasks.php?action=create', 'POST', taskData);
            
            if(response.success) {
                this.showAlert('success', response.message);
                $('#createTaskModal').modal('hide');
                form.reset();
                setTimeout(() => location.reload(), 1500);
            } else {
                this.showAlert('danger', response.message);
            }
        } catch(error) {
            this.showAlert('danger', 'Error creating task: ' + error);
        } finally {
            this.hideLoading();
        }
    }
    
    async updateTaskStatus(taskId, status) {
        if(!confirm('Are you sure you want to update the task status?')) return;
        
        this.showLoading();
        
        try {
            const response = await this.ajaxRequest('tasks.php?action=update_status', 'POST', {
                task_id: taskId,
                status: status
            });
            
            if(response.success) {
                this.showAlert('success', response.message);
                setTimeout(() => location.reload(), 1000);
            } else {
                this.showAlert('danger', response.message);
            }
        } catch(error) {
            this.showAlert('danger', 'Error updating status');
        } finally {
            this.hideLoading();
        }
    }
    
    async acceptTask(e) {
        const assignmentId = $(e.target).data('id');
        
        if(!confirm('Are you sure you want to accept this task?')) return;
        
        this.showLoading();
        
        try {
            const response = await this.ajaxRequest('tasks.php?action=accept', 'POST', {
                assignment_id: assignmentId
            });
            
            if(response.success) {
                this.showAlert('success', response.message);
                setTimeout(() => location.reload(), 1000);
            } else {
                this.showAlert('danger', response.message);
            }
        } catch(error) {
            this.showAlert('danger', 'Error accepting task');
        } finally {
            this.hideLoading();
        }
    }
    
    async rejectTask(assignmentId, reason) {
        this.showLoading();
        
        try {
            const response = await this.ajaxRequest('tasks.php?action=reject', 'POST', {
                assignment_id: assignmentId,
                reason: reason
            });
            
            if(response.success) {
                this.showAlert('success', response.message);
                setTimeout(() => location.reload(), 1000);
            } else {
                this.showAlert('danger', response.message);
            }
        } catch(error) {
            this.showAlert('danger', 'Error rejecting task');
        } finally {
            this.hideLoading();
        }
    }
    
    showStatusModal(e) {
        const taskId = $(e.target).data('id');
        const currentStatus = $(e.target).data('status');
        
        const modalHtml = `
            <div class="modal fade" id="statusModal" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Update Task Status</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <select class="form-control" id="newStatus">
                                <option value="not_started" ${currentStatus === 'not_started' ? 'selected' : ''}>Not Started</option>
                                <option value="in_progress" ${currentStatus === 'in_progress' ? 'selected' : ''}>In Progress</option>
                                <option value="waiting_review" ${currentStatus === 'waiting_review' ? 'selected' : ''}>Waiting for Review</option>
                                <option value="completed" ${currentStatus === 'completed' ? 'selected' : ''}>Completed</option>
                            </select>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="button" class="btn btn-primary" onclick="taskManager.confirmStatusUpdate(${taskId})">Update Status</button>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        $('body').append(modalHtml);
        $('#statusModal').modal('show');
        
        $('#statusModal').on('hidden.bs.modal', function() {
            $(this).remove();
        });
    }
    
    confirmStatusUpdate(taskId) {
        const newStatus = $('#newStatus').val();
        this.updateTaskStatus(taskId, newStatus);
        $('#statusModal').modal('hide');
    }
    
    showRejectModal(e) {
        const assignmentId = $(e.target).data('id');
        
        const modalHtml = `
            <div class="modal fade" id="rejectModal" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Reject Task</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label">Reason for Rejection *</label>
                                <textarea class="form-control" id="rejectReason" rows="4" placeholder="Please provide a reason for rejecting this task..." required></textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="button" class="btn btn-danger" onclick="taskManager.confirmReject(${assignmentId})">Submit Rejection</button>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        $('body').append(modalHtml);
        $('#rejectModal').modal('show');
    }
    
    confirmReject(assignmentId) {
        const reason = $('#rejectReason').val().trim();
        
        if(!reason) {
            this.showAlert('warning', 'Please provide a reason for rejection');
            return;
        }
        
        if(confirm('Are you sure you want to reject this task?')) {
            this.rejectTask(assignmentId, reason);
            $('#rejectModal').modal('hide');
        }
    }
    
    async viewTaskDetails(e) {
        const taskId = $(e.target).data('id');
        
        try {
            const response = await this.ajaxRequest('tasks.php?action=get_details&id=' + taskId);
            
            if(response.success) {
                $('#taskDetailsContent').html(response.html);
                $('#taskDetailsModal').modal('show');
            } else {
                this.showAlert('danger', response.message);
            }
        } catch(error) {
            this.showAlert('danger', 'Error loading task details');
        }
    }
    
    async ajaxRequest(endpoint, method = 'GET', data = null) {
        const url = this.apiBase + endpoint;
        const options = {
            method: method,
            headers: {
                'Content-Type': 'application/json'
            }
        };
        
        if(data) {
            options.body = JSON.stringify(data);
        }
        
        const response = await fetch(url, options);
        return await response.json();
    }
    
    showAlert(type, message) {
        // Remove existing alerts
        $('.alert-dismissible').remove();
        
        const alertHtml = `
            <div class="alert alert-${type} alert-dismissible fade show" role="alert">
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        `;
        
        $('.container-fluid').prepend(alertHtml);
        
        setTimeout(() => {
            $('.alert-dismissible').fadeOut();
        }, 5000);
    }
    
    showLoading() {
        // Create loading overlay if it doesn't exist
        if(!$('#loadingOverlay').length) {
            $('body').append(`
                <div id="loadingOverlay" style="position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);z-index:9999;display:flex;align-items:center;justify-content:center;">
                    <div class="spinner-border text-light" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
            `);
        }
    }
    
    hideLoading() {
        $('#loadingOverlay').remove();
    }
}

// Initialize TaskManager when document is ready
$(document).ready(function() {
    window.taskManager = new TaskManager();
});