(function (OC, window, $, undefined) {
    'use strict';

    var DriverManager = function () {
        this.drivers = [];
        this.baseUrl = OC.generateUrl('/apps/drivermanager/api/drivers');
        this.currentDate = new Date();
        this.selectedDate = null;
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
            
            this.initCustomDatePicker();
            this.loadDrivers();
        },

        // Initialize custom datepicker
        initCustomDatePicker: function() {
            var self = this;
            
            // Open datepicker
            $('#date-picker-btn, #license-expiry').on('click', function(e) {
                e.preventDefault();
                self.showDatePicker();
            });
            
            // Navigation
            $('#prev-month').on('click', function() {
                self.currentDate.setMonth(self.currentDate.getMonth() - 1);
                self.renderCalendar();
            });
            
            $('#next-month').on('click', function() {
                self.currentDate.setMonth(self.currentDate.getMonth() + 1);
                self.renderCalendar();
            });
            
            // Close datepicker when clicking outside
            $(document).on('click', function(e) {
                if (!$(e.target).closest('.date-input-wrapper, #custom-datepicker').length) {
                    self.hideDatePicker();
                }
            });
        },

        showDatePicker: function() {
            this.renderCalendar();
            $('#custom-datepicker').show();
        },

        hideDatePicker: function() {
            $('#custom-datepicker').hide();
        },

        renderCalendar: function() {
            var self = this;
            var year = this.currentDate.getFullYear();
            var month = this.currentDate.getMonth();
            
            // Update header
            var monthNames = ['January', 'February', 'March', 'April', 'May', 'June',
                'July', 'August', 'September', 'October', 'November', 'December'];
            $('#current-month-year').text(monthNames[month] + ' ' + year);
            
            // Get first day of month and number of days
            var firstDay = new Date(year, month, 1);
            var lastDay = new Date(year, month + 1, 0);
            var daysInMonth = lastDay.getDate();
            var startingDayOfWeek = (firstDay.getDay() + 6) % 7; // Make Monday = 0
            
            var daysContainer = $('#datepicker-days');
            daysContainer.empty();
            
            var today = new Date();
            today.setHours(0, 0, 0, 0);
            
            // Add empty cells for days before month starts
            for (var i = 0; i < startingDayOfWeek; i++) {
                var prevMonthDay = new Date(year, month, 1 - startingDayOfWeek + i);
                var dayElement = $('<div class="datepicker-day other-month">')
                    .text(prevMonthDay.getDate());
                daysContainer.append(dayElement);
            }
            
            // Add days of current month
            for (var day = 1; day <= daysInMonth; day++) {
                var currentDay = new Date(year, month, day);
                var dayElement = $('<div class="datepicker-day">')
                    .text(day)
                    .data('date', currentDay.getTime());
                
                // Add classes
                if (currentDay.getTime() === today.getTime()) {
                    dayElement.addClass('today');
                }
                
                if (currentDay < today) {
                    dayElement.addClass('past');
                } else {
                    dayElement.on('click', function() {
                        var clickedDate = new Date($(this).data('date'));
                        self.selectDate(clickedDate);
                    });
                }
                
                if (this.selectedDate && currentDay.getTime() === this.selectedDate.getTime()) {
                    dayElement.addClass('selected');
                }
                
                daysContainer.append(dayElement);
            }
            
            // Add days of next month to fill grid
            var totalCells = daysContainer.children().length;
            var remainingCells = 42 - totalCells; // 6 rows × 7 days
            for (var i = 1; i <= remainingCells && i <= 14; i++) {
                var nextMonthDay = $('<div class="datepicker-day other-month">')
                    .text(i);
                daysContainer.append(nextMonthDay);
            }
        },

        selectDate: function(date) {
            this.selectedDate = new Date(date);
            var formattedDate = this.formatDateForDisplay(date);
            $('#license-expiry').val(formattedDate);
            this.hideDatePicker();
            
            // Remove previous selected styling and add to new date
            $('.datepicker-day').removeClass('selected');
            $('.datepicker-day').each(function() {
                if ($(this).data('date') === date.getTime()) {
                    $(this).addClass('selected');
                }
            });
        },

        // Format date for display as DD/MM/YYYY
        formatDateForDisplay: function(date) {
            if (!date) return '';
            
            try {
                var day = String(date.getDate()).padStart(2, '0');
                var month = String(date.getMonth() + 1).padStart(2, '0');
                var year = date.getFullYear();
                
                return day + '/' + month + '/' + year;
            } catch (e) {
                console.warn('Date formatting error:', e);
                return '';
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
                
                return year + '-' + month + '-' + day;
            } catch (e) {
                console.warn('Date conversion error:', e);
                return '';
            }
        },

        // Validate DD/MM/YYYY format
        isValidDateFormat: function(dateString) {
            var regex = /^\d{2}\/\d{2}\/\d{4}$/;
            return regex.test(dateString);
        },

        // Get status based on days until expiry
        getStatusInfo: function(daysUntilExpiry) {
            if (daysUntilExpiry <= 0) {
                return { text: 'Expired', class: 'status-expired' };
            } else if (daysUntilExpiry <= 30) {
                return { text: 'Expiring Soon', class: 'status-warning' };
            } else {
                return { text: 'Valid', class: 'status-valid' };
            }
        },

        showForm: function(driver) {
            if (driver) {
                $('#form-title').text('Edit Driver');
                $('#driver-id').val(driver.id);
                $('#name').val(driver.name);
                $('#surname').val(driver.surname);
                $('#license-number').val(driver.licenseNumber);
                
                var displayDate = this.formatDateForDisplay(new Date(driver.licenseExpiry));
                $('#license-expiry').val(displayDate);
                this.selectedDate = new Date(driver.licenseExpiry);
            } else {
                $('#form-title').text('Add New Driver');
                $('#driver-form-element')[0].reset();
                $('#driver-id').val('');
                this.selectedDate = null;
            }
            $('#driver-form').show();
            $('#name').focus();
        },

        hideForm: function() {
            $('#driver-form').hide();
            $('#driver-form-element')[0].reset();
            this.hideDatePicker();
            this.selectedDate = null;
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
                var displayDate = self.formatDateForDisplay(expiryDate);
                
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
            }).fail(function() {
                OC.Notification.showTemporary('Error saving driver');
            });
        },

        deleteDriver: function(id) {
            var self = this;
            $.ajax({
                url: this.baseUrl + '/' + id,
                method: 'DELETE'
            }).done(function() {
                self.loadDrivers();
                OC.Notification.showTemporary('Driver deleted successfully');
            }).fail(function() {
                OC.Notification.showTemporary('Error deleting driver');
            });
        }
    };

    $(document).ready(function () {
        new DriverManager();
    });

})(OC, window, jQuery);