(function (OC, window, $, undefined) {
    'use strict';

    var DriverManager = function () {
        this.drivers = [];
        this.baseUrl = OC.generateUrl('/apps/drivermanager/api/drivers');
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
            
            this.loadDrivers();
        },

        // Format date for display as DD/MM/YYYY
        formatDateForDisplay: function(dateString) {
            if (!dateString) return '';
            
            try {
                var date = new Date(dateString);
                var day = String(date.getDate()).padStart(2, '0');
                var month = String(date.getMonth() + 1).padStart(2, '0'); // +1 because months are 0-indexed
                var year = date.getFullYear();
                
                return day + '/' + month + '/' + year; // DD/MM/YYYY
            } catch (e) {
                console.warn('Date formatting error:', e);
                return dateString; // Fallback to original string
            }
        },

        // Convert date from DD/MM/YYYY or any format back to YYYY-MM-DD for form input
        formatDateForInput: function(dateString) {
            if (!dateString) return '';
            
            try {
                var date = new Date(dateString);
                return date.toISOString().split('T')[0]; // Returns YYYY-MM-DD
            } catch (e) {
                console.warn('Date input formatting error:', e);
                return dateString;
            }
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
                // Convert to YYYY-MM-DD format for date input
                $('#license-expiry').val(this.formatDateForInput(driver.licenseExpiry));
            } else {
                $('#form-title').text('Add New Driver');
                $('#driver-form-element')[0].reset();
                $('#driver-id').val('');
            }
            $('#driver-form').show();
        },

        hideForm: function() {
            $('#driver-form').hide();
            $('#driver-form-element')[0].reset();
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
                
                // Format the expiry date for display as DD/MM/YYYY
                var displayDate = self.formatDateForDisplay(driver.licenseExpiry);
                
                var row = $('<tr>');
                row.append($('<td>').text(driver.name));
                row.append($('<td>').text(driver.surname));
                row.append($('<td>').text(driver.licenseNumber));
                row.append($('<td>').text(displayDate)); // DD/MM/YYYY format
                row.append($('<td>').html('<span class="status ' + statusInfo.class + '">' + statusInfo.text + '</span>'));
                row.append($('<td>').html(
                    '<button class="edit-btn" data-id="' + driver.id + '">Edit</button> ' +
                    '<button class="delete-btn" data-id="' + driver.id + '">Delete</button>'
                ));
                
                tbody.append(row);
            });
            
            // Remove previous event handlers to prevent duplicates
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
            var formData = {
                name: $('#name').val(),
                surname: $('#surname').val(),
                licenseNumber: $('#license-number').val(),
                licenseExpiry: $('#license-expiry').val() // This is already in YYYY-MM-DD format from input
            };
            
            // Basic validation
            if (!formData.name || !formData.surname || !formData.licenseNumber || !formData.licenseExpiry) {
                OC.Notification.showTemporary('Please fill in all fields');
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