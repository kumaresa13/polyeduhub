/**
 * PolyEduHub Main JavaScript File
 */

(function () {
  "use strict";

  // ======== sidebar toggle
  const sidebarNavWrapper = document.querySelector(".sidebar-nav-wrapper");
  const mainWrapper = document.querySelector(".main-wrapper");
  const menuToggle = document.querySelector("#menu-toggle");
  const menuClose = document.querySelector("#menu-close");

  if (menuToggle) {
    menuToggle.addEventListener("click", function () {
      sidebarNavWrapper.classList.add("active");
      mainWrapper.classList.add("active");
    });
  }

  if (menuClose) {
    menuClose.addEventListener("click", function () {
      sidebarNavWrapper.classList.remove("active");
      mainWrapper.classList.remove("active");
    });
  }

  // ======== sidebar dropdown
  const dropdownToggle = document.querySelectorAll(".nav-dropdown-toggle");
  dropdownToggle.forEach((trigger) => {
    trigger.addEventListener("click", function () {
      const parent = trigger.closest(".sidebar-nav-item");
      const submenu = parent.querySelector(".nav-dropdown");
      
      // Toggle submenu visibility
      if (submenu) {
        submenu.classList.toggle("active");
        trigger.classList.toggle("active");
      }
    });
  });

  // ======== theme switch
  const themeToggle = document.querySelector(".theme-switch");
  if (themeToggle) {
    themeToggle.addEventListener("click", function () {
      document.body.classList.toggle("dark-theme");
      localStorage.setItem("theme", document.body.classList.contains("dark-theme") ? "dark" : "light");
    });

    // Check user's previous theme preference
    const savedTheme = localStorage.getItem("theme");
    if (savedTheme === "dark") {
      document.body.classList.add("dark-theme");
    }
  }

  // ======== search bar
  const searchToggle = document.querySelector(".search-bar-toggle");
  const searchBar = document.querySelector(".search-bar");
  if (searchToggle && searchBar) {
    searchToggle.addEventListener("click", function () {
      searchBar.classList.toggle("active");
    });
  }

  // ======== form validation
  const forms = document.querySelectorAll(".needs-validation");
  forms.forEach(form => {
    form.addEventListener("submit", function (event) {
      if (!form.checkValidity()) {
        event.preventDefault();
        event.stopPropagation();
      }
      form.classList.add("was-validated");
    }, false);
  });
  
  // Initialize tooltips
  var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
  var tooltipList = tooltipTriggerList.map(function(tooltipTriggerEl) {
      return new bootstrap.Tooltip(tooltipTriggerEl);
  });

  // Initialize popovers
  var popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
  var popoverList = popoverTriggerList.map(function(popoverTriggerEl) {
      return new bootstrap.Popover(popoverTriggerEl);
  });

  // Toggle sidebar on mobile
  const toggleSidebar = document.getElementById('toggleSidebar');
  if (toggleSidebar) {
      toggleSidebar.addEventListener('click', function() {
          document.querySelector('.sidebar').classList.toggle('show');
          document.querySelector('.content').classList.toggle('pushed');
      });
  }

  // Automatically close sidebar when clicking on a link (mobile only)
  if (window.innerWidth < 992) {
      const sidebarLinks = document.querySelectorAll('.sidebar .nav-link');
      if (sidebarLinks) {
          sidebarLinks.forEach(link => {
              link.addEventListener('click', function() {
                  document.querySelector('.sidebar').classList.remove('show');
                  document.querySelector('.content').classList.remove('pushed');
              });
          });
      }
  }

  // File upload preview
  const fileInput = document.querySelector('.file-upload-input');
  if (fileInput) {
      fileInput.addEventListener('change', function() {
          const filePreview = document.querySelector('.file-preview');
          const filePreviewName = document.querySelector('.file-preview-name');
          const filePreviewSize = document.querySelector('.file-preview-size');
          const filePreviewIcon = document.querySelector('.file-preview-icon i');

          if (this.files.length > 0) {
              const file = this.files[0];
              filePreview.style.display = 'flex';
              filePreviewName.textContent = file.name;
              filePreviewSize.textContent = formatFileSize(file.size);
              
              // Set icon based on file type
              const fileExtension = file.name.split('.').pop().toLowerCase();
              switch (fileExtension) {
                  case 'pdf':
                      filePreviewIcon.className = 'fas fa-file-pdf';
                      break;
                  case 'doc':
                  case 'docx':
                      filePreviewIcon.className = 'fas fa-file-word';
                      break;
                  case 'xls':
                  case 'xlsx':
                      filePreviewIcon.className = 'fas fa-file-excel';
                      break;
                  case 'ppt':
                  case 'pptx':
                      filePreviewIcon.className = 'fas fa-file-powerpoint';
                      break;
                  case 'jpg':
                  case 'jpeg':
                  case 'png':
                  case 'gif':
                      filePreviewIcon.className = 'fas fa-file-image';
                      break;
                  case 'zip':
                  case 'rar':
                      filePreviewIcon.className = 'fas fa-file-archive';
                      break;
                  default:
                      filePreviewIcon.className = 'fas fa-file';
              }
          } else {
              filePreview.style.display = 'none';
          }
      });
  }

  // Resource search filter
  const resourceFilter = document.getElementById('resourceFilter');
  if (resourceFilter) {
      resourceFilter.addEventListener('input', function() {
          const filterValue = this.value.toLowerCase();
          const resourceItems = document.querySelectorAll('.resource-item, .resource-card');
          
          resourceItems.forEach(item => {
              const title = item.querySelector('h6, .card-title').textContent.toLowerCase();
              const meta = item.querySelector('.resource-meta, .card-text').textContent.toLowerCase();
              
              if (title.includes(filterValue) || meta.includes(filterValue)) {
                  item.style.display = '';
              } else {
                  item.style.display = 'none';
              }
          });
      });
  }

  // Category filter
  const categoryFilters = document.querySelectorAll('.category-filter');
  if (categoryFilters.length > 0) {
      categoryFilters.forEach(filter => {
          filter.addEventListener('click', function(e) {
              e.preventDefault();
              
              // Update active state
              document.querySelectorAll('.category-filter').forEach(f => {
                  f.classList.remove('active');
              });
              this.classList.add('active');
              
              const category = this.getAttribute('data-category');
              const resourceItems = document.querySelectorAll('.resource-item, .resource-card');
              
              if (category === 'all') {
                  resourceItems.forEach(item => {
                      item.style.display = '';
                  });
              } else {
                  resourceItems.forEach(item => {
                      if (item.getAttribute('data-category') === category) {
                          item.style.display = '';
                      } else {
                          item.style.display = 'none';
                      }
                  });
              }
          });
      });
  }

  // Rating system
  const ratingInputs = document.querySelectorAll('.rating-input');
  if (ratingInputs.length > 0) {
      ratingInputs.forEach(input => {
          input.addEventListener('change', function() {
              const value = this.value;
              const stars = this.closest('.rating-container').querySelectorAll('.rating-star');
              
              stars.forEach((star, index) => {
                  if (index < value) {
                      star.classList.add('active');
                  } else {
                      star.classList.remove('active');
                  }
              });
          });
      });
  }

  // Password toggle
  const passwordToggles = document.querySelectorAll('.password-toggle');
  if (passwordToggles.length > 0) {
      passwordToggles.forEach(toggle => {
          toggle.addEventListener('click', function() {
              const passwordInput = this.closest('.input-group').querySelector('input');
              const icon = this.querySelector('i');
              
              if (passwordInput.type === 'password') {
                  passwordInput.type = 'text';
                  icon.classList.remove('fa-eye');
                  icon.classList.add('fa-eye-slash');
              } else {
                  passwordInput.type = 'password';
                  icon.classList.remove('fa-eye-slash');
                  icon.classList.add('fa-eye');
              }
          });
      });
  }

  // Alert auto close
  const autoCloseAlerts = document.querySelectorAll('.alert-auto-close');
  if (autoCloseAlerts.length > 0) {
      autoCloseAlerts.forEach(alert => {
          setTimeout(() => {
              const bsAlert = new bootstrap.Alert(alert);
              bsAlert.close();
          }, 5000);
      });
  }
})();

/**
* Format file size to human-readable format
* 
* @param {number} bytes File size in bytes
* @param {number} decimals Number of decimal places
* @return {string} Formatted file size
*/
function formatFileSize(bytes, decimals = 2) {
  if (bytes === 0) return '0 Bytes';
  
  const k = 1024;
  const dm = decimals < 0 ? 0 : decimals;
  const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB'];
  
  const i = Math.floor(Math.log(bytes) / Math.log(k));
  
  return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i];
}

/**
* Show loading spinner
* 
* @param {HTMLElement} button Button element
* @param {string} originalText Original button text
*/
function showLoading(button, originalText) {
  button.disabled = true;
  button.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Loading...';
  button.setAttribute('data-original-text', originalText);
}

/**
* Hide loading spinner
* 
* @param {HTMLElement} button Button element
*/
function hideLoading(button) {
  const originalText = button.getAttribute('data-original-text');
  button.disabled = false;
  button.innerHTML = originalText;
}

/**
* Show confirmation dialog
* 
* @param {string} message Confirmation message
* @param {function} callback Callback function on confirm
*/
function confirmAction(message, callback) {
  if (confirm(message)) {
      callback();
  }
}

/**
* AJAX function for form submission
* 
* @param {HTMLFormElement} form Form element
* @param {function} successCallback Callback on success
* @param {function} errorCallback Callback on error
*/
function submitFormAjax(form, successCallback, errorCallback) {
  const formData = new FormData(form);
  const submitButton = form.querySelector('[type="submit"]');
  const originalButtonText = submitButton.innerHTML;
  
  showLoading(submitButton, originalButtonText);
  
  fetch(form.action, {
      method: form.method,
      body: formData
  })
  .then(response => response.json())
  .then(data => {
      hideLoading(submitButton);
      if (data.success) {
          if (successCallback) successCallback(data);
      } else {
          if (errorCallback) errorCallback(data);
      }
  })
  .catch(error => {
      hideLoading(submitButton);
      console.error('Error:', error);
      if (errorCallback) errorCallback({ message: 'An unexpected error occurred. Please try again.' });
  });
  
  return false;
}