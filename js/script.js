(function (OC, window, $, undefined) {
    'use strict';

    var DriverManager = function () {
        this.drivers = [];
        this.filteredDrivers = [];
        this.baseUrl = OC.generateUrl('/apps/drivermanager/api/drivers');
        this.testNotificationUrl = OC.generateUrl('/apps/drivermanager/api/test-notification');
        this.currentDate = new Date();
        this.selectedDate = null;
        
        // Pagination settings
        this.currentPage = 1;
        this.pageSize = 25;
        this.totalDrivers = 0;
        this.searchQuery = '';
        
        this.init();
    };

    DriverManager.prototype = {
        init: function () {
            var self = this;
            
            $('#new-driver-btn').on('click', function() {
                self.showForm();
            });
            
            $('#test-notification-btn').on('click', function() {
                self.testNotifications();
            });
            
            $('#cancel-btn').on('click', function() {
                self.hideForm();
            });
            
            $('#driver-form-element').on('submit', function(e) {
                e.preventDefault();
                self.saveDriver();
            });
            
            // Search functionality
            this.initSearch();
            
            // Pagination functionality
            this.initPagination();
            
            // Input formatting and datepicker
            this.initInputFormatting();
            this.initCustomDatePicker();
            
            this.loadDrivers();
        },

        // Test notification system
        testNotifications: function() {
            var self = this;
            var $button = $('#test-notification-btn');
            var originalText = $button.text();
            
            // Disable button and show loading state
            $button.prop('disabled', true).text('Sending test...');
            
            $.ajax({
                url: this.testNotificationUrl,
                method: 'POST',
                dataType: 'json'
            }).done(function(response) {
                if (response.success) {
                    OC.Notification.showTemporary(response.message || 'Test notifications sent successfully!');
                    
                    // Show additional info if available
                    if (response.timestamp) {
                        console.log('Test notification sent at:', response.timestamp);
                    }
                } else {
                    OC.Notification.showTemporary(response.message || 'Test completed');
                }
            }).fail(function(xhr) {
                var errorMsg = 'Failed to send test notifications';
                try {
                    var response = JSON.parse(xhr.responseText);
                    if (response.error) {
                        errorMsg = response.error;
                    }
                } catch (e) {
                    // Use default error message
                }
                OC.Notification.showTemporary(errorMsg);
            }).always(function() {
                // Re-enable button and restore text
                $button.prop('disabled', false).text(originalText);
            });
        },

        // Initialize search functionality
        initSearch: function() {
            var self = this;
            var searchTimeout;
            
            $('#search-input').on('input', function() {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(function() {
                    self.performSearch();
                }, 300); // Debounce search for 300ms
            });
            
            $('#search-btn').on('click', function() {
                self.performSearch();
            });
            
            $('#clear-search-btn').on('click', function() {
                self.clearSearch();
            });
            
            // Enter key to search
            $('#search-input').on('keypress', function(e) {
                if (e.which === 13) {
                    self.performSearch();
                }
            });
        },

        // Initialize pagination functionality
        initPagination: function() {
            var self = this;
            
            $('#first-page').on('click', function() {
                self.goToPage(1);
            });
            
            $('#prev-page').on('click', function() {
                self.goToPage(self.currentPage - 1);
            });
            
            $('#next-page').on('click', function() {
                self.goToPage(self.currentPage + 1);
            });
            
            $('#last-page').on('click', function() {
                var totalPages = Math.ceil(self.filteredDrivers.length / self.pageSize);
                self.goToPage(totalPages);
            });
            
            $('#page-size').on('change', function() {
                self.pageSize = parseInt($(this).val());
                self.currentPage = 1;
                self.renderDrivers();
                self.updatePaginationInfo();
            });
        },

        // Perform search
        performSearch: function() {
            this.searchQuery = $('#search-input').val().trim().toLowerCase();
            this.currentPage = 1;
            this.filterAndRenderDrivers();
            
            // Show/hide clear button
            if (this.searchQuery) {
                $('#clear-search-btn').show();
            } else {
                $('#clear-search-btn').hide();
            }
        },

        // Clear search
        clearSearch: function() {
            $('#search-input').val('');
            this.searchQuery = '';
            this.currentPage = 1;
            this.filterAndRenderDrivers();
            $('#clear-search-btn').hide();
        },

        // Filter drivers based on search query
        filterDrivers: function() {
            if (!this.searchQuery) {
                this.filteredDrivers = this.drivers.slice();
                return;
            }
            
            this.filteredDrivers = this.drivers.filter(function(driver) {
                var name = (driver.name || '').toLowerCase();
                var surname = (driver.surname || '').toLowerCase();
                var licenseNumber = (driver.licenseNumber || '').toLowerCase();
                
                return name.includes(this.searchQuery) || 
                       surname.includes(this.searchQuery) || 
                       licenseNumber.includes(this.searchQuery);
            }.bind(this));
        },

        // Filter and render drivers
        filterAndRenderDrivers: function() {
            this.filterDrivers();
            this.renderDrivers();
            this.updatePaginationInfo();
            this.updateResultsCount();
        },

        // Go to specific page
        goToPage: function(page) {
            var totalPages = Math.ceil(this.filteredDrivers.length / this.pageSize);
            
            if (page < 1 || page > totalPages) return;
            
            this.currentPage = page;
            this.renderDrivers();
            this.updatePaginationInfo();
        },

        // Update pagination controls
        updatePaginationInfo: function() {
            var totalPages = Math.ceil(this.filteredDrivers.length / this.pageSize);
            
            $('#page-info').text('Page ' + this.currentPage + ' of ' + totalPages);
            
            // Update button states
            $('#first-page, #prev-page').prop('disabled', this.currentPage === 1);
            $('#next-page, #last-page').prop('disabled', this.currentPage === totalPages || totalPages === 0);
        },

        // Update results count
        updateResultsCount: function() {
            var total = this.filteredDrivers.length;
            var start = (this.currentPage - 1) * this.pageSize + 1;
            var end = Math.min(this.currentPage * this.pageSize, total);
            
            var text = '';
            if (total === 0) {
                text = 'No drivers found';
            } else if (this.searchQuery) {
                text = 'Showing ' + start + '-' + end + ' of ' + total + ' drivers (filtered)';
            } else {
                text = 'Showing ' + start + '-' + end + ' of ' + total + ' drivers';
            }
            
            $('#results-count').text(text);
        },

        // Get paginated drivers for current page
        getPaginatedDrivers: function() {
            var start = (this.currentPage - 1) * this.pageSize;
            var end = start + this.pageSize;
            return this.filteredDrivers.slice(start, end);
        },

        // Initialize input formatting for auto-capitalization
        initInputFormatting: function() {
            // Capitalize first letter of each word for names
            $('#name, #surname').on('input', function() {
                var value = $(this).val();
                var capitalized = value.replace(/\b\w+/g, function(word) {
                    return word.charAt(0).toUpperCase() + word.slice(1).toLowerCase();
                });
                $(this).val(capitalized);
            });

            // Convert license number to uppercase
            $('#license-number').on('input', function() {
                var value = $(this).val().toUpperCase();
                $(this).val(value);
            });

            // Allow alphanumeric characters only for license number
            $('#license-number').on('keypress', function(e) {
                var char = String.fromCharCode(e.which);
                if (!/[a-zA-Z0-9]/.test(char)) {
                    e.preventDefault();
                }
            });

            // Limit license number length
            $('#license-number').on('input', function() {
                var value = $(this).val();
                if (value.length > 20) {
                    $(this).val(value.substring(0, 20));
                }
            });
        },

        // Sanitize and format data before saving
        sanitizeFormData: function() {
            var name = $('#name').val().trim();
            var surname = $('#surname').val().trim();
            var licenseNumber = $('#license-number').val().trim();

            return {
                name: this.capitalizeWords(name),
                surname: this.capitalizeWords(surname),
                licenseNumber: licenseNumber.toUpperCase(),
                licenseExpiry: $('#license-expiry').val().trim()
            };
        },

        // Capitalize first letter of each word
        capitalizeWords: function(str) {
            if (!str) return '';
            return str.replace(/\b\w+/g, function(word) {
                return word.charAt(0).toUpperCase() + word.slice(1).toLowerCase();
            });
        },

        // Initialize custom datepicker
        initCustomDatePicker: function() {
            var self = this;
            
            $('#date-picker-btn, #license-expiry').on('click', function(e) {
                e.preventDefault();
                self.showDatePicker();
            });
            
            $('#prev-month').on('click', function() {
                self.currentDate.setMonth(self.currentDate.getMonth() - 1);
                self.renderCalendar();
            });
            
            $('#next-month').on('click', function() {
                self.currentDate.setMonth(self.currentDate.getMonth() + 1);
                self.renderCalendar();
            });
            
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
            
            var monthNames = ['January', 'February', 'March', 'April', 'May', 'June',
                'July', 'August', 'September', 'October', 'November', 'December'];
            $('#current-month-year').text(monthNames[month] + ' ' + year);
            
            var firstDay = new Date(year, month, 1);
            var lastDay = new Date(year, month + 1, 0);
            var daysInMonth = lastDay.getDate();
            var startingDayOfWeek = (firstDay.getDay() + 6) % 7;
            
            var daysContainer = $('#datepicker-days');
            daysContainer.empty();
            
            var today = new Date();
            today.setHours(0, 0, 0, 0);
            
            for (var i = 0; i < startingDayOfWeek; i++) {
                var prevMonthDay = new Date(year, month, 1 - startingDayOfWeek + i);
                var dayElement = $('<div class="datepicker-day other-month">')
                    .text(prevMonthDay.getDate());
                daysContainer.append(dayElement);
            }
            
            for (var day = 1; day <= daysInMonth; day++) {
                var currentDay = new Date(year, month, day);
                var dayElement = $('<div class="datepicker-day">')
                    .text(day)
                    .data('date', currentDay.getTime());
                
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
            
            var totalCells = daysContainer.children().length;
            var remainingCells = 42 - totalCells;
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
            
            $('.datepicker-day').removeClass('selected');
            $('.datepicker-day').each(function() {
                if ($(this).data('date') === date.getTime()) {
                    $(this).addClass('selected');
                }
            });
        },

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

        isValidDateFormat: function(dateString) {
            var regex = /^\d{2}\/\d{2}\/\d{4}$/;
            return regex.test(dateString);
        },

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
                
                // Apply sanitization when loading existing driver data
                $('#name').val(this.capitalizeWords(driver.name || ''));
                $('#surname').val(this.capitalizeWords(driver.surname || ''));
                $('#license-number').val((driver.licenseNumber || '').toUpperCase());
                
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
            $('#loading-indicator').show();
            $('#drivers-table').hide();
            
            $.get(this.baseUrl).done(function(drivers) {
                self.drivers = drivers;
                self.filterAndRenderDrivers();
                $('#loading-indicator').hide();
                $('#drivers-table').show();
            }).fail(function() {
                $('#loading-indicator').hide();
                OC.Notification.showTemporary('Failed to load drivers');
            });
        },

        renderDrivers: function() {
            var self = this;
            var tbody = $('#drivers-table tbody');
            tbody.empty();
            
            var paginatedDrivers = this.getPaginatedDrivers();
            
            if (paginatedDrivers.length === 0) {
                $('#no-results').show();
                $('#drivers-table').hide();
                return;
            } else {
                $('#no-results').hide();
                $('#drivers-table').show();
            }
            
            paginatedDrivers.forEach(function(driver) {
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
            var formData = this.sanitizeFormData();
            
            if (!formData.name || !formData.surname || !formData.licenseNumber || !formData.licenseExpiry) {
                OC.Notification.showTemporary('Please fill in all fields');
                return;
            }

            if (!this.isValidDateFormat(formData.licenseExpiry)) {
                OC.Notification.showTemporary('Please select a valid expiry date');
                return;
            }

            formData.licenseExpiry = this.convertToBackendFormat(formData.licenseExpiry);
            
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
