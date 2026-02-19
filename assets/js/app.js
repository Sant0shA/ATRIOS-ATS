/* ============================================================
   ATRIOS ATS - SHARED JAVASCRIPT
   ============================================================
   
   PURPOSE: Common JavaScript functionality across all pages
   VERSION: 1.0.0
   LAST MODIFIED: 2026-02-20
   
   FEATURES:
   - Auto-dismiss alerts
   - Mobile sidebar toggle
   - Form validation helpers
   - Confirm dialogs
   
   CHANGE LOG:
   2026-02-20: Initial JavaScript utilities
   
   ============================================================ */

(function() {
  'use strict';

  /* ============================================================
     [AUTO-DISMISS] - Auto-dismiss alerts after 5 seconds
     ============================================================ */
  
  function initAutoDismiss() {
    const alerts = document.querySelectorAll('.alert:not(.alert-permanent)');
    alerts.forEach(alert => {
      setTimeout(() => {
        const bsAlert = new bootstrap.Alert(alert);
        bsAlert.close();
      }, 5000);
    });
  }

  /* ============================================================
     [MOBILE-SIDEBAR] - Toggle sidebar on mobile
     ============================================================ */
  
  function initMobileSidebar() {
    const sidebar = document.querySelector('.sidebar');
    const toggleBtn = document.querySelector('.mobile-menu-toggle');
    
    if (toggleBtn) {
      toggleBtn.addEventListener('click', () => {
        sidebar.classList.toggle('mobile-open');
      });
    }
    
    // Close sidebar when clicking outside on mobile
    document.addEventListener('click', (e) => {
      if (window.innerWidth <= 768) {
        if (!sidebar.contains(e.target) && !toggleBtn.contains(e.target)) {
          sidebar.classList.remove('mobile-open');
        }
      }
    });
  }

  /* ============================================================
     [CONFIRM-DELETE] - Confirmation before delete actions
     ============================================================ */
  
  function initConfirmDelete() {
    const deleteButtons = document.querySelectorAll('[data-confirm-delete]');
    deleteButtons.forEach(btn => {
      btn.addEventListener('click', (e) => {
        const message = btn.dataset.confirmDelete || 'Are you sure you want to delete this item?';
        if (!confirm(message)) {
          e.preventDefault();
        }
      });
    });
  }

  /* ============================================================
     [FORM-VALIDATION] - Client-side form validation
     ============================================================ */
  
  function initFormValidation() {
    const forms = document.querySelectorAll('.needs-validation');
    forms.forEach(form => {
      form.addEventListener('submit', (e) => {
        if (!form.checkValidity()) {
          e.preventDefault();
          e.stopPropagation();
        }
        form.classList.add('was-validated');
      });
    });
  }

  /* ============================================================
     [TOOLTIPS] - Initialize Bootstrap tooltips
     ============================================================ */
  
  function initTooltips() {
    const tooltipTriggerList = [].slice.call(
      document.querySelectorAll('[data-bs-toggle="tooltip"]')
    );
    tooltipTriggerList.map(tooltipTriggerEl => {
      return new bootstrap.Tooltip(tooltipTriggerEl);
    });
  }

  /* ============================================================
     [INITIALIZE] - Run on DOM ready
     ============================================================ */
  
  document.addEventListener('DOMContentLoaded', () => {
    initAutoDismiss();
    initMobileSidebar();
    initConfirmDelete();
    initFormValidation();
    initTooltips();
    
    console.log('✅ Atrios ATS JavaScript initialized');
  });

})();
