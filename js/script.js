(function (OC, window, $, undefined) {
    'use strict';

    var DriverManager = function () {
        this.drivers = [];
        this.baseUrl = OC.generateUrl('/apps/drivermanager/api/drivers');
        this.datePicker = null;
        this.init();
    };

    DriverManager.prototype = {
        init: function () {
            var self = this;
            
            $('#new-driver-btn').on('click', function() {
                self.showForm();
            });
            
            $('#cancel-btn').on('click', function() {
                self.hideForm();
            });
            
            $('#driver-form-element').on('submit', function(e) {
                e.preventDefault();
                self.saveDriver();
            });
            
            // Initialize datepicker when DOM is ready
            this.initDatePicker();
            
            this.loadDrivers();
        },

        // Initialize Flatpickr datepicker
        initDatePicker: function() {
            var self = this;
            
            // Wait for Flatpickr to be available
            if (typeof flatpickr !== 'undefined') {
                this.setupDatePicker();
            } else {
                // Retry after a short delay
                setTimeout(function() {
                    self.initDatePicker();
                }, 100);
            }
        },

        setupDatePicker: function() {
            var self = this;
            
            this.datePicker = flatpickr("#license-expiry", {
                dateFormat: "d/m/Y", // DD/MM/YYYY format
                allowInput: true,
                clickOpens: false, // We'll control opening with the button
                locale: {
                    firstDayOfWeek: 1 // Start week on Monday
                },
                minDate: "today", // Prevent selecting past dates
                onChange: function(selectedDates, dateStr, instance) {
                    // Date is automatically formatted as DD/MM/YYYY
                    console.log('Date selected:', dateStr);
                }
            });
            
            // Open datepicker when button is clicked
            $('#date-picker-btn').on('click', function(e) {
                e.preventDefault();
                if (self.datePicker) {
                    self.datePicker.open();
                }
            });
            
            // Also open when clicking the input
            $('#license-expiry').on('click', function() {
                if (self.datePicker) {
                    self.datePicker.open();
                }
            });
        },

        // Format date for display as DD/MM/YYYY
        formatDateForDisplay: function(dateString) {
            if (!dateString) return '';
            
            try {
                var date = new Date(dateString);
                var day = String(date.getDate()).padStart(2, '0');
                var month = String(date.getMonth() + 1).padStart(2, '0');
                var year = date.getFullYear();
                
                return day + '/' + month + '/' + year;
            } catch (e) {
                console.warn('Date formatting error:', e);
                return dateString;
            }
        },

        // Convert DD/MM/YYYY to YYYY-MM-DD for backend
        convertToBackendFormat: function(ddmmyyyy) {
            if (!ddmmyyyy) return '';
            
            try {
                var parts = ddmmyyyy.split('/');
                if (parts.length !== 3) return '';
                
                var day = parts[0].padStart(2, '0');
                var month = parts[1].padStart(2, '0');
                var year = parts[2];
                
                // Validate the date
                var date = new Date(year, month - 1, day);
                if (date.getFullYear() != year || date.getMonth() != month - 1 || date.getDate() != day) {
                    throw new Error('Invalid date');
                }
                
                return year + '-' + month + '-' + day;
            } catch (e) {
                console.warn('Date conversion error:', e);
                return '';
            }
        },

        // Validate DD/MM/YYYY format
        isValidDateFormat: function(dateString) {
            var regex = /^\d{2}\/\d{2}\/\d{4}$/;
            if (!regex.test(dateString)) return false;
            
            var parts = dateString.split('/');
            var day = parseInt(parts[0], 10);
            var month = parseInt(parts[1], 10);
            var year = parseInt(parts[2], 10);
            
            // Basic validation
            if (month < 1 || month > 12) return false;
            if (day < 1 || day > 31) return false;
            if (year < 1900 || year > 2100) return false;
            
            // Check if date is valid
            var date = new Date(year, month - 1, day);
            return date.getFullYear() === year && 
                   date.getMonth() === month - 1 && 
                   date.getDate() === day;
        },

        // Get status based on days until expiry
        getStatusInfo: function(daysUntilExpiry) {
            if (daysUntilExpiry <= 0) {
                return {
                    text: 'Expired',
                    class: 'status-expired'
                };
            } else if (daysUntilExpiry <= 30) {
                return {
                    text: 'Expiring Soon',
                    class: 'status-warning'
                };
            } else {
                return {
                    text: 'Valid',
                    class: 'status-valid'
                };
            }
        },

        showForm: function(driver) {
            if (driver) {
                $('#form-title').text('Edit Driver');
                $('#driver-id').val(driver.id);
                $('#name').val(driver.name);
                $('#surname').val(driver.surname);
                $('#license-number').val(driver.licenseNumber);
                
                // Set the datepicker value
                var displayDate = this.formatDateForDisplay(driver.licenseExpiry);
                $('#license-expiry').val(displayDate);
                
                // Update datepicker if available
                if (this.datePicker) {
                    var backendDate = new Date(driver.licenseExpiry);
                    this.datePicker.setDate(backendDate, false);
                }
            } else {
                $('#form-title').text('Add New Driver');
                $('#driver-form-element')[0].reset();
                $('#driver-id').val('');
                
                // Clear datepicker
                if (this.datePicker) {
                    this.datePicker.clear();
                }
            }
            $('#driver-form').show();
            $('#name').focus();
        },

        hideForm: function() {
            $('#driver-form').hide();
            $('#driver-form-element')[0].reset();
            if (this.datePicker) {
                this.datePicker.clear();
            }
        },

        loadDrivers: function() {
            var self = this;
            $.get(this.baseUrl).done(function(drivers) {
                self.drivers = drivers;
                self.renderDrivers();
            }).fail(function() {
                OC.Notification.showTemporary('Failed to load drivers');
            });
        },

        renderDrivers: function() {
            var self = this;
            var tbody = $('#drivers-table tbody');
            tbody.empty();
            
            this.drivers.forEach(function(driver) {
                var expiryDate = new Date(driver.licenseExpiry);
                var today = new Date();
                var daysUntilExpiry = Math.ceil((expiryDate - today) / (1000 * 60 * 60 * 24));
                
                var statusInfo = self.getStatusInfo(daysUntilExpiry);
                var displayDate = self.formatDateForDisplay(driver.licenseExpiry);
                
                var row = $('<tr>');
                row.append($('<td>').text(driver.name));
                row.append($('<td>').text(driver.surname));
                row.append($('<td>').text(driver.licenseNumber));
                row.append($('<td>').text(displayDate));
                row.append($('<td>').html('<span class="status ' + statusInfo.class + '">' + statusInfo.text + '</span>'));
                row.append($('<td>').html(
                    '<button class="edit-btn" data-id="' + driver.id + '">Edit</button> ' +
                    '<button class="delete-btn" data-id="' + driver.id + '">Delete</button>'
                ));
                
                tbody.append(row);
            });
            
            $('.edit-btn').off('click').on('click', function() {
                var driverId = $(this).data('id');
                var driver = self.drivers.find(d => d.id == driverId);
                if (driver) {
                    self.showForm(driver);
                } else {
                    OC.Notification.showTemporary('Driver not found');
                }
            });
            
            $('.delete-btn').off('click').on('click', function() {
                var driverId = $(this).data('id');
                if (driverId && confirm('Are you sure you want to delete this driver?')) {
                    self.deleteDriver(driverId);
                }
            });
        },

        saveDriver: function() {
            var expiryDate = $('#license-expiry').val();
            
            // Validate date format
            if (!this.isValidDateFormat(expiryDate)) {
                OC.Notification.showTemporary('Please select a valid expiry date');
                return;
            }
            
            var formData = {
                name: $('#name').val(),
                surname: $('#surname').val(),
                licenseNumber: $('#license-number').val(),
                licenseExpiry: this.convertToBackendFormat(expiryDate)
            };
            
            // Basic validation
            if (!formData.name || !formData.surname || !formData.licenseNumber || !formData.licenseExpiry) {
                OC.Notification.showTemporary('Please fill in all fields correctly');
                return;
            }
            
            var driverId = $('#driver-id').val();
            var url = driverId ? this.baseUrl + '/' + driverId : this.baseUrl;
            var method = driverId ? 'PUT' : 'POST';
            
            var self = this;
            $.ajax({
                url: url,
                method: method,
                data: formData
            }).done(function() {
                self.hideForm();
                self.loadDrivers();
                OC.Notification.showTemporary('Driver saved successfully');
            }).fail(function(xhr) {
                var errorMsg = 'Error saving driver';
                try {
                    var response = JSON.parse(xhr.responseText);
                    if (response.error) {
                        errorMsg += ': ' + response.error;
                    }
                } catch (e) {
                    // Use default error message
                }
                OC.Notification.showTemporary(errorMsg);
            });
        },

        deleteDriver: function(id) {
            if (!id) {
                OC.Notification.showTemporary('Invalid driver ID');
                return;
            }
            
            var self = this;
            $.ajax({
                url: this.baseUrl + '/' + id,
                method: 'DELETE'
            }).done(function() {
                self.loadDrivers();
                OC.Notification.showTemporary('Driver deleted successfully');
            }).fail(function(xhr) {
                var errorMsg = 'Error deleting driver';
                try {
                    var response = JSON.parse(xhr.responseText);
                    if (response.error) {
                        errorMsg += ': ' + response.error;
                    }
                } catch (e) {
                    // Use default error message
                }
                OC.Notification.showTemporary(errorMsg);
            });
        }
    };

    $(document).ready(function () {
        new DriverManager();
    });

})(OC, window, jQuery);