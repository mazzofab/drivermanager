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

        showForm: function(driver) {
            if (driver) {
                $('#form-title').text('Edit Driver');
                $('#driver-id').val(driver.id);
                $('#name').val(driver.name);
                $('#surname').val(driver.surname);
                $('#license-number').val(driver.licenseNumber);
                $('#license-expiry').val(driver.licenseExpiry);
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
            }).fail(function(xhr, status, error) {
                console.error('Failed to load drivers:', error);
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
                
                var status = 'Valid';
                var statusClass = 'status-valid';
                
                if (daysUntilExpiry < 0) {
                    status = 'Expired';
                    statusClass = 'status-expired';
                } else if (daysUntilExpiry <= 30) {
                    status = 'Expiring Soon';
                    statusClass = 'status-warning';
                }
                
                var row = $('<tr>');
                row.append($('<td>').text(driver.name));
                row.append($('<td>').text(driver.surname));
                row.append($('<td>').text(driver.licenseNumber));
                row.append($('<td>').text(driver.licenseExpiry));
                row.append($('<td>').html('<span class="status ' + statusClass + '">' + status + '</span>'));
                row.append($('<td>').html(
                    '<button class="edit-btn" data-id="' + driver.id + '">Edit</button> ' +
                    '<button class="delete-btn" data-id="' + driver.id + '">Delete</button>'
                ));
                
                tbody.append(row);
            });
            
            // Edit button handler
            $('.edit-btn').off('click').on('click', function() {
                var driverId = parseInt($(this).data('id'));
                var driver = self.drivers.find(function(d) { 
                    return parseInt(d.id) === driverId; 
                });
                
                if (driver) {
                    self.showForm(driver);
                } else {
                    OC.Notification.showTemporary('Error: Driver not found');
                }
            });
            
            // Delete button handler - FIXED
            $('.delete-btn').off('click').on('click', function() {
                var driverId = parseInt($(this).data('id'));
                
                if (!driverId || isNaN(driverId)) {
                    console.error('Invalid driver ID for delete:', $(this).data('id'));
                    OC.Notification.showTemporary('Error: Invalid driver ID');
                    return;
                }
                
                if (confirm('Are you sure you want to delete this driver?')) {
                    self.deleteDriver(driverId);
                }
            });
        },

        saveDriver: function() {
            var formData = {
                name: $('#name').val(),
                surname: $('#surname').val(),
                licenseNumber: $('#license-number').val(),
                licenseExpiry: $('#license-expiry').val()
            };
            
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
            }).fail(function(xhr, status, error) {
                OC.Notification.showTemporary('Error saving driver');
            });
        },

        deleteDriver: function(id) {
            if (!id || isNaN(id)) {
                console.error('Invalid ID passed to deleteDriver:', id);
                OC.Notification.showTemporary('Error: Invalid driver ID');
                return;
            }
            
            var self = this;
            $.ajax({
                url: this.baseUrl + '/' + id,
                method: 'DELETE'
            }).done(function() {
                self.loadDrivers();
                OC.Notification.showTemporary('Driver deleted successfully');
            }).fail(function(xhr, status, error) {
                OC.Notification.showTemporary('Error deleting driver');
            });
        }
    };

    $(document).ready(function () {
        new DriverManager();
    });

})(OC, window, jQuery);